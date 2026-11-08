<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Hook;

use Nowo\WikiBundle\Api\Value\WikiApiRateLimitState;
use Nowo\WikiBundle\Api\WikiApiPrincipal;

/**
 * 1.24 (spec 218): the default limiter: nothing passes until the host sets `api.hooks.rate_limiter`.
 */
final readonly class DenyingWikiApiRateLimiter implements WikiApiRateLimiterInterface
{
    public function consume(WikiApiPrincipal $principal): WikiApiRateLimitState
    {
        return new WikiApiRateLimitState(false, 0, 0, 0);
    }

    public function failedTokenState(string $clientIp): WikiApiRateLimitState
    {
        return new WikiApiRateLimitState(false, 0, 0, 0);
    }

    public function registerFailedToken(string $clientIp): void
    {
    }
}
