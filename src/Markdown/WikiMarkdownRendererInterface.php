<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown;

use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;

/**
 * Renders the Markdown source of a revision to HTML (1.11, `nowo_wiki.editor.type: markdown`).
 *
 * The page service sanitizes the result ({@see WikiHtmlSanitizerInterface}) and stores it
 * as the revision's `content_html` render cache. Replace the alias of this interface to render with another engine;
 * to add syntax to the default renderer, register a {@see WikiMarkdownExtensionInterface} service instead.
 */
interface WikiMarkdownRendererInterface
{
    /**
     * Converts Markdown to HTML (not sanitized yet); empty input yields an empty string.
     *
     * @param string $markdown Markdown source
     *
     * @return string rendered HTML
     */
    public function render(string $markdown): string;
}
