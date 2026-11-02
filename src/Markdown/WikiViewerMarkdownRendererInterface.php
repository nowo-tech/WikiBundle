<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown;

/**
 * A Markdown renderer whose output depends on the viewer (1.13): page links show the current title and URL of pages
 * the viewer may read (*Content not visible* otherwise) and mentions link to people the host knows.
 *
 * {@see WikiMarkdownRendererInterface::render()} stays viewer-neutral (it fills the `content_html` cache shared by
 * every reader, search excerpts and AI context); this method renders the page body for the current request only and
 * must never be stored.
 */
interface WikiViewerMarkdownRendererInterface
{
    /**
     * Converts Markdown to HTML for the current viewer (not sanitized yet); empty input yields an empty string.
     *
     * @param string $markdown Markdown source
     *
     * @return string rendered HTML
     */
    public function renderForViewer(string $markdown): string;
}
