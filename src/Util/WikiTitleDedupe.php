<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Util;

use function html_entity_decode;
use function mb_strtolower;
use function preg_match;
use function preg_replace;
use function strip_tags;
use function strlen;
use function substr;
use function trim;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * 1.29 `web_ui.page_header.title: dedupe`: a page body that starts with an `<h1>` repeating the page title shows the
 * title twice (header + body). The read view drops that first heading at render time, only on an exact match of the
 * normalised text (trimmed, whitespace collapsed, case-insensitive); the stored revision, the editor, search, export
 * and the API are untouched. Pure function, no state (worker mode).
 */
final class WikiTitleDedupe
{
    /** A leading `<h1>` (after whitespace or comments only) and its content. */
    private const LEADING_H1 = '#^(?:\s|<!--.*?-->)*<h1\b[^>]*>(.*?)</h1\s*>#is';

    /**
     * The body without its first heading when that heading is an `<h1>` whose text equals the title.
     *
     * @param string $html the rendered body (before sanitizing; the output is sanitized as usual)
     * @param string $title the page title
     */
    public static function strip(string $html, string $title): string
    {
        if (preg_match(self::LEADING_H1, $html, $match) !== 1) {
            return $html;
        }
        $wanted = self::normalise($title);
        if ($wanted === '' || self::normalise(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== $wanted) {
            return $html;
        }

        return trim(substr($html, strlen($match[0])));
    }

    /**
     * Trimmed, whitespace collapsed to one space, lower case.
     */
    public static function normalise(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? ''), 'UTF-8');
    }
}
