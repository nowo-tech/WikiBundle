<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Twig;

use Nowo\WikiBundle\Markdown\Milkdown\WikiFilePreviewFrames;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownIconHydrator;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownRichContent;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;
use Nowo\WikiBundle\Ui\WikiTabActions;
use Nowo\WikiBundle\Util\WikiDateFormat;
use Nowo\WikiBundle\Util\WikiTableScroll;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Provides `nowo_wiki_safe_html`: re-sanitizes stored page HTML at render time, so revisions saved
 * before a sanitizer fix (or written directly to the database) are never output unsanitized.
 *
 * 1.13 (`editor.type: milkdown`): the filter then draws the allowlisted Tabler icons of the dialect
 * ({@see WikiMilkdownIconHydrator}), and `nowo_wiki_rich_content_assets()` prints the Milkdown read-side assets
 * (an empty string with any other editor).
 *
 * 1.28 (`editor.milkdown.file_previews`): after sanitizing, the file preview frames the per-viewer render registered
 * ({@see WikiFilePreviewFrames}) are put back in place of their placeholders.
 *
 * 1.31: `nowo_wiki_datetime(locale)` formats a date in the given locale ({@see WikiDateFormat::medium()}); the page view
 * passes the request locale, so nothing is kept between requests.
 */
final class WikiHtmlTwigExtension extends AbstractExtension
{
    /**
     * Creates the extension.
     *
     * @param WikiHtmlSanitizerInterface $sanitizer sanitizer applied to page HTML at render time
     * @param WikiMilkdownIconHydrator|null $iconHydrator draws dialect icons after sanitizing (1.13, milkdown)
     * @param WikiMilkdownRichContent|null $richContent read-side assets of the dialect (1.13, milkdown)
     * @param WikiFilePreviewFrames|null $previewFrames puts back the file preview frames of this request after sanitizing (1.28, milkdown)
     */
    public function __construct(
        private readonly WikiHtmlSanitizerInterface $sanitizer,
        private readonly ?WikiMilkdownIconHydrator $iconHydrator = null,
        private readonly ?WikiMilkdownRichContent $richContent = null,
        private readonly ?WikiFilePreviewFrames $previewFrames = null,
    ) {
    }

    /**
     * Registers the `nowo_wiki_safe_html` filter, marked as HTML-safe.
     *
     * @return list<TwigFilter> filters provided by this extension
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('nowo_wiki_safe_html', $this->sanitize(...), ['is_safe' => ['html']]),
            // 1.29 (MA-L1): tables of an already sanitized body in labelled scroll boxes.
            new TwigFilter('nowo_wiki_scroll_tables', static fn (mixed $html, string $label): string => WikiTableScroll::wrap((string) $html, $label), ['is_safe' => ['html']]),
            // 1.31 (web_ui.page_meta: card): a date in the reader's locale (medium date, short time); plain text.
            new TwigFilter('nowo_wiki_datetime', WikiDateFormat::medium(...)),
        ];
    }

    /**
     * Registers `nowo_wiki_rich_content_assets()` (1.13), marked as HTML-safe, and `nowo_wiki_tab_actions()` (1.30).
     *
     * @return list<TwigFunction> functions provided by this extension
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_wiki_rich_content_assets', $this->richContentAssets(...), ['is_safe' => ['html']]),
            // 1.30 (web_ui.page_header.tabs: toolbar): the page tabs as toolbar icon buttons.
            new TwigFunction('nowo_wiki_tab_actions', WikiTabActions::fromTabs(...)),
        ];
    }

    /**
     * Sanitizes stored page HTML for output; returns an empty string for null or empty input.
     *
     * @param string|null $html stored page HTML
     *
     * @return string sanitized HTML
     */
    public function sanitize(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $html = $this->sanitizer->sanitize($html);
        $html = $this->iconHydrator instanceof WikiMilkdownIconHydrator ? $this->iconHydrator->hydrate($html) : $html;

        // 1.28: only frames this request's render registered; any other placeholder is removed.
        return $this->previewFrames instanceof WikiFilePreviewFrames ? $this->previewFrames->hydrate($html) : $html;
    }

    /**
     * The read-side `<link>` / `<script>` tags of the Markdown dialect, or an empty string.
     */
    public function richContentAssets(): string
    {
        return $this->richContent?->assets() ?? '';
    }
}
