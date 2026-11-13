<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Doctrine;

use Closure;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepositoryInterface;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Query\Filter\Parameter;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Service\WikiRequestBoundaryResetter;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

use function array_keys;
use function array_sum;
use function in_array;
use function is_a;
use function is_object;
use function is_string;
use function ksort;
use function spl_object_id;
use function sprintf;
use function str_starts_with;
use function strrpos;
use function substr;

/**
 * The wiki's private entity manager (1.34.0, REQ-ARCH-001 rule 6; opt-in with
 * `nowo_wiki.database.private_entity_manager: true` until 2.0). Adapted from AuthKitBundle 1.46.0 (audits NV,
 * NJ-2, NJV-1, NF-2, NF2-2, NF3-2, NF-7) with audit NZ's repository factory.
 *
 * Up to 1.33 the bundle wrote its rows through `database.entity_manager` (the application's `default` manager unless
 * configured): every wiki save flushed whatever the application had left pending in that unit of work, and a failed
 * wiki write closed the application's manager. With the option on, every wiki service writes and reads through
 * this **private** manager, created on the connection, mapping, caches, proxies and event manager of the source manager
 * (`database.entity_manager`): the tables do not move, the bundle's Doctrine listeners (table names, user references) and
 * the host's listeners still run, and a wiki flush never commits an application change. The connection is shared,
 * so a transaction the application opened includes the wiki rows (the bundle's own transactions become savepoints).
 *
 * The object is a stable decorator (the repositories and services keep it); the wrapped manager is resolved on every call
 * and created again when:
 *
 * - it was closed (its own failed flush) or the source connection changed;
 * - a rollback (transaction or savepoint) undid rows it wrote inside a transaction, so its identity map may describe
 *   rows that no longer exist ({@see PrivateEntityManagerMiddleware}; DBAL 4 only, with DBAL 3 the manager is only
 *   replaced at the next boundary). A stale manager that still has scheduled work is kept until that work is flushed or
 *   the next boundary;
 * - {@see reset()} ran: at every outermost unit-of-work boundary (the bundle's
 *   {@see WikiRequestBoundaryResetter}: start and end of the main request,
 *   of a Messenger message and of the outermost console command) and at `kernel.reset`. Changes still pending at that
 *   moment are dropped and logged as a warning (entity classes and counts, never a value).
 *
 * Host objects reached from wiki rows (the `user_class` revision author, link creator, uploader, comment author, ...) are adopted before each flush:
 * an instance the private manager does not hold (e.g. the security user, managed by the application's manager) is
 * replaced on the wiki row by the private manager's reference to the same id, so Doctrine never mistakes it for a
 * new entity. The application's instance is never written.
 *
 * The SQL filters enabled on the source manager, with their parameters, are mirrored on every call.
 *
 * @internal
 */
final class WikiEntityManager extends EntityManagerDecorator implements ResetInterface
{
    private ?EntityManagerInterface $current = null;

    /** A rollback undid rows the private manager wrote since it was created or last replaced. */
    private bool $stale = false;

    /**
     * Transaction nesting levels of the connection in which the private manager wrote (flush, bulk DQL / DBAL, refresh)
     * or loaded rows that nobody committed yet (audits NJ-2, NJV-1: only a rollback of one of these levels makes the
     * identity map stale).
     *
     * @var array<int, true>
     */
    private array $uncommitted = [];

    /**
     * Repositories that are not container services, bound to this decorator (stable across the replacements of the
     * private manager): a factory keeping one per private manager created would make memory grow in a kept-alive kernel.
     * Bounded by the number of mapped classes asked for; emptied by {@see reset()} with the manager.
     *
     * @var array<class-string, EntityRepository<object>>
     */
    private array $repositories = [];

    /**
     * @param ManagerRegistry $registry The application's Doctrine registry (the source manager is resolved per call)
     * @param string $sourceManagerName `database.entity_manager` (the manager 1.33 wrote the wiki rows through)
     * @param LoggerInterface|null $logger warns when a boundary drops pending changes
     */
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly string $sourceManagerName = 'default',
        private readonly ?LoggerInterface $logger = null,
    ) {
        // No wrapped manager yet: every read of $this->wrapped goes through __get() (resolved per call).
        unset($this->{'wrapped'});
    }

    /**
     * The wrapped manager of {@see EntityManagerDecorator} (the only magic property).
     */
    public function __get(string $name): EntityManagerInterface
    {
        if ($name !== 'wrapped') {
            throw new LogicException(sprintf('Undefined property %s::$%s.', self::class, $name));
        }

        return $this->live();
    }

    /**
     * The open private manager, created (again) when needed, with the source's SQL filters.
     */
    public function live(): EntityManagerInterface
    {
        $source  = $this->source();
        $current = $this->current;
        if (!$current instanceof EntityManagerInterface || !$current->isOpen() || $current->getConnection() !== $source->getConnection()
            || ($this->stale && !$this->hasScheduledWork($current))) {
            if ($current instanceof EntityManagerInterface && $current->isOpen()) {
                $current->clear();
            }
            // DoctrineBundle's ContainerAwareEventManager extends EventManager; any other implementation gets a fresh one.
            $eventManager = $source->getEventManager();
            // Same mapping, caches and proxies, but a repository factory owned by this manager (audit NZ): the shared factory
            // keeps one repository per manager instance for the life of the process, so every replaced private manager
            // would stay in memory; service repositories still come from the source factory.
            $configuration = clone $source->getConfiguration();
            $configuration->setRepositoryFactory(new PrivateManagerRepositoryFactory($configuration->getRepositoryFactory()));
            // @igor-ignore - Bundle-owned unit of work: dropped at every boundary, replaced when closed, stale or on another connection.
            $current = $this->current = new EntityManager($source->getConnection(), $configuration, $eventManager instanceof EventManager ? $eventManager : null);
            // @igor-ignore - Bundle-owned flag of the private manager, reset when the manager is replaced.
            $this->stale = false;
            // @igor-ignore - Bundle-owned bookkeeping, reset when the manager is replaced.
            $this->uncommitted = [];
        }
        $this->syncFilters($source, $current);

        return $current;
    }

    /**
     * Whether a private manager exists right now (no side effect; tests and the boundary subscriber).
     */
    public function isCreated(): bool
    {
        return $this->current instanceof EntityManagerInterface;
    }

    /**
     * Whether a rollback made the private manager's identity map stale (no side effect; tests).
     */
    public function isStale(): bool
    {
        return $this->stale;
    }

    /**
     * A transaction (level 1) or savepoint (its level; null when the level is unknown) was rolled back on the
     * connection (told by {@see PrivateEntityManagerConnection}). The manager becomes stale only when the rollback
     * undid rows it wrote (audit NJ-2: an unrelated rollback of the application, or of a wiki savepoint, leaves it
     * alone); a stale manager is replaced at its next use unless it has scheduled work. It is never cleared here (the
     * rollback may run inside the private manager's own failing flush).
     */
    public function connectionRolledBack(?int $level = 1): void
    {
        $undone = false;
        foreach (array_keys($this->uncommitted) as $written) {
            if ($level === null || $written >= $level) {
                $undone = true;
                // @igor-ignore - Bundle-owned bookkeeping of the private manager's open transaction levels.
                unset($this->uncommitted[$written]);
            }
        }
        if ($undone && $this->current instanceof EntityManagerInterface) {
            // @igor-ignore - Bundle-owned flag of the private manager, cleared when the manager is replaced.
            $this->stale = true;
        }
    }

    /**
     * The outermost transaction was committed: every row the private manager wrote is durable.
     */
    public function connectionCommitted(): void
    {
        // @igor-ignore - Bundle-owned bookkeeping of the private manager's open transaction levels.
        $this->uncommitted = [];
    }

    /**
     * A savepoint of this level was released: its rows now belong to the level below.
     */
    public function savepointReleased(int $level): void
    {
        if (isset($this->uncommitted[$level])) {
            // @igor-ignore - Bundle-owned bookkeeping of the private manager's open transaction levels.
            unset($this->uncommitted[$level]);
            if ($level > 1) {
                // @igor-ignore - Bundle-owned bookkeeping of the private manager's open transaction levels.
                $this->uncommitted[$level - 1] = true;
            }
        }
    }

    /**
     * Service repositories (`ServiceEntityRepositoryInterface`) come from the source configuration's factory (audit NZ);
     * every other repository is built on this decorator (stable across the replacements of the private manager) and kept
     * until {@see reset()}: bounded by the number of mapped classes the bundle asks for.
     *
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return EntityRepository<T>
     */
    public function getRepository(string $className): EntityRepository
    {
        $live     = $this->live();
        $metadata = $live->getClassMetadata($className);
        $class    = $metadata->customRepositoryClassName;
        if ($class !== null && is_a($class, ServiceEntityRepositoryInterface::class, true)) {
            return $live->getRepository($className);
        }

        /** @var EntityRepository<T> $repository */
        $repository = $this->repositories[$metadata->getName()] ??= PrivateManagerRepositoryFactory::create($this, $metadata, $class ?? $live->getConfiguration()->getDefaultRepositoryClassName());

        return $repository;
    }

    public function flush(): void
    {
        $live = $this->live();
        $this->adoptForeignReferences($live);
        $live->flush();
        $this->wrote();
    }

    public function refresh(object $object, LockMode|int|null $lockMode = LockMode::NONE): void
    {
        $this->live()->refresh($object, $lockMode);
        $this->wrote();
    }

    /**
     * A wiki statement that bypassed the unit of work (bulk DQL, DBAL) wrote at the current level (audit NJV-1).
     */
    public function noteWrite(): void
    {
        $this->wrote();
    }

    /**
     * Rows loaded by the private manager inside a transaction may be the application's uncommitted writes: a rollback
     * of that level makes the identity map stale too (audit NJV-1). Doctrine `postLoad` listener; other managers'
     * loads are ignored.
     */
    public function postLoad(PostLoadEventArgs $args): void
    {
        if ($this->current instanceof EntityManagerInterface && $args->getObjectManager() === $this->current) {
            $this->wrote();
        }
    }

    /**
     * Rolls back the transaction or savepoint the wiki opened. Inside the application's transaction (a savepoint of
     * the wiki), a rollback that fails (the server already ended the transaction, a lost connection) is logged and
     * swallowed, so the wiki's caller rethrows the ORIGINAL failure, never the rollback's; the private manager is
     * then treated as stale. An outermost rollback that fails is rethrown as before.
     */
    public function rollback(): void
    {
        $live  = $this->live();
        $level = $live->getConnection()->getTransactionNestingLevel();
        try {
            $live->rollback();
        } catch (Throwable $exception) {
            if ($level <= 1) {
                throw $exception;
            }
            $this->connectionRolledBack(null);
            try {
                $this->logger?->warning('Wiki: rolling back a wiki savepoint inside the application\'s transaction failed ({exception}); the original failure is rethrown by the caller.', [
                    'bundle'    => 'nowo_wiki',
                    'exception' => $exception::class,
                ]);
            } catch (Throwable) {
                // A failing logger never hides the original failure either.
            }
        }
    }

    public function wrapInTransaction(callable $func): mixed
    {
        try {
            return $this->live()->wrapInTransaction($func);
        } finally {
            $this->wrote();
        }
    }

    /**
     * Host objects on wiki rows that the private manager does not hold (the security user, a user the application
     * loaded) and saved wiki rows of another manager (a space a legacy host port loaded through the application's
     * manager) become the private manager's reference to the same id, so Doctrine never takes them for new entities.
     * Only to-one associations of wiki entities are looked at; an object without an identifier, or a wiki entity whose
     * row does not exist yet, is left alone, so Doctrine reports it exactly as before.
     */
    private function adoptForeignReferences(EntityManagerInterface $manager): void
    {
        $unitOfWork = $manager->getUnitOfWork();
        $rows       = $unitOfWork->getScheduledEntityInsertions();
        foreach ($unitOfWork->getIdentityMap() as $managed) {
            foreach ($managed as $entity) {
                $rows[] = $entity;
            }
        }
        // The wiki entities map no cascade-persist association: every new wiki row is persisted explicitly, so the
        // scheduled insertions and the identity map hold every row the flush writes.
        $seen = [];
        foreach ($rows as $entity) {
            $key = spl_object_id($entity);
            if (isset($seen[$key]) || !self::isBundleEntity($entity::class)) {
                continue;
            }
            $seen[$key] = true;
            $metadata   = $manager->getClassMetadata($entity::class);
            foreach ($metadata->associationMappings as $field => $mapping) {
                $value = $metadata->getFieldValue($entity, $field);
                if (!$mapping->isToOne() || !is_object($value) || $manager->contains($value) || !$this->isSavedElsewhere($manager, $value)) {
                    continue;
                }
                $target = $manager->getClassMetadata($value::class);
                $id     = $target->getIdentifierValues($value);
                if ($id !== [] && !in_array(null, $id, true)) {
                    $metadata->setFieldValue($entity, $field, $manager->getReference($target->getName(), $id));
                }
            }
        }
    }

    /**
     * Whether an object the private manager does not hold is a saved row of another manager: always for a host object
     * (the security user); for a wiki entity (a space or page a legacy host port loaded through the application's
     * manager) only when its row exists, since wiki entities get their id in the constructor and a new one must stay
     * new (Doctrine then reports it exactly as before).
     */
    private function isSavedElsewhere(EntityManagerInterface $manager, object $value): bool
    {
        if (!self::isBundleEntity($value::class)) {
            return true;
        }

        return $manager->getUnitOfWork()->getEntityState($value) === UnitOfWork::STATE_DETACHED;
    }

    /**
     * Records the nesting level the private manager's last write is waiting in (none outside a transaction).
     */
    private function wrote(): void
    {
        $level = $this->current?->getConnection()->getTransactionNestingLevel() ?? 0;
        if ($level > 0) {
            // @igor-ignore - Bundle-owned bookkeeping of the private manager's open transaction levels.
            $this->uncommitted[$level] = true;
        }
    }

    /**
     * Detaches every row from the private manager. Never touches an application manager and never creates the private
     * one (the bundle's batch writes call it to keep memory flat).
     */
    public function clear(): void
    {
        if ($this->current instanceof EntityManagerInterface && $this->current->isOpen()) {
            $this->current->clear();
        }
    }

    /**
     * Drops the private manager (a unit-of-work boundary or `kernel.reset`); the next call creates a fresh one.
     * Changes still pending are lost: they are logged as a warning with their entity classes and counts. The manager is
     * dropped FIRST, so a failing inspection or clear never keeps it across the boundary.
     */
    public function reset(): void
    {
        $current = $this->current;
        // Dropped first, so a failure below can never keep a manager across the boundary (audit NF2-2).
        // @igor-ignore - Boundary drop of the bundle's own manager (no state kept).
        $this->current = null;
        // @igor-ignore - Boundary drop of the bundle's own manager (no state kept).
        $this->stale = false;
        // @igor-ignore - Boundary drop of the bundle's own manager (no state kept).
        $this->uncommitted  = [];
        $this->repositories = [];
        if (!$current instanceof EntityManagerInterface || !$current->isOpen()) {
            return;
        }

        try {
            $this->warnAboutDroppedChanges($current);
        } catch (Throwable $exception) {
            // Computing the change sets can throw (invalid association, detached or half-built entity).
            $this->logGenericWarning($exception);
        }

        try {
            $current->clear();
        } catch (Throwable $exception) {
            // A host onClear listener that throws must not abort the boundary (audit NF3-2): the manager is dropped.
            $this->logGenericWarning($exception);
        }
    }

    /**
     * The application's manager whose connection, configuration, events and filters the private one shares. Never
     * flushed, cleared, closed or reset by the bundle.
     *
     * @throws LogicException when the source is not an ORM manager or does not map the wiki entities
     */
    public function source(): EntityManagerInterface
    {
        $source = $this->registry->getManager($this->sourceManagerName);
        if (!$source instanceof EntityManagerInterface || $source->getMetadataFactory()->isTransient(WikiPage::class)) {
            throw new LogicException(sprintf('The Doctrine entity manager "%s" (nowo_wiki.database.entity_manager, the source of database.private_entity_manager) does not map %s.', $this->sourceManagerName, WikiPage::class));
        }

        return $source;
    }

    /**
     * The fallback warning of {@see reset()}; a failing logger never breaks the boundary.
     */
    private function logGenericWarning(Throwable $exception): void
    {
        try {
            $this->logger?->warning('Wiki: the bundle\'s private entity manager was dropped at a unit-of-work boundary; its pending changes could not be inspected or cleared ({exception}).', [
                'bundle'    => 'nowo_wiki',
                'exception' => $exception::class,
            ]);
        } catch (Throwable) {
            // Nothing else to report to: the manager is dropped anyway.
        }
    }

    /**
     * Safety net (audits NF-2, ND-L2): a boundary that drops the private manager while it still has inserts, updates or
     * deletes waiting (a wiki entity changed and never saved through the bundle) logs them, per entity class.
     */
    private function warnAboutDroppedChanges(EntityManagerInterface $manager): void
    {
        if (!$this->logger instanceof LoggerInterface) {
            return;
        }

        $unitOfWork = $manager->getUnitOfWork();
        $unitOfWork->computeChangeSets();
        $changes = [];
        foreach ([$unitOfWork->getScheduledEntityInsertions(), $unitOfWork->getScheduledEntityUpdates(), $unitOfWork->getScheduledEntityDeletions()] as $entities) {
            foreach ($entities as $entity) {
                $class           = self::realClass($entity::class);
                $changes[$class] = ($changes[$class] ?? 0) + 1;
            }
        }
        if ($changes === []) {
            return;
        }

        ksort($changes);
        $this->logger->warning('Wiki: {count} pending change(s) of the bundle\'s private entity manager were dropped at a unit-of-work boundary. Write wiki rows through the wiki services; the application\'s flush() does not write them.', [
            'bundle'  => 'nowo_wiki',
            'count'   => array_sum($changes),
            'changes' => $changes,
        ]);
    }

    /**
     * Scheduled inserts, updates or deletes, including changes not computed yet (a managed entity edited in memory).
     */
    private function hasScheduledWork(EntityManagerInterface $manager): bool
    {
        $unitOfWork = $manager->getUnitOfWork();
        $unitOfWork->computeChangeSets();

        return $unitOfWork->getScheduledEntityInsertions() !== []
            || $unitOfWork->getScheduledEntityUpdates() !== []
            || $unitOfWork->getScheduledEntityDeletions() !== []
            || $unitOfWork->getScheduledCollectionUpdates() !== []
            || $unitOfWork->getScheduledCollectionDeletions() !== [];
    }

    /**
     * Mirrors the SQL filters enabled on the source manager, with their parameters, on the private one, and disables
     * the others. A filter whose parameters differ (its state string) is disabled and enabled again (a fresh instance)
     * before its parameters are copied, so a parameter the source no longer has (a previous tenant) never survives.
     * The parameters are read as they are stored (audit NF-7): values, list flag and the list type are kept.
     */
    private function syncFilters(EntityManagerInterface $source, EntityManagerInterface $target): void
    {
        $wanted  = $source->getFilters()->getEnabledFilters();
        $filters = $target->getFilters();
        // The private manager runs on a copy of the source configuration (its own repository factory): a filter the
        // application registered after the copy was made is added to the copy before it is enabled.
        $sourceConfiguration = $source->getConfiguration();
        $targetConfiguration = $target->getConfiguration();
        foreach (array_keys($filters->getEnabledFilters()) as $name) {
            if (!isset($wanted[$name])) {
                $filters->disable($name);
            }
        }
        foreach ($wanted as $name => $filter) {
            $state = (string) $filter;
            if ($filters->isEnabled($name)) {
                if ((string) $filters->getFilter($name) === $state) {
                    continue;
                }
                $filters->disable($name);
            }
            $class = $sourceConfiguration->getFilterClassName($name);
            if ($class !== null && $targetConfiguration->getFilterClassName($name) === null) {
                $targetConfiguration->addFilter($name, $class);
            }
            $copy = $filters->enable($name);
            foreach ($this->filterParameters($filter) as $parameter => $value) {
                if ($value->isList) {
                    /** @var array<mixed> $list */
                    $list = $value->value;
                    $copy->setParameterList($parameter, $list, is_string($value->type) ? $value->type : Types::STRING);
                } else {
                    // SQLFilter::setParameter() takes a string type or null: a non-string type was inferred, null infers it again.
                    $copy->setParameter($parameter, $value->value, is_string($value->type) ? $value->type : null);
                }
            }
        }
    }

    /**
     * The raw parameters of a filter (SQLFilter keeps them private and only exposes quoted values).
     *
     * @return array<string, Parameter>
     */
    private function filterParameters(SQLFilter $filter): array
    {
        $read = static fn (SQLFilter $filter): array => $filter->parameters; // @phpstan-ignore property.private (bound to SQLFilter below)
        /** @var array<string, Parameter> $parameters */
        $parameters = Closure::bind($read, null, SQLFilter::class)($filter);

        return $parameters;
    }

    /**
     * Whether a class (or a Doctrine proxy of it) is one of the bundle's entities.
     */
    public static function isBundleEntity(string $class): bool
    {
        return str_starts_with(self::realClass($class), 'Nowo\\WikiBundle\\Entity\\');
    }

    /**
     * The entity class of a Doctrine proxy class name (`…\\__CG__\\App\\Entity\\X` → `App\\Entity\\X`), or the class itself.
     */
    public static function realClass(string $class): string
    {
        $marker = strrpos($class, '\\__CG__\\');

        return $marker !== false ? substr($class, $marker + 8) : $class;
    }
}
