<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use RuntimeException;

/**
 * A refused collaboration request (1.13), with the HTTP status the endpoint answers (400, 413, 415, 429…).
 */
final class WikiCollabException extends RuntimeException
{
    /**
     * @param int $status HTTP status code
     * @param string $reason short machine reason (`invalid_payload`, `too_large`, `rate_limited`, …)
     */
    public function __construct(
        public readonly int $status,
        public readonly string $reason,
    ) {
        parent::__construct($reason);
    }
}
