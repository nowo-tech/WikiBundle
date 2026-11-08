<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use DateTimeImmutable;

/**
 * 1.24 (spec 218): which pages of a space {@see WikiPageMetadataFinderInterface::pageMetadata()} reads.
 */
final readonly class WikiPageMetadataQuery
{
    /**
     * @param bool $archived archived pages instead of active ones
     * @param string|null $parentId only the children of this page
     * @param bool $topLevel only pages without a parent (ignored with `$parentId`)
     * @param DateTimeImmutable|null $updatedSince only pages changed at or after this time
     */
    public function __construct(
        public bool $archived = false,
        public ?string $parentId = null,
        public bool $topLevel = false,
        public ?DateTimeImmutable $updatedSince = null,
    ) {
    }
}
