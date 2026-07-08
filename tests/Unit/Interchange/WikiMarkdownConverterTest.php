<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Interchange;

use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use PHPUnit\Framework\TestCase;

final class WikiMarkdownConverterTest extends TestCase
{
    public function testMarkdownToHtml(): void
    {
        $html = (new WikiMarkdownConverter())->markdownToHtml("## Title\n\nParagraph with **bold**.");

        self::assertStringContainsString('<h2', $html);
        self::assertStringContainsString('<strong>bold</strong>', $html);
    }

    public function testHtmlToMarkdownRoundTripPreservesHeading(): void
    {
        $converter = new WikiMarkdownConverter();
        $markdown  = $converter->htmlToMarkdown('<h2>Title</h2><p>Text</p>');
        $html      = $converter->markdownToHtml($markdown);

        self::assertStringContainsString('Title', $markdown);
        self::assertStringContainsString('<h2', $html);
    }
}
