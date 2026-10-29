<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Twig;

use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Provides `nowo_wiki_safe_html`: re-sanitizes stored page HTML at render time, so revisions saved
 * before a sanitizer fix (or written directly to the database) are never output unsanitized.
 */
final class WikiHtmlTwigExtension extends AbstractExtension
{
    /**
     * Creates the extension.
     *
     * @param WikiHtmlSanitizerInterface $sanitizer sanitizer applied to page HTML at render time
     */
    public function __construct(
        private readonly WikiHtmlSanitizerInterface $sanitizer,
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
     * Sanitizes stored page HTML for output; returns an empty string for null or empty input.
     *
     * @param string|null $html stored page HTML
     *
     * @return string sanitized HTML
     */
    public function sanitize(?string $html): string
    {
        return $html === null || $html === '' ? '' : $this->sanitizer->sanitize($html);
    }
}
