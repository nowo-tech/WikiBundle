<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\ValueObject;

use DateTimeImmutable;

/**
 * A wiki page as host ports and events see it (1.32.0, REQ-ARCH-001 rules 1 and 3): ids and plain values copied from
 * the bundle's `WikiPage` row when the port is called (no entity, no revision body). Build one with
 * `Port\WikiSnapshots::page()`.
 */
final readonly class WikiPageSnapshot
{
    /**
     * @param string $id page id (UUID)
     * @param string $spaceId id of the page's space
     * @param string $slug page slug, unique in its space
     * @param string $title page title
     * @param string|null $parentId id of the parent page; null at the root
     * @param int $position position among its siblings
     * @param string|null $currentRevisionId id of the current revision; null before the first save
     * @param DateTimeImmutable|null $archivedAt archive time; null while active
     * @param DateTimeImmutable $createdAt creation time
     * @param DateTimeImmutable $updatedAt last change of the page row
     */
    public function __construct(
        public string $id,
        public string $spaceId,
        public string $slug,
        public string $title,
        public ?string $parentId,
        public int $position,
        public ?string $currentRevisionId,
        public ?DateTimeImmutable $archivedAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * Whether the page is archived.
     *
     * @return bool true when {@see self::$archivedAt} is set
     */
    public function isArchived(): bool
    {
        return $this->archivedAt instanceof DateTimeImmutable;
    }
}
