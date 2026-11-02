<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use DateTimeImmutable;

/**
 * The stored Yjs log of a page (1.13): an optional snapshot, the updates after it (base64, in order), the highest
 * sequence number, the revision the log started from and when it last changed.
 */
final readonly class WikiCollabState
{
    /**
     * @param string|null $snapshot base64 full state covering every entry up to its sequence number
     * @param list<string> $updates base64 updates after the snapshot, oldest first
     * @param int $seq highest sequence number in the log (0 when empty)
     * @param string|null $baseRevisionId revision the log was seeded from (null: never seeded)
     * @param bool $seeded whether a client was already asked to seed it
     * @param DateTimeImmutable|null $updatedAt last append, compaction or reset
     */
    public function __construct(
        public ?string $snapshot = null,
        public array $updates = [],
        public int $seq = 0,
        public ?string $baseRevisionId = null,
        public bool $seeded = false,
        public ?DateTimeImmutable $updatedAt = null,
    ) {
    }

    /**
     * Whether the log holds nothing.
     */
    public function isEmpty(): bool
    {
        return $this->snapshot === null && $this->updates === [];
    }
}
