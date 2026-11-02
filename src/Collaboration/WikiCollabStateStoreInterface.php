<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use DateTimeImmutable;

/**
 * Storage of the Yjs log of each page (1.13, FR-076 "stored Y state"). The server never decodes Yjs: it keeps what the
 * clients post, in order, and hands it back on (re)connect; clients compact it by posting a full-state snapshot.
 *
 * Every method must be safe under concurrent requests: {@see self::append()} must never lose an update and
 * {@see self::claimSeed()} must answer true to one caller only.
 */
interface WikiCollabStateStoreInterface
{
    /**
     * Reads the log of a page (an empty state when there is none).
     */
    public function load(string $pageId): WikiCollabState;

    /**
     * Appends one base64 update and returns its sequence number (increasing).
     */
    public function append(string $pageId, string $update): int;

    /**
     * Replaces every entry up to `$upToSeq` with the base64 full-state snapshot.
     */
    public function compact(string $pageId, string $snapshot, int $upToSeq): void;

    /**
     * Marks the log as being seeded; true for one caller only — the first after a reset, or the first after a claim
     * older than `$staleBefore` (the client that claimed it left without seeding).
     */
    public function claimSeed(string $pageId, DateTimeImmutable $staleBefore): bool;

    /**
     * Drops the log and records the revision a new log starts from.
     */
    public function reset(string $pageId, ?string $baseRevisionId): void;

    /**
     * Records that the collaborative document was saved as this revision (the log stays).
     */
    public function markSaved(string $pageId, string $revisionId): void;
}
