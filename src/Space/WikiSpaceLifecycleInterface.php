<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Space;

use InvalidArgumentException;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\WikiBundle\Event\WikiSpaceAccessCheckEvent;
use Nowo\WikiBundle\Service\WikiInvalidSlugException;
use Nowo\WikiBundle\Service\WikiSlugTakenException;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * 1.50: rename, archive, unarchive and purge a space (public service, SemVer). Framework-free: ids in, snapshots out.
 *
 * - **Who**: with an `$actor`, the person must open the space (else {@see WikiSpaceNotFoundException}, the answer of a
 *   missing space, so another tenant's space reads as unknown) and hold `Manage` on it through the space checker, which
 *   a {@see WikiSpaceAccessCheckEvent} listener may override (else
 *   {@see WikiSpaceAccessDeniedException}). A null actor is a trusted call that checks nothing (a console command, a
 *   message handler of the host): pass the person whenever there is one.
 * - **Optimistic locking**: `$expectedVersion` (the snapshot's `version`) is compared under the space row lock; another
 *   change since then answers {@see WikiSpaceVersionConflictException}. Null: last write wins.
 * - **Concurrency** (REQ-RUNTIME-003): each change is one transaction of the wiki's runner under the space lock. A lock
 *   conflict surfaces as {@see TransientStorageFailure} (nothing written: 409 + `Retry-After`, `CONFLICT_001`); a purge
 *   that already committed part of its work surfaces as a `ConcurrencyConflict` with `committed: true`
 *   (`CONFLICT_002`: read again, then call the purge again to resume). The host's entity manager is never cleared,
 *   reset or closed by these calls.
 * - **Events** are dispatched after the change committed.
 */
interface WikiSpaceLifecycleInterface
{
    /** Pages deleted per purge batch (one transaction each). */
    public const DEFAULT_BATCH_SIZE = 100;

    /**
     * Changes the name and/or the slug (null: unchanged). The slug stays unique per owner. Links between pages follow
     * the page ids and keep working; URLs built with the old slug stop resolving (the bundle keeps no slug history).
     * Equal values write nothing and dispatch nothing.
     *
     * @throws WikiSpaceAccessDeniedException|WikiSpaceNotFoundException|WikiSpaceVersionConflictException
     * @throws WikiSpaceStateException when the space is being purged
     * @throws InvalidArgumentException when nothing is asked or the name is empty or longer than 255 characters
     * @throws WikiInvalidSlugException when the slug is not lowercase letters, digits and single dashes (max 120)
     * @throws WikiSlugTakenException when another space of the owner has the slug
     * @throws TransientStorageFailure on a lock conflict (nothing written)
     */
    public function renameSpace(string $spaceId, ?string $name, ?string $slug = null, ?WikiUserRef $actor = null, ?int $expectedVersion = null): WikiSpaceSnapshot;

    /**
     * Archives the space: read-only for everyone, hidden from navigation and search, open only to the people who may
     * manage it. Reversible. Archiving an archived space writes nothing.
     *
     * @throws WikiSpaceAccessDeniedException|WikiSpaceNotFoundException|WikiSpaceVersionConflictException
     * @throws TransientStorageFailure on a lock conflict (nothing written)
     */
    public function archiveSpace(string $spaceId, ?WikiUserRef $actor = null, ?int $expectedVersion = null): WikiSpaceSnapshot;

    /**
     * Brings an archived space back. Unarchiving an active space writes nothing.
     *
     * @throws WikiSpaceAccessDeniedException|WikiSpaceNotFoundException|WikiSpaceVersionConflictException
     * @throws WikiSpaceStateException when the space is being purged
     * @throws TransientStorageFailure on a lock conflict (nothing written)
     */
    public function unarchiveSpace(string $spaceId, ?WikiUserRef $actor = null, ?int $expectedVersion = null): WikiSpaceSnapshot;

    /**
     * Deletes the space for good with its pages, revisions, attachments (files removed after each commit), comments,
     * links, favourites, visits, references (to its pages too) and co-editing state — in batches of
     * {@see self::DEFAULT_BATCH_SIZE} pages, one transaction each, so a large space never holds one long lock. Irreversible.
     *
     * Only an archived space, unless `$force` (the host's administrator flag) — a forced purge archives it first. The
     * first call marks the space as being purged; a call that stops (a failure, or `$maxBatches` reached) leaves it
     * archived and marked, and calling again resumes where it stopped (idempotent: a batch commits or leaves nothing).
     *
     * @param int|null $maxBatches batches this call commits at most (null: until done); a host running the purge from a
     *                             queue passes a budget and calls again while the result is not finished
     *
     * @throws WikiSpaceNotFoundException when the space does not exist (an already finished purge included)
     * @throws WikiSpaceAccessDeniedException|WikiSpaceStateException
     * @throws TransientStorageFailure on a lock conflict: `ConcurrencyConflict::$committed` true when this call already
     *                                 committed part of the purge
     */
    public function purgeSpace(string $spaceId, ?WikiUserRef $actor = null, bool $force = false, ?int $maxBatches = null): WikiSpacePurgeResult;
}
