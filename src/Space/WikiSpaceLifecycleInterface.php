<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Space;

use InvalidArgumentException;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiSpaceAccessCheckEvent;
use Nowo\WikiBundle\Service\WikiInvalidSlugException;
use Nowo\WikiBundle\Service\WikiSlugTakenException;
use Nowo\WikiBundle\Util\WikiSpaceSlugRule;
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
     * @throws WikiSpaceStateException when the space is archived (bring it back first, as the REST API asks) or being purged
     * @throws InvalidArgumentException when nothing is asked or the name is empty or longer than 255 characters
     * @throws WikiInvalidSlugException when the slug is not lowercase letters, digits and single dashes (max 120) — or,
     *                                  1.55, a slug a configured `tenant_paths` requirement matches ({@see WikiSpaceSlugRule})
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
     * Call it outside any transaction (audit XO-1): each batch commits on its own, then its files go and its events fire.
     * A second call that finds the space already gone returns `finished: true` without dispatching `SPACE_PURGED`; only
     * the call that deleted the space row announces it. Files whose unlink failed, or that a crash left behind, stay in
     * the attachment storage: run `WikiAttachmentService::purgeStrayFiles()` from a nightly job.
     *
     * @throws WikiSpacePurgeInTransactionException when a transaction is open on the wiki's connection (nothing written)
     * @throws WikiSpaceNotFoundException when the space does not exist (an already finished purge included)
     * @throws WikiSpaceAccessDeniedException|WikiSpaceStateException
     * @throws TransientStorageFailure on a lock conflict: `ConcurrencyConflict::$committed` true when this call already
     *                                 committed part of the purge
     */
    public function purgeSpace(string $spaceId, ?WikiUserRef $actor = null, bool $force = false, ?int $maxBatches = null): WikiSpacePurgeResult;

    /**
     * 1.55 (Beacon spec 209, account erasure): {@see self::purgeSpace()} as a trusted, forced call that is idempotent —
     * a space that is already gone (an earlier call finished, or there never was one) answers `finished: true`,
     * `found: false` ({@see WikiSpacePurgeResult::alreadyGone()}) with no page and no batch instead of
     * {@see WikiSpaceNotFoundException}, so a retried erasure or a replayed message has nothing to catch. Everything else
     * is the purge: an active space is archived first (search, navigation, favourites and recents hide it from that first
     * unit on), then its pages, revisions, attachments (rows, then files through the attachment storage after each
     * commit), comments, links, references, favourites, visits, trash entries, co-editing state and the space row go,
     * one transaction per batch. Search has no index of its own: it reads the page rows, so nothing of the space is found
     * once its pages are gone.
     *
     * - **Canonical id only** (1.55.1, audit ZAP-1): `$spaceId` must be a lowercase UUID, as the bundle generates them.
     *   Anything else (an upper-cased, padded or truncated id) is refused, so "already gone" never answers an id that only
     *   looks like a stored one while the space stays.
     * - **Expected owner** (1.55.1, audit ZAP-2): give `$expectedOwnerType` and `$expectedOwnerId` (both or none). They
     *   are compared byte for byte with the space's owner under the space lock of the first unit; another owner answers
     *   {@see WikiSpaceOwnerMismatchException} and nothing is written. An erasure always knows whose data it erases:
     *   pass them on every call.
     * - **Files** (1.55.1, audit ZAP-3): the rows are gone when `finished` is true, files may not be. Run
     *   `WikiAttachmentService::purgeStrayFiles()` when `filesFailed` > 0 (a storage delete failed and was logged), and
     *   the host's own page-file sweep when `filesTruncated` > 0 (`page_files.max_purge_files` cut the
     *   `WikiPageFilesPurgedEvent` list) — {@see WikiSpacePurgeResult::needsFileSweep()}. A process that dies between a
     *   batch commit and its file deletes cannot report them: keep `purgeStrayFiles()` in a nightly job as well.
     *
     * Runs in the wiki's own units, after your erasure committed: call it outside any transaction (from a listener of
     * your "erasure committed" event or a message handler); inside one it throws before writing anything. A call that
     * stops (failure, `$maxBatches`) resumes on the next call.
     *
     * @param int|null $maxBatches batches this call commits at most (null: until done)
     * @param WikiSpaceOwnerScope|null $expectedOwnerType 1.55.1: the owner kind the space must have (with the id)
     * @param string|null $expectedOwnerId 1.55.1: the owner id the space must have, byte for byte (with the type)
     *
     * @throws InvalidArgumentException when the id is not a lowercase UUID, or only one of the expected owner values is given
     * @throws WikiSpaceOwnerMismatchException when the space has another owner (nothing written)
     * @throws WikiSpacePurgeInTransactionException when a transaction is open on the wiki's connection (nothing written)
     * @throws TransientStorageFailure on a lock conflict: `ConcurrencyConflict::$committed` true when this call already
     *                                 committed part of the purge (call again to resume)
     */
    public function eraseSpace(string $spaceId, ?int $maxBatches = null, ?WikiSpaceOwnerScope $expectedOwnerType = null, ?string $expectedOwnerId = null): WikiSpacePurgeResult;
}
