<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;
use League\HTMLToMarkdown\HtmlConverter;

/**
 * Converts between Markdown (Outline/Notion exports) and wiki HTML storage.
 */
final readonly class WikiMarkdownConverter
{
    private MarkdownConverter $markdownConverter;

    private HtmlConverter $htmlConverter;

    /**
     * Configures the CommonMark converter (raw HTML stripped, unsafe links disabled) and the HTML-to-Markdown converter.
     */
    public function __construct()
    {
        $environment = new Environment([
            'html_input'         => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $this->markdownConverter = new MarkdownConverter($environment);
        $this->htmlConverter     = new HtmlConverter([
            'strip_tags'   => true,
            'remove_nodes' => 'script style',
            'hard_break'   => true,
            'header_style' => 'atx',
        ]);
    }

    /**
     * Converts Markdown to HTML; empty input yields an empty paragraph.
     *
     * @param string $markdown Markdown source
     *
     * @return string rendered HTML
     */
    public function markdownToHtml(string $markdown): string
    {
        $markdown = trim($markdown);
        if ($markdown === '') {
            return '<p></p>';
        }

        return trim($this->markdownConverter->convert($markdown)->getContent());
    }

    /**
     * Converts stored HTML to ATX-style Markdown, dropping script and style nodes; empty input yields an empty string.
     *
     * @param string $html HTML source
     *
     * @return string Markdown text
     */
    public function htmlToMarkdown(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        return trim($this->htmlConverter->convert($html));
    }
}
