<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use DateTimeImmutable;
use Nowo\WikiBundle\Entity\WikiPage;

/**
 * 1.24 (spec 218): a page with its current revision's number, date and author id, read without the revision body.
 */
final readonly class WikiPageMetadata
{
    public function __construct(
        public WikiPage $page,
        public ?int $revisionNumber,
        public ?DateTimeImmutable $revisedAt,
        public ?string $authorId,
    ) {
    }
}
