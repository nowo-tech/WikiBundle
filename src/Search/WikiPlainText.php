<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Search;

use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;

use function chr;
use function dechex;
use function hexdec;
use function html_entity_decode;
use function ord;
use function preg_replace;
use function preg_replace_callback;
use function str_replace;
use function strip_tags;
use function trim;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * The plain text of a page body (1.16, public API): what the search matches and the excerpts show.
 *
 * - {@see self::fromHtml()} reduces the rendered, sanitized HTML (the revision's viewer-neutral `content_html`): tags
 *   are dropped with one space at every block boundary, so attribute values (`data-icon="bulb"`, callout types, link
 *   targets) never become text; 1.31: the placeholder of a hidden reference (in any language) is dropped. It fills
 *   the revision's `content_text` column on save ({@see WikiPageRevision::getContentText()})
 *   and `wiki:search:backfill-text` (`--all` rewrites texts saved before 1.31).
 * - {@see self::fromMarkdown()} reduces Markdown in the Milkdown dialect without rendering it: directive fences and
 *   `{…}` attribute blocks, icon shortcodes (`:tabler-bulb:`), page links (`[[wiki:<uuid>]]`, never a title) and the
 *   Markdown punctuation are dropped, link and image texts are kept. Use it where only the source is at hand (for
 *   example a host search provider indexing a draft).
 *
 * Both are pure and stateless: safe in long-running workers. The result is one line (runs of whitespace collapse to
 * one space).
 */
final class WikiPlainText
{
    /** Tags that start or end a block of text (a space is inserted before each one). */
    private const BLOCK_BOUNDARY = '~</?(?:address|article|aside|blockquote|br|dd|details|div|dl|dt|figcaption|figure|footer|h[1-6]|header|hr|img|li|ol|p|pre|section|summary|table|tbody|td|tfoot|th|thead|tr|ul)\b[^>]*>~i';

    /** Elements whose content is never text (scripts and styles are refused by the sanitizer anyway; SVG icons). */
    private const NON_TEXT = '~<(script|style|svg|template)\b[^>]*>.*?</\1\s*>~is';

    /**
     * 1.31: the placeholder of a reference the reader may not see (`nowo-md-ref--hidden`). Since 1.31 the shared cache
     * stores it empty; revisions saved before hold a text translated in the saver's locale, which is dropped too. The
     * class must be a whole token of the `class` attribute (audit NB-W-1: `-` is not a boundary, `data-class` is not
     * `class`); since 1.31 authored HTML cannot carry it ({@see WikiHtmlSanitizer::withoutServerClasses()}).
     */
    private const HIDDEN_REF = '~<span\b[^>]*\sclass\s*=\s*(["\'])(?:(?!\1).)*?(?<![\w-])nowo-md-ref--hidden(?![\w-])(?:(?!\1).)*\1[^>]*>[^<]*</span\s*>~is';

    /**
     * The text of rendered HTML with one space between blocks: "<h2>Overview</h2><p>This runbook…" reads
     * "Overview This runbook…"; entities are decoded and runs of whitespace (no-break spaces included) collapse.
     */
    public static function fromHtml(string $html): string
    {
        $html   = preg_replace(self::NON_TEXT, ' ', $html) ?? $html;
        $html   = preg_replace(self::HIDDEN_REF, ' ', $html) ?? $html;
        $spaced = preg_replace(self::BLOCK_BOUNDARY, ' $0', $html) ?? $html;
        $text   = html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return self::collapse($text);
    }

    /**
     * The text of Markdown in the Milkdown dialect, without rendering it (see the class description).
     */
    public static function fromMarkdown(string $markdown): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $markdown);
        // Backslash escapes are kept aside (a private-use marker) so the punctuation rules below never see them.
        $text = preg_replace_callback('/\\\\([\\\\`*_{}\[\]()#+\-.!|~>$:])/', static fn (array $m): string => "\u{E000}" . dechex(ord($m[1])) . "\u{E001}", $text) ?? $text;
        // Fenced code: the fence lines go, the code stays (it is searchable text).
        $text = preg_replace('/^[ \t]*(?:`{3,}|~{3,})[^\n]*$/m', ' ', $text) ?? $text;
        // Container directives: `:::callout{icon="bulb"}` / `:::details[Summary]` → the label only; closing `:::` lines go.
        $text = preg_replace_callback('/^[ \t]*:{3,}[ \t]*([A-Za-z][\w-]*)?(?:\[([^\]\n]*)\])?(?:\{[^}\n]*\})?[ \t]*$/m', static fn (array $m): string => ' ' . ($m[2] ?? '') . ' ', $text) ?? $text;
        // Leaf and text directives `::name[label]{…}` / `:name[label]{…}` → the label.
        $text = preg_replace('/(?<![\w:]):{1,2}[A-Za-z][\w-]*\[([^\]\n]*)\](?:\{[^}\n]*\})?/', '$1', $text) ?? $text;
        // Leaf directives without a label `::name{…}`.
        $text = preg_replace('/(?<![\w:])::[A-Za-z][\w-]*\{[^}\n]*\}/', ' ', $text) ?? $text;
        // Icon shortcodes (`:tabler-bulb:`, `:rocket:`) and page links (ids only: never a title).
        $text = preg_replace('/(?<![\w:]):[a-z0-9][a-z0-9_+-]*:(?![\w:])/i', ' ', $text) ?? $text;
        $text = preg_replace('/\[\[wiki:[^\]\n]*\]\]/i', ' ', $text) ?? $text;
        // Images and links keep their text; reference definitions and attribute blocks go.
        $text = preg_replace('/!\[([^\]\n]*)\]\([^)\n]*\)/', '$1', $text) ?? $text;
        $text = preg_replace('/\[([^\]\n]*)\]\([^)\n]*\)/', '$1', $text) ?? $text;
        $text = preg_replace('/^[ \t]*\[[^\]\n]+\]:[ \t]+\S+[^\n]*$/m', ' ', $text) ?? $text;
        $text = preg_replace('/\[([^\]\n]*)\]\[[^\]\n]*\]/', '$1', $text) ?? $text;
        $text = preg_replace('/\{[#.:][^}\n]*\}|\{[\w-]+="[^"\n]*"[^}\n]*\}/', ' ', $text) ?? $text;
        $text = preg_replace('/<(https?:\/\/[^>\s]+)>/i', '$1', $text) ?? $text;
        $text = strip_tags($text);
        // Block markers: headings, quotes, list bullets, task boxes, rules, table separators and pipes.
        $text = preg_replace('/^[ \t]*#{1,6}[ \t]+/m', '', $text) ?? $text;
        $text = preg_replace('/^[ \t]*(?:>[ \t]?)+/m', '', $text) ?? $text;
        $text = preg_replace('/^[ \t]*(?:[-*+]|\d{1,9}[.)])[ \t]+(?:\[[ xX]\][ \t]+)?/m', '', $text) ?? $text;
        $text = preg_replace('/^[ \t]*(?:[-*_][ \t]*){3,}$/m', ' ', $text) ?? $text;
        $text = preg_replace('/^[ \t]*\|?[ \t]*:?-+:?[ \t]*(?:\|[ \t]*:?-+:?[ \t]*)+\|?[ \t]*$/m', ' ', $text) ?? $text;
        $text = str_replace('|', ' ', $text);
        // Inline punctuation: strong / emphasis / strikethrough / mark / code / math delimiters, hard-break backslashes.
        $text = preg_replace('/(\*{1,3}|~~|==|`+|\$\$?)(?=\S)(.+?)(?<=\S)\1/u', '$2', $text) ?? $text;
        $text = preg_replace('/(?<!\w)(_{1,3})(?=\S)(.+?)(?<=\S)\1(?!\w)/u', '$2', $text) ?? $text;
        $text = preg_replace_callback('/\x{E000}([0-9a-f]{2})\x{E001}/u', static fn (array $m): string => chr((int) hexdec($m[1])), $text) ?? $text;

        return self::collapse(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function collapse(string $text): string
    {
        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? $text);
    }
}
