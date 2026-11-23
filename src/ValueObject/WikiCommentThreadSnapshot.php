<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\ValueObject;

use DateTimeImmutable;

/**
 * 1.55 (Beacon, REQ-ARCH-001 rules 1 and 3): a page comment thread as host ports see it — ids, people and times copied
 * from the bundle's `WikiCommentThread` row (no anchor, no quoted text). Build one with `Port\WikiSnapshots::thread()`.
 */
final readonly class WikiCommentThreadSnapshot
{
    /**
     * @param string $id thread id (UUID)
     * @param string $pageId id of the thread's page
     * @param string|null $createdById who started it; null once that person was erased or without a usable id
     * @param bool $pageLevel whether it is about the whole page (no anchor in the text)
     * @param DateTimeImmutable|null $resolvedAt when it was resolved; null while open
     * @param string|null $resolvedById who resolved it; null while open or once erased
     * @param DateTimeImmutable $createdAt when it was started
     * @param DateTimeImmutable $updatedAt last change of the thread row
     */
    public function __construct(
        public string $id,
        public string $pageId,
        public ?string $createdById,
        public bool $pageLevel,
        public ?DateTimeImmutable $resolvedAt,
        public ?string $resolvedById,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /** Whether the thread is resolved. */
    public function isResolved(): bool
    {
        return $this->resolvedAt instanceof DateTimeImmutable;
    }
}
