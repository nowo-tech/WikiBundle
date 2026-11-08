<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api;

use RuntimeException;

/**
 * 1.24 (spec 218): a request refused, with what the client sees: `{"err": message, "ECODE": ecode}` plus `$extra`. The
 * message goes to the client as it is, so it names no id or person.
 */
final class WikiApiRefusal extends RuntimeException
{
    /**
     * @param array<string, mixed> $extra more fields of the body (a conflict's current revision)
     * @param array<string, string> $headers headers of the answer (`Retry-After`)
     * @param bool $afterCommit the write was stored before this refusal: its idempotent claim is kept, so a retry with the
     *                          same key does not run it again (audit IF-L8)
     */
    public function __construct(
        public readonly int $status,
        public readonly string $ecode,
        string $message,
        public readonly array $extra = [],
        public readonly array $headers = [],
        public readonly bool $afterCommit = false,
    ) {
        parent::__construct($message);
    }

    public static function notFound(): self
    {
        return new self(404, 'ACCESS_001', 'Not found');
    }

    public static function levelRefused(): self
    {
        return new self(403, 'TOKEN_001', 'This token may not do this');
    }

    public static function permissionRefused(): self
    {
        return new self(403, 'ACCESS_002', 'You may not do this');
    }

    public static function planRefused(): self
    {
        return new self(403, 'PLAN_001', 'The wiki module is not in this plan');
    }

    public static function archivedWiki(): self
    {
        return new self(403, 'WIKI_002', 'This wiki is archived');
    }

    /** A server error after the write was stored (a host bug): 500, claim kept. */
    public static function serverErrorAfterCommit(): self
    {
        return new self(500, 'SERVER_001', 'Server error', [], [], true);
    }

    public static function invalid(string $field): self
    {
        return new self(400, 'INPUT_002', 'Invalid field: ' . $field);
    }
}
