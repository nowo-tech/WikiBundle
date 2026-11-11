<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Util;

use function html_entity_decode;
use function htmlspecialchars;
use function mb_strtolower;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function strip_tags;
use function strlen;
use function substr;
use function trim;

use const ENT_HTML5;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const PREG_SET_ORDER;

/**
 * 1.29 `web_ui.page_header.title: dedupe`: a page body that starts with an `<h1>` repeating the page title shows the
 * title twice (header + body). The read view drops that first heading at render time, only on an exact match of the
 * normalised text (trimmed, whitespace collapsed, case-insensitive); the stored revision, the editor, search, export
 * and the API are untouched. Pure function, no state (worker mode).
 *
 * Audit MA-I1: a heading that holds more than text (an image, media, a form control, math) is never dropped, and the
 * `id`s of a dropped heading (targets of in-page `#…` links and the table of contents) stay in the page on empty
 * `<sup class="nowo-wiki-anchor">` elements (an element the sanitizer keeps with its `id`).
 */
final class WikiTitleDedupe
{
    /** A leading `<h1>` (after whitespace or comments only) and its content. */
    private const LEADING_H1 = '#^(?:\s|<!--.*?-->)*<h1\b[^>]*>(.*?)</h1\s*>#is';

    /** Content that is more than text: such a heading is kept. */
    private const NON_TEXT = '#<(?:img|svg|picture|video|audio|iframe|object|embed|canvas|input|math|button|select|textarea)\b#i';

    /** An `id` attribute (double or single quoted). */
    private const ID_ATTR = '#\sid\s*=\s*(?:"([^"]*)"|\'([^\']*)\')#i';

    /**
     * The body without its first heading when that heading is an `<h1>` whose text equals the title.
     *
     * @param string $html the rendered body (before sanitizing; the output is sanitized as usual)
     * @param string $title the page title
     */
    public static function strip(string $html, string $title): string
    {
        if (preg_match(self::LEADING_H1, $html, $match) !== 1 || preg_match(self::NON_TEXT, $match[1]) === 1) {
            return $html;
        }
        $wanted = self::normalise($title);
        if ($wanted === '' || self::normalise(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== $wanted) {
            return $html;
        }

        $anchors = '';
        preg_match_all(self::ID_ATTR, $match[0], $ids, PREG_SET_ORDER);
        foreach ($ids as $id) {
            $value = html_entity_decode(($id[2] ?? '') !== '' ? $id[2] : ($id[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($value !== '') {
                $anchors .= '<sup id="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="nowo-wiki-anchor"></sup>';
            }
        }

        return $anchors . trim(substr($html, strlen($match[0])));
    }

    /**
     * Trimmed, whitespace collapsed to one space, lower case.
     */
    public static function normalise(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? ''), 'UTF-8');
    }
}
