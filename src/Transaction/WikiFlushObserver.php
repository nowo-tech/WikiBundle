<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Transaction;

use Doctrine\ORM\Event\OnFlushEventArgs;

use function str_contains;
use function str_starts_with;

/**
 * 1.37 (audit QN-M1): watches the flushes of one unit of work of {@see WikiTransactionRunner} on the shared entity
 * manager, and records whether one of them wrote an entity of the application (not a wiki row). Such a write is undone
 * by a rollback of the unit while Doctrine keeps it as written, so the runner then closes the manager.
 *
 * One instance per unit, registered on the manager's event manager for the unit only.
 *
 * @internal
 */
final class WikiFlushObserver
{
    /** Namespace of the wiki's entities. */
    private const ENTITY_NAMESPACE = 'Nowo\\WikiBundle\\Entity\\';

    /** A flush of the unit wrote (inserted, updated, deleted, or changed a collection of) an application entity. */
    public bool $hostWritten = false;

    /** Whether a class is one of the wiki's entities (its Doctrine proxy included). */
    public static function isWikiEntity(string $class): bool
    {
        return str_starts_with($class, self::ENTITY_NAMESPACE) || str_contains($class, '\\__CG__\\' . self::ENTITY_NAMESPACE);
    }

    /** Doctrine `onFlush`: what this flush is about to write. */
    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        // The wiki's entities have no to-many associations: a collection change is always the application's.
        if ($unitOfWork->getScheduledCollectionUpdates() !== [] || $unitOfWork->getScheduledCollectionDeletions() !== []) {
            // @igor-ignore - One observer per unit of work, created and dropped by the runner: never a shared service.
            $this->hostWritten = true;

            return;
        }
        $entities = [...$unitOfWork->getScheduledEntityInsertions(), ...$unitOfWork->getScheduledEntityUpdates(), ...$unitOfWork->getScheduledEntityDeletions()];
        foreach ($entities as $entity) {
            if (!self::isWikiEntity($entity::class)) {
                // @igor-ignore - One observer per unit of work, created and dropped by the runner: never a shared service.
                $this->hostWritten = true;

                return;
            }
        }
    }
}
