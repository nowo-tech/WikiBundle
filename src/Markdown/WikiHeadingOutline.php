<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

use function array_intersect;
use function explode;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function mb_substr;
use function preg_match;
use function preg_replace;
use function str_starts_with;
use function strtolower;
use function trim;

use const LIBXML_NOERROR;
use const LIBXML_NONET;
use const LIBXML_NOWARNING;

/**
 * 1.40: the sections (headings with a `nowo-h-<id>` DOM id) of rendered, sanitized page HTML — the link picker's step 2
 * and the `Title › Section` labels of page links (contract "section anchors and link picker" §3.1, §4.4).
 *
 * Only headings whose id is valid ({@see WikiHeadingAnchor}) count, the first occurrence of an id wins, and at most
 * {@see self::MAX_SECTIONS} are returned. The text is the heading's own text and code: page references (titles of other
 * pages), mentions, icons and math are left out, so a section label never carries another page's title or a person's
 * name. With `$topLevelOnly`, headings nested in any block (callouts, details, quotes, lists, host containers) are left
 * out too. Stateless; nothing is kept between calls.
 */
final class WikiHeadingOutline
{
    /** Most sections returned for one page (the Milkdown picker keeps 200). */
    public const MAX_SECTIONS = 200;

    /** Longest section text, in characters. */
    public const MAX_TEXT = 200;

    /** Deepest element nesting walked (the sanitizer and the Markdown renderer bound nesting far below it). */
    public const MAX_DEPTH = 64;

    /** Classes of dialect markup whose text is not the heading's own. */
    private const FOREIGN_CLASSES = ['nowo-md-ref', 'nowo-md-mention', 'nowo-md-icon', 'nowo-md-math', 'nowo-md-heading-anchor', 'nowo-milkdown-sr-only'];

    /**
     * @param string $html sanitized HTML of one page
     * @param bool $topLevelOnly only headings that are direct children of the document
     *
     * @return list<array{id: string, level: int, text: string}>
     */
    public static function fromHtml(string $html, bool $topLevelOnly = false): array
    {
        if (preg_match('/<h[1-6]\b/i', $html) !== 1) {
            return [];
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded   = $document->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $body = $document->getElementsByTagName('body')->item(0);
        if (!$loaded || !$body instanceof DOMElement) {
            return []; // @codeCoverageIgnore
        }

        $sections = [];
        $seen     = [];
        self::collect($body, $topLevelOnly, $sections, $seen, 0);

        return $sections;
    }

    /**
     * @param list<array{id: string, level: int, text: string}> $sections
     * @param array<string, true> $seen
     */
    private static function collect(DOMNode $parent, bool $topLevelOnly, array &$sections, array &$seen, int $depth): void
    {
        foreach ($parent->childNodes as $node) {
            if (!$node instanceof DOMElement || isset($sections[self::MAX_SECTIONS - 1])) {
                continue;
            }
            if (preg_match('/^h([1-6])$/', strtolower($node->tagName), $level) === 1) {
                $raw = $node->getAttribute('id');
                $id  = str_starts_with($raw, WikiHeadingAnchor::PREFIX) ? WikiHeadingAnchor::normalise($raw) : '';
                if ($id !== '' && !isset($seen[$id])) {
                    $seen[$id] = true;
                    $text      = trim((string) preg_replace('/\s+/u', ' ', self::text($node, 0)));
                    if ($text !== '') {
                        $sections[] = ['id' => $id, 'level' => (int) $level[1], 'text' => mb_substr($text, 0, self::MAX_TEXT, 'UTF-8')];
                    }
                }
                continue;
            }
            if (!$topLevelOnly && $depth < self::MAX_DEPTH) {
                self::collect($node, false, $sections, $seen, $depth + 1);
            }
        }
    }

    /**
     * The heading's own text: text nodes outside dialect references, mentions, icons and math.
     */
    private static function text(DOMNode $node, int $depth): string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $text .= $child->data;
            } elseif ($depth < self::MAX_DEPTH && $child instanceof DOMElement && array_intersect(explode(' ', $child->getAttribute('class')), self::FOREIGN_CLASSES) === []) {
                $text .= self::text($child, $depth + 1);
            }
        }

        return $text;
    }
}
