<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\ValueObject;

use DateTimeImmutable;

/**
 * A page revision as host ports and events see it (1.34.0, REQ-ARCH-001 rules 1 and 3): ids, number, format and
 * author id copied from the bundle's `WikiPageRevision` row (no body). Build one with `Port\WikiSnapshots::revision()`.
 */
final readonly class WikiRevisionSnapshot
{
    /**
     * @param string $id revision id (UUID)
     * @param string $pageId id of the revision's page
     * @param int $revisionNumber revision number (1 for the first save)
     * @param string $format `html` or `markdown`
     * @param string|null $authorId the author's id; null once the author was deleted or without a usable id
     * @param DateTimeImmutable $createdAt save time
     */
    public function __construct(
        public string $id,
        public string $pageId,
        public int $revisionNumber,
        public string $format,
        public ?string $authorId,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
