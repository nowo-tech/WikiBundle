<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Transaction;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\TransactionContract\TransactionRunnerInterface;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\DoctrineTransactionRunner;
use Nowo\WikiBundle\Doctrine\WikiEntityManager;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Doctrine\WikiFlusher;
use Nowo\WikiBundle\Entity\WikiSpace;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\ResetInterface;

use function max;
use function min;
use function random_int;
use function trigger_deprecation;
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
 * The unit runs on the wiki's private entity manager ({@see WikiEntityManager}, the only mode since 2.0):
 * transaction-doctrine's ORM runner; after a failure it forgets what the unit loaded or wrote, and a manager closed by a
 * failed flush is created again on its next call. The application's manager is never involved. A manager given by hand
 * (an {@see EntityManagerInterface} instead of the provider, or a provider built without the private manager) runs each
 * unit in a connection-level transaction flushed at its end; after a failure nothing of it is cleared, detached or
 * reset.
 *
 * {@see self::run()} makes one attempt (HTTP writes: the client retries after the 503). {@see self::runRetrying()} replays
 * a transient failure a few times with full-jitter backoff, only when it is the outermost transaction, for a replayable
 * background unit of an application (no wiki service calls it: their writes make one attempt); its side effects must
 * wait until it returned.
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

    /**
     * @param EntityManagerInterface|WikiEntityManagerProvider $entityManager the wiki's manager (or its provider, resolved per call)
     * @param object|null $registry @deprecated since 2.0, ignored (it replaced a closed shared manager); removed in 3.0
     * @param string|null $managerName @deprecated since 2.0, ignored (the shared manager's name); removed in 3.0
     * @param int|null $retryAfterSeconds Retry-After hint of the TransientStorageFailure; null for none
     * @param int $backoffBaseMilliseconds upper bound of the first backoff of {@see self::runRetrying()} (0 = no wait)
     * @param LoggerInterface|null $logger @deprecated since 2.0, ignored (it warned when a closed shared manager was
     *                                     replaced); removed in 3.0
     */
    public function __construct(
        private EntityManagerInterface|WikiEntityManagerProvider $entityManager,
        ?object $registry = null,
        ?string $managerName = null,
        private ?int $retryAfterSeconds = 1,
        private int $backoffBaseMilliseconds = self::BACKOFF_BASE_MILLISECONDS,
        ?LoggerInterface $logger = null,
    ) {
        // Deprecated arguments of the removed shared manager, kept for the signature (removed in 3.0).
        if ($registry !== null || $managerName !== null || $logger instanceof LoggerInterface) {
            trigger_deprecation('nowo-tech/wiki-bundle', '2.0', 'The $registry, $managerName and $logger arguments of %s are ignored since 2.0 (the private entity manager is the only mode) and removed in 3.0.', self::class);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function run(callable $operation): mixed
    {
        $manager = $this->manager();
        if ($manager instanceof WikiEntityManager) {
            // The wiki's own manager: transaction-doctrine's ORM runner (forgets what a failed unit loaded or wrote; a
            // closed private manager is created again by the decorator on its next call).
            return DoctrineTransactionRunner::forEntityManager($manager, null, null, $this->retryAfterSeconds)->run($operation);
        }

        // A manager given by hand: one connection-level unit flushed at its end; after a failure nothing is cleared,
        // detached or reset (the wiki does not own that manager).
        return DoctrineTransactionRunner::forConnection($manager->getConnection(), $this->retryAfterSeconds)->run(static function () use ($operation, $manager): mixed {
            $result = $operation();
            WikiFlusher::flush($manager);

            return $result;
        });
    }

    /**
     * 1.37 (audit QN-M1): a unit of work on the wiki's connection that flushes nothing itself (the co-editing log, which
     * writes through DBAL), around wiki writes that may flush (a page save inside the log's lock). After a failure the
     * private manager follows its own rollback bookkeeping.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function runOnConnection(callable $operation): mixed
    {
        return DoctrineTransactionRunner::forConnection($this->manager()->getConnection(), $this->retryAfterSeconds)->run($operation);
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
     * 1.50: inside a unit, a write lock on the space row (as {@see self::lockSpace()}) that also answers whether the space
     * is archived (or being purged, which archives it), read by the locking statement itself: the latest committed state,
     * whatever the caller loaded. A space row that is gone answers false (the write then fails on its foreign key).
     */
    public function lockSpaceArchived(WikiSpace $space): bool
    {
        /** @var list<array{id: string, archivedAt: mixed}> $rows */
        $rows = $this->manager()->createQueryBuilder()
            ->select('x.id AS id', 'x.archivedAt AS archivedAt')
            ->from(WikiSpace::class, 'x')
            ->where('x.id = :id')
            ->setParameter('id', $space->getId())
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getArrayResult();
        foreach ($rows as $row) {
            if ((string) $row['id'] === $space->getId()) {
                return $row['archivedAt'] !== null;
            }
        }

        return false;
    }

    /**
     * Inside a unit: a write lock on the row of a wiki entity, then the entity read again under it with a locking read
     * (its state is the latest committed one, whatever the caller loaded before, also inside a host transaction). A row that is gone answers false; the entity is left as it was.
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
        // 1.38 (audit QY-L3): a locking read, so inside a host transaction under REPEATABLE READ the entity is read from
        // the latest commit, not from the snapshot the host's first read took (a plain refresh would be a snapshot read).
        $manager->refresh($entity, LockMode::PESSIMISTIC_WRITE);

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
     * @deprecated since 2.0, no effect (it replaced a closed shared manager at a boundary; the private manager replaces
     *             itself); removed in 3.0
     */
    public function reset(): void
    {
    }

    /**
     * 2.0 (W1-2): the private manager's instance of a wiki row handed to a wiki service ({@see WikiEntityManager::attach()}):
     * a row your application's manager loaded is loaded again by its id, so the wiki writes it itself and never inserts
     * it a second time. With a manager given by hand, the row is returned as it is.
     *
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    public function attach(object $entity): object
    {
        $manager = $this->manager();

        return $manager instanceof WikiEntityManager ? $manager->attach($entity) : $entity;
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
}
