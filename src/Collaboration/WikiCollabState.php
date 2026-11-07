<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use DateTimeImmutable;

/**
 * The stored Yjs log of a page (1.13). {@see WikiCollabStateStoreInterface::load()} fills the data (snapshot and
 * updates); {@see WikiCollabStateStoreInterface::info()} only the counters, without reading any data.
 */
final readonly class WikiCollabState
{
    /**
     * @param string|null $snapshot base64 full state covering every entry up to {@see self::$snapshotSeq} (load() only)
     * @param list<string> $updates base64 updates after the snapshot, oldest first (load() only)
     * @param int $seq highest sequence number in the log (0 when empty)
     * @param string|null $baseRevisionId revision the log was seeded from (null: never seeded)
     * @param DateTimeImmutable|null $seededAt when a client claimed the seeding
     * @param string|null $seededBy identifier of the user who claimed it
     * @param DateTimeImmutable|null $updatedAt last append, compaction, claim or reset (null: no document yet)
     * @param int $updateCount number of updates after the snapshot
     * @param int $bytes stored bytes (snapshot and updates, base64)
     * @param int $snapshotSeq last sequence number the snapshot covers (0: no snapshot)
     * @param int $savedSeq sequence number when a live session last saved the page (0: not since the log began)
     */
    public function __construct(
        public ?string $snapshot = null,
        public array $updates = [],
        public int $seq = 0,
        public ?string $baseRevisionId = null,
        public ?DateTimeImmutable $seededAt = null,
        public ?string $seededBy = null,
        public ?DateTimeImmutable $updatedAt = null,
        public int $updateCount = 0,
        public int $bytes = 0,
        public int $snapshotSeq = 0,
        public int $savedSeq = 0,
    ) {
    }

    /**
     * Whether the log holds nothing (no snapshot, no update).
     */
    public function isEmpty(): bool
    {
        return $this->snapshotSeq === 0 && $this->updateCount === 0;
    }

    /**
     * Whether the log holds updates no save has taken in: text that exists nowhere else. A log that only holds the seed
     * counts, since the seed update cannot be told apart from text typed with it; that errs towards asking.
     */
    public function hasUnsavedUpdates(): bool
    {
        return !$this->isEmpty() && $this->seq > $this->savedSeq;
    }
}
