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

    public function testEmptyMarkdownReturnsEmptyParagraph(): void
    {
        self::assertSame('<p></p>', (new WikiMarkdownConverter())->markdownToHtml('   '));
    }

    public function testEmptyHtmlReturnsEmptyString(): void
    {
        self::assertSame('', (new WikiMarkdownConverter())->htmlToMarkdown('   '));
    }

    public function testMarkdownToHtmlUsesGithubFlavouredMarkdown(): void
    {
        $html = (new WikiMarkdownConverter())->markdownToHtml(<<<'MD'
| Name | Role |
|------|------|
| Ada  | Lead |

- [x] Done
- [ ] Todo

~~Old~~ www.example.com
MD);

        self::assertStringContainsString('<table>', $html);
        self::assertStringContainsString('<th>Name</th>', $html);
        self::assertStringContainsString('<td>Ada</td>', $html);
        self::assertStringContainsString('<li data-type="taskItem" data-checked="true">Done</li>', $html);
        self::assertStringContainsString('<li data-type="taskItem" data-checked="false">Todo</li>', $html);
        self::assertStringContainsString('<del>Old</del>', $html);
        self::assertStringContainsString('<a href="http://www.example.com">www.example.com</a>', $html);
    }

    public function testHtmlToMarkdownWritesTablesTaskListsAndStrikethrough(): void
    {
        $markdown = (new WikiMarkdownConverter())->htmlToMarkdown(
            '<table><thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td>1</td><td>2</td></tr></tbody></table>'
            . '<ul data-type="taskList"><li data-type="taskItem" data-checked="true"><p>yes</p></li><li data-type="taskItem" data-checked="false"><p>no</p></li></ul>'
            . '<ol><li>first</li></ol><p><s>gone</s> <del>old</del> <strike>x</strike><s> </s>kept</p>',
        );

        self::assertSame("| A | B |\n|---|---|\n| 1 | 2 |\n\n- [x] yes\n- [ ] no\n\n1. first\n\n~~gone~~ ~~old~~ ~~x~~kept", $markdown);
    }
}
