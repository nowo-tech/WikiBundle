<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Value;

/**
 * 1.24 (spec 218): the answer to a claim; with {@see WikiApiIdempotencyOutcome::Completed}, the first answer and what
 * it touched (for the replay's access check).
 */
final readonly class WikiApiIdempotencyClaim
{
    public function __construct(
        public WikiApiIdempotencyOutcome $outcome,
        public ?int $status = null,
        public ?string $body = null,
        public ?string $wikiSlug = null,
        public ?string $pageId = null,
    ) {
    }
}
