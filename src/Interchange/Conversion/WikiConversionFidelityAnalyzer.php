<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange\Conversion;

use DOMDocument;
use DOMElement;
use Nowo\WikiBundle\Markdown\WikiMarkdownLimitsInterface;
use Nowo\WikiBundle\Markdown\WikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface;

use function array_sum;
use function html_entity_decode;
use function in_array;
use function ksort;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function max;
use function mb_strtolower;
use function min;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function round;
use function strtolower;
use function trim;

use const ENT_HTML5;
use const ENT_QUOTES;
use const LIBXML_NONET;

/**
 * Measures what `wiki:convert-markdown` lost or approximated on one page (1.13): the source HTML is walked for
 * constructs Markdown (GFM, or GFM + the Milkdown dialect) cannot hold, and the words of the HTML are compared with the
 * words of the converted Markdown once rendered.
 *
 * Construct codes — lost: `underline`, `subscript`, `superscript`, `highlight` (GFM only), `alignment` (justified
 * text; centred / right-aligned text with GFM), `image_size`, `column_width`, `list_style`, `embed` (GFM only),
 * `element:<tag>` (an element the converter drops), `render_limit` (the Markdown is over the renderer's limits and
 * would show as plain text). Approximated: `table_cell_span`, `table_cell_blocks`, `table_without_header`,
 * `nested_table`, `table_caption`, `figure_caption`, `embed_as_link` (dialect).
 */
final readonly class WikiConversionFidelityAnalyzer
{
    /** Elements the converter writes faithfully (their attributes are checked separately). */
    private const SUPPORTED = [
        'html', 'body', 'p', 'br', 'strong', 'b', 'em', 'i', 's', 'del', 'strike', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'blockquote', 'code', 'pre', 'a', 'img', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'hr', 'span', 'div', 'figure',
    ];

    /** Elements whose text is kept but whose meaning is dropped. */
    private const LOST_ELEMENTS = ['u' => 'underline', 'sub' => 'subscript', 'sup' => 'superscript'];

    /** Block content a GFM table cell (one line) cannot hold as such. */
    private const CELL_BLOCKS = ['p', 'ul', 'ol', 'pre', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr'];

    /** Dialect tokens a page may hold as text, left out of the word comparison (rendered as titles / icons). */
    private const TOKENS = '/\[\[wiki:[0-9a-fA-F-]{36}(?:#[A-Za-z0-9_-]{1,128})?\]\]|:tabler-[a-z0-9]+(?:-[a-z0-9]+)*:/';

    private WikiMarkdownRendererInterface $renderer;

    /**
     * @param WikiMarkdownRendererInterface|null $renderer renders the converted Markdown (the bundle renderer; the
     *                                                     default GFM renderer when null)
     */
    public function __construct(?WikiMarkdownRendererInterface $renderer = null)
    {
        $this->renderer = $renderer ?? new WikiMarkdownRenderer();
    }

    /**
     * Analyses one conversion.
     *
     * @param string $html source HTML (the current revision)
     * @param string $markdown converted Markdown
     * @param bool $dialect whether the Milkdown dialect was written (alignment, highlight and embeds kept)
     */
    public function analyze(string $html, string $markdown, bool $dialect): WikiConversionFidelity
    {
        $lost         = [];
        $approximated = [];
        foreach ($this->elements($html) as $element) {
            $this->inspect($element, $dialect, $lost, $approximated);
        }

        if ($this->renderer instanceof WikiMarkdownLimitsInterface && $this->renderer->exceedsLimits($markdown)) {
            $lost['render_limit'] = 1;
        }

        $source   = $this->words($this->text($html));
        $rendered = $this->words($this->text(preg_replace('#<span class="nowo-md-ref[^"]*">.*?</span>#s', ' ', $this->renderer->render($markdown)) ?? ''));
        $missing  = 0;
        $added    = 0;
        foreach ($source as $word => $count) {
            $missing += max(0, $count - ($rendered[$word] ?? 0));
        }

        foreach ($rendered as $word => $count) {
            $added += max(0, $count - ($source[$word] ?? 0));
        }

        $total  = array_sum($source) + array_sum($rendered);
        $common = array_sum($source) - $missing;
        ksort($lost);
        ksort($approximated);

        return new WikiConversionFidelity(
            $lost,
            $approximated,
            $total === 0 ? 1.0 : round(min(1.0, 2 * $common / $total), 4),
            $missing,
            $added,
        );
    }

    /**
     * @param array<string, int> $lost
     * @param array<string, int> $approximated
     */
    private function inspect(DOMElement $element, bool $dialect, array &$lost, array &$approximated): void
    {
        $tag   = strtolower($element->tagName);
        $style = $element->getAttribute('style');
        if (isset(self::LOST_ELEMENTS[$tag])) {
            $this->tally($lost, self::LOST_ELEMENTS[$tag]);
        } elseif ($tag === 'mark') {
            if (!$dialect) {
                $this->tally($lost, 'highlight');
            }
        } elseif ($tag === 'iframe') {
            if ($dialect) {
                $this->tally($approximated, 'embed_as_link');
            } else {
                $this->tally($lost, 'embed');
            }
        } elseif ($tag === 'figcaption') {
            $this->tally($approximated, 'figure_caption');
        } elseif ($tag === 'caption') {
            $this->tally($approximated, 'table_caption');
        } elseif ($tag === 'table') {
            $this->inspectTable($element, $approximated);
        } elseif (!in_array($tag, self::SUPPORTED, true)) {
            $this->tally($lost, 'element:' . $tag);
        }

        if (preg_match('/text-align\s*:\s*(\w+)/i', $style, $match) === 1) {
            $align = strtolower($match[1]);
            if ($align === 'justify' || (!$dialect && in_array($align, ['center', 'right'], true))) {
                $this->tally($lost, 'alignment');
            }
        }

        if ($tag === 'img' && ($element->hasAttribute('width') || $element->hasAttribute('height'))) {
            $this->tally($lost, 'image_size');
        }

        if (($tag === 'td' || $tag === 'th') && ($element->hasAttribute('colwidth') || preg_match('/(?<![-\w])width\s*:/i', $style) === 1)) {
            $this->tally($lost, 'column_width');
        }

        if ($tag === 'ol' && trim($element->getAttribute('type')) !== '' && $element->getAttribute('type') !== '1') {
            $this->tally($lost, 'list_style');
        }
    }

    /**
     * @param array<string, int> $approximated
     */
    private function inspectTable(DOMElement $table, array &$approximated): void
    {
        $headers = 0;
        foreach ($table->getElementsByTagName('*') as $node) {
            $tag = strtolower($node->tagName);
            if ($tag === 'table') {
                $this->tally($approximated, 'nested_table');
            } elseif ($tag === 'th' || $tag === 'td') {
                $headers += $tag === 'th' ? 1 : 0;
                if ((int) $node->getAttribute('colspan') > 1 || (int) $node->getAttribute('rowspan') > 1) {
                    $this->tally($approximated, 'table_cell_span');
                }

                if ($this->hasCellBlocks($node)) {
                    $this->tally($approximated, 'table_cell_blocks');
                }
            }
        }

        if ($headers === 0) {
            $this->tally($approximated, 'table_without_header');
        }
    }

    private function hasCellBlocks(DOMElement $cell): bool
    {
        $paragraphs = 0;
        foreach ($cell->getElementsByTagName('*') as $node) {
            $tag = strtolower($node->tagName);
            if ($tag === 'p') {
                ++$paragraphs;
            } elseif (in_array($tag, self::CELL_BLOCKS, true)) {
                return true;
            }
        }

        return $paragraphs > 1;
    }

    /**
     * @return list<DOMElement>
     */
    private function elements(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $elements = [];
        foreach ($document->getElementsByTagName('*') as $element) {
            $elements[] = $element;
        }

        return $elements;
    }

    /**
     * @param array<string, int> $counts
     */
    private function tally(array &$counts, string $code): void
    {
        $counts[$code] = ($counts[$code] ?? 0) + 1;
    }

    private function text(string $html): string
    {
        $text = html_entity_decode(preg_replace('/<[^>]*>/', ' ', $html) ?? $html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace(self::TOKENS, ' ', $text) ?? $text;
    }

    /**
     * @return array<string, int> word => occurrences
     */
    private function words(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($text), $matches);
        $words = [];
        foreach ($matches[0] as $word) {
            $words[$word] = ($words[$word] ?? 0) + 1;
        }

        return $words;
    }
}
