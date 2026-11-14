<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Util;

use function is_string;
use function mb_substr;
use function preg_replace;
use function trim;

/**
 * Spec 234: one line of plain text from a host value — control and format characters removed, whitespace runs
 * collapsed to one space, trimmed and cut to a maximum number of characters. Null for an empty result or a value that is
 * not valid UTF-8. The value is never HTML: every output escapes it (Twig autoescape, `json_encode`).
 */
final class WikiPlainLabel
{
    /**
     * @param string|null $value the host's value
     * @param int $max the maximum number of characters kept (longer values are truncated)
     */
    public static function normalize(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $line = preg_replace(['/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]+/u', '/\s+/u'], [' ', ' '], $value);
        if (!is_string($line)) {
            return null;
        }
        $line = trim(mb_substr(trim($line), 0, $max));

        return $line === '' ? null : $line;
    }
}
