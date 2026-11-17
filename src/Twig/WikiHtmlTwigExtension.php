<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Twig;

use Nowo\MilkdownEditorBundle\Markdown\HeadingAnchorLinks;
use Nowo\WikiBundle\Markdown\Milkdown\WikiFilePreviewFrames;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownIconHydrator;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownRichContent;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;
use Nowo\WikiBundle\Ui\WikiTabActions;
use Nowo\WikiBundle\Util\WikiDateFormat;
use Nowo\WikiBundle\Util\WikiTableScroll;
use Nowo\WikiBundle\ValueObject\WikiSpaceIcon;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

use function htmlspecialchars;
use function str_replace;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

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
     * @param array<string, array{picker?: string}> $iconPicker 1.39 `nowo_wiki.icon_picker` (which icon fields use the IconSelector picker)
     */
    public function __construct(
        private readonly WikiHtmlSanitizerInterface $sanitizer,
        private readonly ?WikiMilkdownIconHydrator $iconHydrator = null,
        private readonly ?WikiMilkdownRichContent $richContent = null,
        private readonly ?WikiFilePreviewFrames $previewFrames = null,
        private readonly array $iconPicker = [],
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
            // 1.40: "link to this section" controls on the headings of an already sanitized body (Milkdown pages).
            new TwigFilter('nowo_wiki_section_links', $this->sectionLinks(...), ['is_safe' => ['html']]),
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
            // 1.39: the grapheme of an `emoji:` icon of an acceptable shape (printed as escaped text), else null.
            new TwigFunction('nowo_wiki_icon_emoji', WikiSpaceIcon::emojiOf(...)),
            // 1.39: whether a page or space icon field uses the IconSelector picker (its script is then loaded).
            new TwigFunction('nowo_wiki_icon_picker', $this->usesIconPicker(...)),
        ];
    }

    /**
     * 1.39: whether a page or space icon field uses the IconSelector picker (`*_icon.picker: icon_selector`).
     */
    public function usesIconPicker(): bool
    {
        foreach ($this->iconPicker as $scope) {
            if (($scope['picker'] ?? 'text') === 'icon_selector') {
                return true;
            }
        }

        return false;
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
     * 1.40 (contract "section anchors and link picker" §4.5): appends a "link to this section" control to every heading
     * of an **already sanitized** body (Milkdown 1.18 `HeadingAnchorLinks::decorate()`; the sanitizer is unchanged), with
     * `editor.type: milkdown` only (other bodies come back unchanged). Without JavaScript it is an ordinary fragment link
     * to `#nowo-h-<id>` (*Copy link address* works); `wiki.js` copies the page URL with the fragment and says
     * `$copiedLabel`.
     *
     * @param mixed $html sanitized HTML (the output of `nowo_wiki_safe_html`)
     * @param string $label accessible name and title of the control (translated)
     * @param string $copiedLabel what the control says after a copy (translated)
     */
    public function sectionLinks(mixed $html, string $label, string $copiedLabel): string
    {
        $html = (string) $html;
        if (!$this->richContent instanceof WikiMilkdownRichContent || $html === '') {
            return $html;
        }

        return str_replace(
            '<a class="' . HeadingAnchorLinks::CSS_CLASS . '" ',
            '<a class="' . HeadingAnchorLinks::CSS_CLASS . '" data-wiki-section-link data-wiki-copied-label="' . htmlspecialchars($copiedLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" ',
            HeadingAnchorLinks::decorate($html, $label),
        );
    }

    /**
     * The read-side `<link>` / `<script>` tags of the Markdown dialect, or an empty string.
     */
    public function richContentAssets(): string
    {
        return $this->richContent?->assets() ?? '';
    }
}
