<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Space;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use InvalidArgumentException;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\TransientFailureClassifier;
use Nowo\TransactionDoctrine\TransientFailureKind;
use Nowo\TransactionDoctrine\WriteUnit\ConcurrencyConflict;
use Nowo\WikiBundle\Attachment\WikiAttachmentStorageInterface;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiAttachmentEvent;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiPageFilesPurgedEvent;
use Nowo\WikiBundle\Event\WikiSpaceAccessCheckEvent;
use Nowo\WikiBundle\Event\WikiSpaceLifecycleEvent;
use Nowo\WikiBundle\Event\WikiSpacePurgedEvent;
use Nowo\WikiBundle\Port\Internal\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Port\WikiSnapshots;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Service\WikiEventDeferral;
use Nowo\WikiBundle\Service\WikiInvalidSlugException;
use Nowo\WikiBundle\Service\WikiSlugTakenException;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use Nowo\WikiBundle\Util\WikiSlugger;
use Nowo\WikiBundle\Util\WikiSpaceSlugRule;
use Nowo\WikiBundle\ValueObject\Uuid;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

use function count;
use function max;
use function mb_strlen;
use function preg_match;
use function trim;

/**
 * 1.50: {@see WikiSpaceLifecycleInterface} on the wiki's transaction runner. Stateless: one instance serves every request
 * of a long-running worker (kernel reset off).
 */
final readonly class WikiSpaceLifecycle implements WikiSpaceLifecycleInterface
{
    /** A space id as the bundle generates it (a lowercase UUID). */
    private const SPACE_ID = '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/';

    /** Longest space name (the column). */
    private const MAX_NAME_LENGTH = 255;

    /**
     * @param WikiTransactionRunner $runner the wiki's runner
     * @param WikiSpaceRepositoryInterface $spaces space lookups
     * @param WikiSpacePurger $purger one purge batch per call
     * @param WikiSlugger $slugger slug rules
     * @param WikiSpaceAccessCheckerInterface $checker `View` and `Manage` on the space
     * @param EventDispatcherInterface $dispatcher access check and lifecycle events
     * @param WikiEventDeferral|null $deferral holds the events of a call inside a wiki scope until it committed
     * @param WikiAttachmentStorageInterface|null $storage removes the purged attachments' files after each commit
     * @param LoggerInterface $logger the lifecycle's log (ids only, never names)
     * @param int $batchSize pages per purge batch
     * @param WikiSpaceSlugRule|null $slugRule 1.55: the space slug rule (tenant-path slugs); null: the bundle's rule only
     */
    public function __construct(
        private WikiTransactionRunner $runner,
        private WikiSpaceRepositoryInterface $spaces,
        private WikiSpacePurger $purger,
        private WikiSlugger $slugger,
        private WikiSpaceAccessCheckerInterface $checker,
        private EventDispatcherInterface $dispatcher,
        private ?WikiEventDeferral $deferral = null,
        private ?WikiAttachmentStorageInterface $storage = null,
        private LoggerInterface $logger = new NullLogger(),
        private int $batchSize = self::DEFAULT_BATCH_SIZE,
        private ?WikiSpaceSlugRule $slugRule = null,
    ) {
    }

    public function renameSpace(string $spaceId, ?string $name, ?string $slug = null, ?WikiUserRef $actor = null, ?int $expectedVersion = null): WikiSpaceSnapshot
    {
        if ($name === null && $slug === null) {
            throw new InvalidArgumentException('Nothing to rename: give a name or a slug.');
        }
        if ($name !== null) {
            $name = trim($name);
            if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
                throw new InvalidArgumentException('Invalid space name: 1 to 255 characters.');
            }
        }
        if ($slug !== null && !($this->slugRule ?? new WikiSpaceSlugRule($this->slugger))->isValid($slug)) {
            throw new WikiInvalidSlugException('Invalid space slug.');
        }
        $this->authorize($spaceId, $actor, WikiSpaceLifecycleAction::Rename);

        try {
            // @igor-ignore - The closure runs at once inside the unit of work and is not kept: the runner holds no state.
            [$before, $after] = $this->runner->run(function () use ($spaceId, $name, $slug, $expectedVersion): array {
                $space = $this->lockedSpace($spaceId);
                if ($space->getPurgeStartedAt() instanceof DateTimeImmutable) {
                    throw new WikiSpaceStateException(WikiSpaceStateException::PURGING);
                }
                if ($space->isArchived()) {
                    // Aligned with the REST API, which refuses a wiki update while it is archived (WIKI_002).
                    throw new WikiSpaceStateException(WikiSpaceStateException::ARCHIVED);
                }
                $this->assertVersion($space, $expectedVersion);
                $before  = WikiSnapshots::space($space);
                $changed = false;
                if ($name !== null && $name !== $space->getName()) {
                    // @igor-ignore - The space row of this call (a Doctrine entity), not service state.
                    $space->setName($name);
                    $changed = true;
                }
                if ($slug !== null && $slug !== $space->getSlug()) {
                    $taken = $this->spaces->findBySlug($space->getOwnerScopeType(), $space->getOwnerScopeId(), $slug);
                    if ($taken instanceof WikiSpace && $taken->getId() !== $space->getId()) {
                        throw new WikiSlugTakenException('Space slug already exists for this owner.');
                    }
                    // The slug first, by statement: a slug another request took meanwhile fails here on the unique key,
                    // never in a flush (a failed flush would close a shared entity manager).
                    $this->runner->manager()->createQuery('UPDATE ' . WikiSpace::class . ' s SET s.slug = :slug WHERE s.id = :id')
                        ->setParameter('slug', $slug)
                        ->setParameter('id', $space->getId())
                        ->execute();
                    $space->changeSlug($slug);
                    $changed = true;
                }
                if (!$changed) {
                    return [$before, null];
                }
                $space->bumpVersion();

                return [$before, WikiSnapshots::space($space)];
            });
        } catch (UniqueConstraintViolationException $exception) { // @phpstan-ignore catch.neverThrown (thrown by the slug statement inside the unit)
            // Another rename or create took the slug between the check and the commit (nothing written).
            throw new WikiSlugTakenException('Space slug already exists for this owner.', 0, $exception);
        }

        return $this->announce(WikiSpaceLifecycleAction::Rename, WikiEvents::SPACE_RENAMED, $before, $after, $actor);
    }

    public function archiveSpace(string $spaceId, ?WikiUserRef $actor = null, ?int $expectedVersion = null): WikiSpaceSnapshot
    {
        return $this->changeArchived($spaceId, true, $actor, $expectedVersion);
    }

    public function unarchiveSpace(string $spaceId, ?WikiUserRef $actor = null, ?int $expectedVersion = null): WikiSpaceSnapshot
    {
        return $this->changeArchived($spaceId, false, $actor, $expectedVersion);
    }

    public function purgeSpace(string $spaceId, ?WikiUserRef $actor = null, bool $force = false, ?int $maxBatches = null): WikiSpacePurgeResult
    {
        return $this->purge($spaceId, $actor, $force, $maxBatches, null, null);
    }

    public function eraseSpace(string $spaceId, ?int $maxBatches = null, ?WikiSpaceOwnerScope $expectedOwnerType = null, ?string $expectedOwnerId = null): WikiSpacePurgeResult
    {
        // Audit ZAP-1: only a canonical id can be answered "already gone"; a look-alike (upper case, padded, truncated)
        // could match the row on a case-insensitive collation and never be erased.
        if (preg_match(self::SPACE_ID, $spaceId) !== 1) {
            throw new InvalidArgumentException('Invalid space id: expected a lowercase UUID.');
        }
        if ((!$expectedOwnerType instanceof WikiSpaceOwnerScope) !== ($expectedOwnerId === null)) {
            throw new InvalidArgumentException('Give the expected owner type and id together.');
        }
        try {
            return $this->purge($spaceId, null, true, $maxBatches, $expectedOwnerType, $expectedOwnerId);
        } catch (WikiSpaceNotFoundException) {
            // Already gone (an earlier erasure finished, or another call deleted the row meanwhile): nothing left to do.
            return new WikiSpacePurgeResult($spaceId, true, 0, 0, found: false);
        }
    }

    /**
     * The purge; with an expected owner (audit ZAP-2) the owner is compared byte for byte under the space lock of the
     * first unit, before anything is written.
     *
     * @throws WikiSpaceOwnerMismatchException when the space has another owner (nothing written)
     * @throws WikiSpaceNotFoundException when the space does not exist (an already finished purge included)
     */
    private function purge(string $spaceId, ?WikiUserRef $actor, bool $force, ?int $maxBatches, ?WikiSpaceOwnerScope $ownerType, ?string $ownerId): WikiSpacePurgeResult
    {
        // Audit XO-1: inside a transaction each batch would be a savepoint, yet its files go and its events fire at once.
        if ($this->runner->manager()->getConnection()->isTransactionActive()) {
            throw new WikiSpacePurgeInTransactionException();
        }
        $this->authorize($spaceId, $actor, WikiSpaceLifecycleAction::Purge);

        // @igor-ignore - The closure runs at once inside the unit of work and is not kept: the runner holds no state.
        [$space, $started] = $this->runner->run(function () use ($spaceId, $force, $ownerType, $ownerId): array {
            $space = $this->lockedSpace($spaceId);
            if ($ownerType instanceof WikiSpaceOwnerScope && ($space->getOwnerScopeType() !== $ownerType || $space->getOwnerScopeId() !== $ownerId)) {
                throw new WikiSpaceOwnerMismatchException();
            }
            if ($space->getPurgeStartedAt() instanceof DateTimeImmutable) {
                return [WikiSnapshots::space($space), false];
            }
            if (!$space->isArchived() && !$force) {
                throw new WikiSpaceStateException(WikiSpaceStateException::NOT_ARCHIVED);
            }
            $space->startPurge();
            $space->bumpVersion();

            return [WikiSnapshots::space($space), true];
        });
        if ($started) {
            $this->logger->info('Wiki space purge started.', ['bundle' => 'nowo_wiki', 'action' => 'space_purge', 'space_id' => $spaceId]);
        }

        $pages     = 0;
        $batches   = 0;
        $failed    = 0;
        $truncated = 0;
        while ($maxBatches === null || $batches < max(0, $maxBatches)) {
            try {
                $batch = $this->purger->batch($spaceId, max(1, $this->batchSize));
            } catch (TransientStorageFailure $failure) {
                if (!$started && $batches === 0) {
                    throw $failure; // nothing of this call committed: retry-safe
                }

                // Part of the purge is committed: read again, then call again to resume (never a blind replay).
                throw new ConcurrencyConflict(1, (new TransientFailureClassifier())->classify($failure) ?? TransientFailureKind::Retryable, null, $failure, true);
            }
            ++$batches;
            $pages += count($batch->pageIds);
            $failed += $this->afterBatch($batch, $actor);
            $truncated += $batch->truncated ? 1 : 0;
            if ($batch->finished && !$batch->spaceDeleted) {
                // Audit XO-2: another call deleted the space row meanwhile; it announces the purge, not this one.
                return new WikiSpacePurgeResult($spaceId, true, $pages, $batches, true, $failed, $truncated);
            }
            if ($batch->finished) {
                $this->logger->info('Wiki space purged.', ['bundle' => 'nowo_wiki', 'action' => 'space_purge', 'space_id' => $spaceId, 'pages' => $pages]);
                $this->dispatch(new WikiSpacePurgedEvent($space, $pages, $actor, new DateTimeImmutable()), WikiEvents::SPACE_PURGED);

                return new WikiSpacePurgeResult($spaceId, true, $pages, $batches, true, $failed, $truncated);
            }
        }

        return new WikiSpacePurgeResult($spaceId, false, $pages, $batches, true, $failed, $truncated);
    }

    private function changeArchived(string $spaceId, bool $archived, ?WikiUserRef $actor, ?int $expectedVersion): WikiSpaceSnapshot
    {
        $action = $archived ? WikiSpaceLifecycleAction::Archive : WikiSpaceLifecycleAction::Unarchive;
        $this->authorize($spaceId, $actor, $action);

        // @igor-ignore - The closure runs at once inside the unit of work and is not kept: the runner holds no state.
        [$before, $after] = $this->runner->run(function () use ($spaceId, $archived, $expectedVersion): array {
            $space = $this->lockedSpace($spaceId);
            if (!$archived && $space->getPurgeStartedAt() instanceof DateTimeImmutable) {
                throw new WikiSpaceStateException(WikiSpaceStateException::PURGING);
            }
            $before = WikiSnapshots::space($space);
            if ($space->isArchived() === $archived) {
                return [$before, null]; // already so: a repeated call (a retry after a lost answer) writes nothing
            }
            $this->assertVersion($space, $expectedVersion);
            $space->changeArchived($archived);
            $space->bumpVersion();

            return [$before, WikiSnapshots::space($space)];
        });

        return $this->announce($action, $archived ? WikiEvents::SPACE_ARCHIVED : WikiEvents::SPACE_UNARCHIVED, $before, $after, $actor);
    }

    /**
     * The person's right to the action: the space must exist and open for them (else the answer of a missing space),
     * then `Manage` through the checker, overridable by a {@see WikiEvents::SPACE_ACCESS_CHECK} listener.
     *
     * @throws WikiSpaceAccessDeniedException|WikiSpaceNotFoundException
     */
    private function authorize(string $spaceId, ?WikiUserRef $actor, WikiSpaceLifecycleAction $action): void
    {
        $space = $this->spaces->findById($spaceId);
        if (!$space instanceof WikiSpace || $space->getId() !== $spaceId) {
            throw new WikiSpaceNotFoundException();
        }
        if (!$actor instanceof WikiUserRef) {
            return;
        }
        $user = WikiUserRefs::securityUser($actor);
        if (!$this->checker->isGranted($user, WikiAction::View, $space)) {
            throw new WikiSpaceNotFoundException();
        }
        $event = new WikiSpaceAccessCheckEvent($action, WikiSnapshots::space($space), $actor, $this->checker->isGranted($user, WikiAction::Manage, $space));
        $this->dispatcher->dispatch($event, WikiEvents::SPACE_ACCESS_CHECK);
        if (!$event->isGranted()) {
            throw new WikiSpaceAccessDeniedException($action);
        }
    }

    /**
     * Inside a unit: the space row locked and read again under the lock (the latest committed state).
     *
     * @throws WikiSpaceNotFoundException when it is gone
     */
    private function lockedSpace(string $spaceId): WikiSpace
    {
        $space = $this->spaces->findById($spaceId);
        if (!$space instanceof WikiSpace || $space->getId() !== $spaceId || !$this->runner->lockEntity($space)) {
            throw new WikiSpaceNotFoundException();
        }

        return $space;
    }

    /** @throws WikiSpaceVersionConflictException */
    private function assertVersion(WikiSpace $space, ?int $expectedVersion): void
    {
        if ($expectedVersion !== null && $space->getVersion() !== $expectedVersion) {
            throw new WikiSpaceVersionConflictException($expectedVersion, $space->getVersion());
        }
    }

    /** After a committed change: the log line and the event; the snapshot the caller gets. */
    private function announce(WikiSpaceLifecycleAction $action, string $name, WikiSpaceSnapshot $before, ?WikiSpaceSnapshot $after, ?WikiUserRef $actor): WikiSpaceSnapshot
    {
        if (!$after instanceof WikiSpaceSnapshot) {
            return $before;
        }
        $this->logger->info('Wiki space changed.', ['bundle' => 'nowo_wiki', 'action' => 'space_' . $action->value, 'space_id' => $after->id, 'version' => $after->version]);
        $this->dispatch(new WikiSpaceLifecycleEvent($action, $after, $before, $actor, new DateTimeImmutable()), $name);

        return $after;
    }

    /**
     * After a committed batch: the attachment files go (a failure is logged and leaves an orphan file for the attachment
     * maintenance), then the attachment and page-file events.
     *
     * @return int attachment files whose delete failed
     */
    private function afterBatch(WikiSpacePurgeBatch $batch, ?WikiUserRef $actor): int
    {
        $user   = $actor instanceof WikiUserRef ? WikiUserRefs::object($actor) : null;
        $failed = 0;
        foreach ($batch->attachments as $attachment) {
            try {
                $this->storage?->delete($attachment->getStorageKey());
            } catch (Throwable $exception) {
                ++$failed;
                $this->logger->warning('Wiki space purge: an attachment file could not be deleted.', ['bundle' => 'nowo_wiki', 'action' => 'space_purge', 'exception_class' => $exception::class]);
            }
            $this->dispatch(new WikiAttachmentEvent($attachment, $user), WikiEvents::ATTACHMENT_DELETED);
        }
        if ($batch->files !== [] && $batch->space instanceof WikiSpace) {
            // `page_files.purge_event`: the host deletes the files it stores for these pages (untrusted URLs, see the event).
            $this->dispatch(new WikiPageFilesPurgedEvent($batch->space, 'space-purge:' . Uuid::generate()->toString(), $batch->pageIds, $batch->files, $user, $batch->truncated), WikiEvents::PAGE_FILES_PURGED);
        }

        return $failed;
    }

    private function dispatch(object $event, string $name): void
    {
        if ($this->deferral instanceof WikiEventDeferral) {
            $this->deferral->dispatch($this->dispatcher, $event, $name);

            return;
        }
        $this->dispatcher->dispatch($event, $name);
    }
}
