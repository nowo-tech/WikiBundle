<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api;

use Symfony\Component\HttpFoundation\Request;

use function is_string;
use function preg_replace;
use function trim;

/**
 * 1.24 (spec 218): the credential of a request. The `Authorization` header only, with or without `Bearer`; never the
 * query string, where it would reach logs.
 */
final class WikiApiCredential
{
    public static function fromRequest(Request $request): ?string
    {
        $header = $request->headers->get('Authorization');
        if (!is_string($header)) {
            return null;
        }
        $credential = trim((string) preg_replace('/^Bearer(\s+|$)/i', '', trim($header)));

        return $credential === '' ? null : $credential;
    }
}
