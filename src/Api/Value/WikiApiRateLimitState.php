<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Value;

/**
 * 1.24 (spec 218): a rate-limit answer, sent as `X-RateLimit-Limit`, `-Remaining` and `-Reset` (Unix seconds).
 */
final readonly class WikiApiRateLimitState
{
    public function __construct(
        public bool $accepted,
        public int $limit,
        public int $remaining,
        public int $resetAt,
    ) {
    }
}
