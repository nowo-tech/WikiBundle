<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Util;

use function htmlspecialchars;
use function preg_replace;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * 1.29 (audit MA-L1): every table of a sanitized page body goes into its own scroll box, so a wide table scrolls
 * sideways inside the column while the `<table>` keeps its table display and semantics (a `display: block` table may
 * lose them in some engines). The box is `<div class="nowo-wiki-table-scroll" data-nowo-ui-scroll-region
 * data-nowo-ui-scroll-label="…">`: UiKit `js/nowo-ui-scroll-region.js` gives it `tabindex="0"`, `role="region"` and
 * the label while it overflows. Runs on sanitized HTML (tags are lower case, `<` in text and attributes is escaped),
 * after the sanitizer, so the allow-list is unchanged. Pure function, no state (worker mode).
 */
final class WikiTableScroll
{
    /** Class of the scroll box. */
    public const CLASS_NAME = 'nowo-wiki-table-scroll';

    /**
     * The HTML with each `<table>…</table>` wrapped in a labelled scroll box (nested tables get their own box).
     *
     * @param string $html sanitized HTML
     * @param string $label accessible name of the boxes (e.g. "Table"), escaped here
     */
    public static function wrap(string $html, string $label): string
    {
        $open = '<div class="' . self::CLASS_NAME . '" data-nowo-ui-scroll-region data-nowo-ui-scroll-label="' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
        $html = preg_replace('#<table(?=[\s>])#i', $open . '<table', $html) ?? $html;

        return preg_replace('#</table\s*>#i', '</table></div>', $html) ?? $html;
    }
}
