<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Hook;

use Nowo\WikiBundle\Api\Value\WikiApiRateLimitState;
use Nowo\WikiBundle\Api\WikiApiPrincipal;

/**
 * 1.24 (spec 218): the host's limits. One bucket per token, shared with the host's other APIs (Beacon Q-B5), and a
 * failed-token limit per client IP.
 */
interface WikiApiRateLimiterInterface
{
    public function consume(WikiApiPrincipal $principal): WikiApiRateLimitState;

    /** Peeked before authenticating: refused means 429 `RATE_002` without asking the authenticator. */
    public function failedTokenState(string $clientIp): WikiApiRateLimitState;

    public function registerFailedToken(string $clientIp): void;
}
