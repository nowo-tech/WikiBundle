<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\ValueObject;

use DateTimeImmutable;

/**
 * 1.55 (Beacon, REQ-ARCH-001 rules 1 and 3): a page comment as host ports see it — ids, author and times copied from the
 * bundle's `WikiComment` row. The body is left out: render it for a reader with the comment service, which applies the
 * reader's rights to the page links and mentions it holds. Build one with `Port\WikiSnapshots::comment()`.
 */
final readonly class WikiCommentSnapshot
{
    /**
     * @param string $id comment id (UUID)
     * @param string $threadId id of its thread
     * @param string $pageId id of the thread's page
     * @param string|null $authorId the author's id; null once the author was erased or without a usable id
     * @param int $position order inside the thread (0 for the first comment)
     * @param DateTimeImmutable $createdAt when it was written
     * @param DateTimeImmutable|null $editedAt last edit; null when never edited
     * @param DateTimeImmutable|null $deletedAt when it was deleted (the row stays as a placeholder); null otherwise
     */
    public function __construct(
        public string $id,
        public string $threadId,
        public string $pageId,
        public ?string $authorId,
        public int $position,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $editedAt,
        public ?DateTimeImmutable $deletedAt,
    ) {
    }

    /** Whether the comment was deleted. */
    public function isDeleted(): bool
    {
        return $this->deletedAt instanceof DateTimeImmutable;
    }
}
