<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Query;

use DateTimeImmutable;

/**
 * 1.51: the live pages of one space, from {@see WikiPageQueryInterface::pageStats()}.
 */
final readonly class WikiSpacePageStats
{
    /**
     * @param string $spaceId the space
     * @param int $pages pages that are not archived
     * @param DateTimeImmutable|null $lastUpdatedAt the latest `updatedAt` of those pages; null without any
     */
    public function __construct(
        public string $spaceId,
        public int $pages,
        public ?DateTimeImmutable $lastUpdatedAt,
    ) {
    }
}
