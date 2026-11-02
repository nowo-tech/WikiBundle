<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Twig;

use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownIconHydrator;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownRichContent;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;
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
 */
final class WikiHtmlTwigExtension extends AbstractExtension
{
    /**
     * Creates the extension.
     *
     * @param WikiHtmlSanitizerInterface $sanitizer sanitizer applied to page HTML at render time
     * @param WikiMilkdownIconHydrator|null $iconHydrator draws dialect icons after sanitizing (1.13, milkdown)
     * @param WikiMilkdownRichContent|null $richContent read-side assets of the dialect (1.13, milkdown)
     */
    public function __construct(
        private readonly WikiHtmlSanitizerInterface $sanitizer,
        private readonly ?WikiMilkdownIconHydrator $iconHydrator = null,
        private readonly ?WikiMilkdownRichContent $richContent = null,
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
        ];
    }

    /**
     * Registers `nowo_wiki_rich_content_assets()` (1.13), marked as HTML-safe.
     *
     * @return list<TwigFunction> functions provided by this extension
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_wiki_rich_content_assets', $this->richContentAssets(...), ['is_safe' => ['html']]),
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

        return $this->iconHydrator instanceof WikiMilkdownIconHydrator ? $this->iconHydrator->hydrate($html) : $html;
    }

    /**
     * The read-side `<link>` / `<script>` tags of the Markdown dialect, or an empty string.
     */
    public function richContentAssets(): string
    {
        return $this->richContent?->assets() ?? '';
    }
}
