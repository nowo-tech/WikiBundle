<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use League\HTMLToMarkdown\Converter\BlockquoteConverter;
use League\HTMLToMarkdown\Converter\CodeConverter;
use League\HTMLToMarkdown\Converter\CommentConverter;
use League\HTMLToMarkdown\Converter\DivConverter;
use League\HTMLToMarkdown\Converter\EmphasisConverter;
use League\HTMLToMarkdown\Converter\HardBreakConverter;
use League\HTMLToMarkdown\Converter\HeaderConverter;
use League\HTMLToMarkdown\Converter\HorizontalRuleConverter;
use League\HTMLToMarkdown\Converter\ImageConverter;
use League\HTMLToMarkdown\Converter\LinkConverter;
use League\HTMLToMarkdown\Converter\ListBlockConverter;
use League\HTMLToMarkdown\Converter\ParagraphConverter;
use League\HTMLToMarkdown\Converter\PreformattedConverter;
use League\HTMLToMarkdown\Converter\TableConverter;
use League\HTMLToMarkdown\Converter\TextConverter;
use League\HTMLToMarkdown\Environment as HtmlEnvironment;
use League\HTMLToMarkdown\HtmlConverter;
use Nowo\WikiBundle\Interchange\HtmlToMarkdown\WikiStrikethroughConverter;
use Nowo\WikiBundle\Interchange\HtmlToMarkdown\WikiTaskItemConverter;
use Nowo\WikiBundle\Markdown\WikiTaskListNormalizer;

/**
 * Converts between Markdown (Outline/Notion exports) and wiki HTML storage.
 *
 * 1.11: Markdown is read as GitHub-flavoured Markdown (tables, task lists, strikethrough, autolinks); task lists come
 * out in the Tiptap markup ({@see WikiTaskListNormalizer}) and HTML tables go back to GFM tables.
 */
final readonly class WikiMarkdownConverter
{
    /** league/html-to-markdown options: its defaults, with tags stripped, script / style dropped and ATX headings. */
    private const HTML_TO_MARKDOWN_OPTIONS = [
        'header_style'            => 'atx',
        'suppress_errors'         => true,
        'strip_tags'              => true,
        'strip_placeholder_links' => false,
        'bold_style'              => '**',
        'italic_style'            => '*',
        'remove_nodes'            => 'script style',
        'hard_break'              => true,
        'list_item_style'         => '-',
        'preserve_comments'       => false,
        'use_autolinks'           => true,
        'table_pipe_escape'       => '\\|',
        'table_caption_side'      => 'top',
    ];

    private MarkdownConverter $markdownConverter;

    private HtmlConverter $htmlConverter;

    /**
     * Configures the CommonMark + GFM converter (raw HTML stripped, unsafe links disabled) and the HTML-to-Markdown
     * converter (with its table converter).
     */
    public function __construct()
    {
        $environment = new Environment([
            'html_input'         => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addEventListener(DocumentParsedEvent::class, new WikiTaskListNormalizer(), -100);
        $this->markdownConverter = new MarkdownConverter($environment);

        // The converters of HtmlEnvironment::createDefaultEnvironment(), then GFM tables, task items and strikethrough.
        $htmlEnvironment = new HtmlEnvironment(self::HTML_TO_MARKDOWN_OPTIONS);
        foreach ([
            new BlockquoteConverter(), new CodeConverter(), new CommentConverter(), new DivConverter(),
            new EmphasisConverter(), new HardBreakConverter(), new HeaderConverter(), new HorizontalRuleConverter(),
            new ImageConverter(), new LinkConverter(), new ListBlockConverter(), new ParagraphConverter(),
            new PreformattedConverter(), new TextConverter(), new TableConverter(), new WikiTaskItemConverter(),
            new WikiStrikethroughConverter(),
        ] as $converter) {
            $htmlEnvironment->addConverter($converter);
        }

        $this->htmlConverter = new HtmlConverter($htmlEnvironment);
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
