<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Transaction;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\TransactionContract\TransactionRunnerInterface;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\DoctrineTransactionRunner;
use Nowo\WikiBundle\Doctrine\WikiEntityManager;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiSpace;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

use function is_object;
use function max;
use function min;
use function random_int;
use function spl_object_id;
use function str_contains;
use function str_starts_with;
use function usleep;

/**
 * 1.37 (Beacon spec 239): the runner of every wiki write, registered as `nowo_wiki.transaction_runner` and injected into
 * the wiki services by its class. The bundle never runs its writes through a runner the application binds to
 * nowo-tech/transaction-contract's {@see TransactionRunnerInterface}: such a runner drives the application's entity
 * manager (it would commit the application's pending work with a wiki write, and reset that manager after a wiki failure).
 *
 * One attempt, one transaction (a savepoint inside a transaction the caller opened), the wiki's manager flushed inside
 * it and committed by the runner: the bundle never flushes inside a transaction it did not open. After any failure no
 * transaction of its own stays open: nowo-tech/transaction-doctrine rolls back to the entry level and closes the
 * connection only when that rollback fails (after a MySQL / MariaDB deadlock inside a nested flush the server dropped the
 * savepoints and `ROLLBACK TO SAVEPOINT` fails with 1305). A transient failure (deadlock, lock wait timeout, serialization
 * failure, lost savepoint) surfaces as {@see TransientStorageFailure} with a Retry-After hint: 503 on the bundle's routes.
 *
 * - **Private entity manager** (`database.private_entity_manager: true`): transaction-doctrine's ORM runner on the
 *   wiki's own manager ({@see WikiEntityManager}); after a failure it forgets what the unit loaded or wrote, and a manager
 *   closed by a failed flush is created again on its next call. The application's manager is never involved.
 * - **Shared manager** (the default, `database.entity_manager`): the unit runs in a connection-level transaction, because
 *   transaction-doctrine's ORM runner would close and reset (or clear) that manager after a storage failure, detaching the
 *   application's entities behind its back (audit QD-M1). After a failure only the wiki rows the unit added, wrote or
 *   changed are detached (and the wiki rows that point at them); every entity of the application stays managed. A failed
 *   flush closes the manager (the ORM does): it stays closed for the rest of the request, so the application's next flush
 *   fails loudly, and {@see self::reset()} replaces it at the next request, message or command boundary.
 *
 * {@see self::run()} makes one attempt (HTTP writes: the client retries after the 503). {@see self::runRetrying()} replays
 * a transient failure a few times with full-jitter backoff, only when it is the outermost transaction (console imports and
 * conversions), so the operation must be replayable and its side effects must wait until it returned.
 *
 * The lock helpers ({@see self::lockSpace()}, {@see self::lockRow()}) are called inside a unit, in one order: co-editing
 * log, space, page, comment thread (docs/HIGH-LOAD.md).
 *
 * Stateless apart from the managers it is given: one instance serves every request of a long-running worker.
 */
final readonly class WikiTransactionRunner implements TransactionRunnerInterface, ResetInterface
{
    /** The service id the wiki services are wired to. */
    public const SERVICE_ID = 'nowo_wiki.transaction_runner';

    /** Attempts of {@see self::runRetrying()} (the first one included). */
    public const RETRY_ATTEMPTS = 3;

    /** Upper bound of the first backoff, in milliseconds; it doubles per attempt (full jitter: 0 to the bound). */
    public const BACKOFF_BASE_MILLISECONDS = 50;

    /** Upper bound of any backoff, in milliseconds. */
    public const BACKOFF_CAP_MILLISECONDS = 1000;

    /** Namespace of the wiki's entities. */
    private const ENTITY_NAMESPACE = 'Nowo\\WikiBundle\\Entity\\';

    /**
     * @param EntityManagerInterface|WikiEntityManagerProvider $entityManager the wiki's manager (or its provider, resolved per call)
     * @param ManagerRegistry|null $registry replaces a shared manager a failed wiki flush closed, at the next boundary
     * @param string|null $managerName the registry name of the shared manager (`database.entity_manager`)
     * @param int|null $retryAfterSeconds Retry-After hint of the TransientStorageFailure; null for none
     * @param int $backoffBaseMilliseconds upper bound of the first backoff of {@see self::runRetrying()} (0 = no wait)
     * @param LoggerInterface|null $logger warns when a closed shared manager is replaced at a boundary
     */
    public function __construct(
        private EntityManagerInterface|WikiEntityManagerProvider $entityManager,
        private ?ManagerRegistry $registry = null,
        private ?string $managerName = null,
        private ?int $retryAfterSeconds = 1,
        private int $backoffBaseMilliseconds = self::BACKOFF_BASE_MILLISECONDS,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function run(callable $operation): mixed
    {
        $manager = $this->manager();
        if ($manager instanceof WikiEntityManager) {
            // The wiki's own manager: transaction-doctrine's ORM runner (forgets what a failed unit loaded or wrote; a
            // closed manager is created again by the decorator on its next call). The application's manager is not involved.
            return DoctrineTransactionRunner::forEntityManager($manager, null, null, $this->retryAfterSeconds)->run($operation);
        }

        // The application's manager (shared): a connection-level transaction, and only the wiki rows the unit touched
        // are forgotten after a failure (audit QD-M1).
        $before = $this->wikiRows($manager);
        try {
            return DoctrineTransactionRunner::forConnection($manager->getConnection(), $this->retryAfterSeconds)->run(static function () use ($operation, $manager): mixed {
                $result = $operation();
                $manager->flush();

                return $result;
            });
        } catch (Throwable $failure) {
            // A manager the ORM closed in a failed flush stays closed: loud for the application; replaced at the boundary.
            if ($manager->isOpen()) {
                $this->forgetWikiRows($manager, $before);
            }

            throw $failure;
        }
    }

    /**
     * Same as {@see self::run()}, but a transient failure of the OUTERMOST transaction is replayed up to `$attempts`
     * times in all, after a full-jitter backoff. Inside a transaction the caller opened it is not replayed: the storage
     * may have rolled back the caller's work as well (a MySQL deadlock rolls back the whole transaction), so the failure
     * goes to the caller. The operation must be replayable: it loads what it changes, and its side effects wait until
     * this method returned.
     *
     * @template T
     *
     * @param callable(): T $operation the unit of work; replayable
     * @param int $attempts attempts in all (at least one)
     *
     * @throws TransientStorageFailure when the last attempt failed transiently, or a nested one did
     *
     * @return T what the operation returned
     */
    public function runRetrying(callable $operation, int $attempts = self::RETRY_ATTEMPTS): mixed
    {
        $attempts = max(1, $attempts);
        for ($attempt = 1;; ++$attempt) {
            $outermost = !$this->manager()->getConnection()->isTransactionActive();
            try {
                return $this->run($operation);
            } catch (TransientStorageFailure $failure) {
                if (!$outermost || $attempt >= $attempts) {
                    throw $failure;
                }
                $this->backoff($attempt);
            }
        }
    }

    /**
     * Inside a unit: a write lock on the space row (`SELECT … FOR UPDATE`; none on SQLite, where a writing transaction
     * locks the whole database). The space row is the lock of its tree and of its attachment quota.
     */
    public function lockSpace(WikiSpace $space): void
    {
        $this->lockRow(WikiSpace::class, $space->getId());
    }

    /**
     * Inside a unit: a write lock on the row of a wiki entity, then the entity read again under it (its state is the
     * committed one, whatever the caller loaded before). A row that is gone answers false; the entity is left as it was.
     *
     * @param object $entity a managed wiki entity (page, comment thread)
     */
    public function lockEntity(object $entity): bool
    {
        $manager = $this->manager();
        $class   = $manager->getClassMetadata($entity::class);
        $id      = $class->getIdentifierValues($entity);
        if (!$this->lockRow($class->getName(), (string) ($id[$class->getSingleIdentifierFieldName()] ?? ''))) {
            return false;
        }
        $manager->refresh($entity);

        return true;
    }

    /**
     * Inside a unit: a write lock on one row of a wiki class by its id. Re-checks the id with `===` (a case-folding
     * collation could match a look-alike id).
     *
     * @param class-string $class the wiki entity class
     * @param string $id the row's id
     *
     * @return bool whether the row exists
     */
    public function lockRow(string $class, string $id): bool
    {
        /** @var list<array{id: string}> $rows */
        $rows = $this->manager()->createQueryBuilder()
            ->select('x.id AS id')
            ->from($class, 'x')
            ->where('x.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getArrayResult();
        foreach ($rows as $row) {
            if ((string) $row['id'] === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * At a request, message or command boundary: a shared manager that a failed wiki flush closed is replaced, so the
     * next request of a long-running worker starts with an open one. Nothing happens to an open manager, or with the
     * private manager (the wiki replaces its own).
     */
    public function reset(): void
    {
        if (!$this->registry instanceof ManagerRegistry || $this->entityManager instanceof EntityManagerInterface) {
            return;
        }
        $manager = $this->registry->getManager($this->managerName);
        if (!$manager instanceof EntityManagerInterface || $manager->isOpen() || $this->entityManager->get() instanceof WikiEntityManager) {
            return;
        }
        $this->registry->resetManager($this->managerName);
        $this->logger?->warning('Wiki: the entity manager closed by a failed write was replaced at the request boundary.', ['bundle' => 'nowo_wiki']);
    }

    /** The wiki's manager of this call. */
    public function manager(): EntityManagerInterface
    {
        return $this->entityManager instanceof WikiEntityManagerProvider ? $this->entityManager->get() : $this->entityManager;
    }

    /**
     * Sleeps a random time between 0 and the attempt's bound (full jitter: concurrent losers do not retry in lockstep).
     */
    private function backoff(int $attempt): void
    {
        $bound = min(self::BACKOFF_CAP_MILLISECONDS, $this->backoffBaseMilliseconds << ($attempt - 1));
        if ($bound > 0) {
            usleep(random_int(0, $bound) * 1000);
        }
    }

    /** Whether a class is one of the wiki's entities. */
    private function isWikiEntity(string $class): bool
    {
        return str_starts_with($class, self::ENTITY_NAMESPACE) || str_contains($class, '\\__CG__\\' . self::ENTITY_NAMESPACE);
    }

    /**
     * The wiki rows the manager holds, with the data it knows them by (before a unit of work).
     *
     * @return array<int, array<string, mixed>> object id => original data
     */
    private function wikiRows(EntityManagerInterface $manager): array
    {
        $unitOfWork = $manager->getUnitOfWork();
        $rows       = [];
        foreach ($unitOfWork->getIdentityMap() as $class => $entities) {
            if ($this->isWikiEntity($class)) {
                foreach ($entities as $entity) {
                    $rows[spl_object_id($entity)] = $unitOfWork->getOriginalEntityData($entity);
                }
            }
        }

        return $rows;
    }

    /**
     * After a failed unit: detaches the wiki rows it added, wrote or changed (their database state was rolled back); a
     * wiki row the caller held and the unit did not change stays managed, and so does every entity of the application.
     *
     * @param array<int, array<string, mixed>> $before {@see self::wikiRows()} before the unit
     */
    private function forgetWikiRows(EntityManagerInterface $manager, array $before): void
    {
        $unitOfWork = $manager->getUnitOfWork();
        $rows       = $unitOfWork->getScheduledEntityInsertions();
        foreach ($unitOfWork->getIdentityMap() as $class => $entities) {
            if ($this->isWikiEntity($class)) {
                foreach ($entities as $entity) {
                    $rows[] = $entity;
                }
            }
        }
        $stale = [];
        $kept  = [];
        foreach ($rows as $entity) {
            if (!$this->isWikiEntity($entity::class) || !$manager->contains($entity)) {
                continue;
            }
            $id = spl_object_id($entity);
            if (!isset($before[$id]) || $before[$id] !== $unitOfWork->getOriginalEntityData($entity) || $this->changed($manager, $entity)) {
                $stale[$id] = $entity;
            } else {
                $kept[$id] = $entity;
            }
        }
        // A kept row that points at a stale one goes too (it would drag it back as a new entity on the next flush).
        do {
            $more = false;
            foreach ($kept as $id => $entity) {
                $metadata = $manager->getClassMetadata($entity::class);
                foreach ($metadata->associationMappings as $field => $mapping) {
                    $target = $mapping->isToOne() ? $metadata->getFieldValue($entity, $field) : null;
                    if (is_object($target) && isset($stale[spl_object_id($target)])) {
                        $stale[$id] = $entity;
                        unset($kept[$id]);
                        $more = true;

                        break;
                    }
                }
            }
        } while ($more);
        foreach ($stale as $entity) {
            $manager->detach($entity);
        }
    }

    /**
     * Whether the row has changes the manager would write (the ORM's own comparison with the data it knows it by).
     */
    private function changed(EntityManagerInterface $manager, object $entity): bool
    {
        $unitOfWork = $manager->getUnitOfWork();
        $unitOfWork->computeChangeSet($manager->getClassMetadata($entity::class), $entity);

        return $unitOfWork->getEntityChangeSet($entity) !== [];
    }
}
