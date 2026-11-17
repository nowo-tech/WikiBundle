<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange\HtmlToMarkdown;

use League\HTMLToMarkdown\Converter\HeaderConverter;
use League\HTMLToMarkdown\ElementInterface;
use Nowo\WikiBundle\Markdown\WikiHeadingAnchor;
use Nowo\WikiBundle\Markdown\WikiHeadingOutline;

use function html_entity_decode;
use function preg_replace;
use function preg_replace_callback;
use function rtrim;
use function str_starts_with;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * Headings of the Milkdown dialect (1.40, contract "section anchors and link picker" §1 Q1 point 4): the alignment of
 * {@see WikiAlignmentTrait} (as the 1.13 `WikiAlignedHeaderConverter`) plus the explicit id suffix
 * `## Title {#id}` from `<hN id="nowo-h-<id>">`, so the reader's Markdown and the API viewer view keep the ids the page's
 * links point at.
 *
 * Only a valid id ({@see WikiHeadingAnchor}) is written. {@see self::keepDifferentIds()} runs on the sanitized HTML first
 * and drops every id equal to the slug of its heading's text, so a suffix is written only where it differs (owner
 * decision O1: plain headings stay plain Markdown). A heading whose text itself ends with ` {#x}` gets `\{#x}`, so that
 * text is not read back as an id.
 */
final class WikiHeadingIdConverter extends HeaderConverter
{
    use WikiAlignmentTrait;

    /** A literal suffix at the end of heading text: escaped so it stays text. */
    private const LITERAL_SUFFIX = '/([ \t])\{#(' . WikiHeadingAnchor::ANCHOR . ')\}$/u';

    public function convert(ElementInterface $element): string
    {
        $markdown = parent::convert($element);
        $line     = rtrim($markdown, "\n");
        if ($line === '') {
            return $markdown;
        }
        $id   = $this->explicitId($element);
        $line = (preg_replace(self::LITERAL_SUFFIX, '$1\\\\{#$2}', $line) ?? $line) . ($id !== '' ? ' {#' . $id . '}' : '');

        return $this->align($element, $line . "\n\n");
    }

    /**
     * Drops the `id` of every heading whose id is the slug of its text (the implicit id a reader of the Markdown would
     * compute again), so {@see self::convert()} writes a suffix only where the id differs (a renamed heading, a repeat).
     *
     * @param string $html sanitized HTML (double-quoted attributes)
     */
    public static function keepDifferentIds(string $html): string
    {
        return preg_replace_callback(
            '~<h([1-6])\b([^>]*?)\sid="([^"]*)"([^>]*)>(.*?)</h\1\s*>~is',
            static function (array $m): string {
                $id = WikiHeadingAnchor::normalise(html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                // Audit RY-L1: the text the id sees (no page references, mentions, icons or math), as the sections do.
                $text = WikiHeadingOutline::textOf($m[5]);
                if ($id !== '' && $id !== WikiHeadingAnchor::slug($text)) {
                    return $m[0];
                }

                return '<h' . $m[1] . $m[2] . $m[4] . '>' . $m[5] . '</h' . $m[1] . '>';
            },
            $html,
        ) ?? $html;
    }

    /**
     * The bare id of a heading element (`''` without a valid `nowo-h-` id).
     */
    private function explicitId(ElementInterface $element): string
    {
        $raw = $element->getAttribute('id');

        return str_starts_with($raw, WikiHeadingAnchor::PREFIX) ? WikiHeadingAnchor::normalise($raw) : '';
    }
}
