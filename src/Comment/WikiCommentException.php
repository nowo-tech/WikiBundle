<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Comment;

use RuntimeException;

/**
 * A refused comment request (1.16): the HTTP status the endpoint answers (403, 404, 409, 413, 422, 429) and a
 * machine-readable reason (`forbidden`, `not_found`, `stale`, `too_large`, `invalid`, `rate_limited`).
 */
final class WikiCommentException extends RuntimeException
{
    public function __construct(public readonly int $status, public readonly string $reason)
    {
        parent::__construct($reason, $status);
    }
}
