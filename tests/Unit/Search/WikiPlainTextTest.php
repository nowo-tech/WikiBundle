<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Search;

use Nowo\WikiBundle\Search\WikiPlainText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 1.16: the plain text the search matches — never markup, directive markers or attribute values.
 */
final class WikiPlainTextTest extends TestCase
{
    public function testHtmlBecomesOneLineOfTextWithSpacesBetweenBlocks(): void
    {
        self::assertSame(
            'Overview This runbook & more Step one Step two Cell',
            WikiPlainText::fromHtml('<h2>Overview</h2><p>This&nbsp;runbook &amp; more</p><ul><li>Step one</li><li>Step two</li></ul><table><tr><td>Cell</td></tr></table>'),
        );
        self::assertSame('Body', WikiPlainText::fromHtml('<div class="nowo-md-callout" data-type="info" data-icon="bulb"><svg class="nowo-md-icon"><use href="#tabler-bulb"></use><title>bulb</title></svg><div>Body</div></div>'));
        self::assertSame('Summary Detail', WikiPlainText::fromHtml('<details open><summary>Summary</summary><p>Detail</p></details>'));
        self::assertSame('', WikiPlainText::fromHtml(''));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function markdown(): iterable
    {
        yield 'emphasis and code' => ['Rotate the **keys** _monthly_ with `vault` and ~~never~~ ==now== $x^2$', 'Rotate the keys monthly with vault and never now x^2'];
        yield 'snake case stays' => ['call snake_case_name here', 'call snake_case_name here'];
        yield 'headings, quotes, lists, tasks' => ["# Title\n> quoted\n- item\n1. first\n- [x] done\n---\n", 'Title quoted item first done'];
        yield 'container directive' => [":::callout{icon=\"bulb\" type=\"info\"}\nBe careful\n:::", 'Be careful'];
        yield 'container directive with label' => [":::details[More]{open}\nHidden\n:::", 'More Hidden'];
        yield 'leaf and text directives' => ["::youtube[Intro video]{id=\"abc\"}\nSee :abbr[HTML]{title=\"x\"} and ::toc{depth=2}", 'Intro video See HTML and'];
        yield 'icons and page links' => ['Deploy :tabler-rocket: then :rocket: see [[wiki:7d6f2c1e-0000-4000-8000-000000000000]] at 10:30:00', 'Deploy then see at 10:30:00'];
        yield 'links and images' => ["![Diagram](/a.png) [Docs](https://x.test \"t\") [ref][1] <https://y.test>\n[1]: https://z.test", 'Diagram Docs ref https://y.test'];
        yield 'attribute blocks and html' => ['## Install {#install} <span class="x">raw</span> &amp; done', 'Install raw & done'];
        yield 'fences and tables' => ["```php\necho 1;\n```\n| A | B |\n|---|:-:|\n| 1 | 2 |", 'echo 1; A B 1 2'];
        yield 'escapes' => ['A \\*literal\\* star', 'A *literal* star'];
    }

    #[DataProvider('markdown')]
    public function testMarkdownLosesItsPunctuationAndDirectives(string $markdown, string $text): void
    {
        self::assertSame($text, WikiPlainText::fromMarkdown($markdown));
    }

    /** 1.31: a hidden reference is never text, whatever the language it was stored in (legacy caches) or empty. */
    public function testHiddenReferencesAreNeverText(): void
    {
        self::assertSame('See and more', WikiPlainText::fromHtml('<p>See <span class="nowo-md-ref nowo-md-ref--hidden">Contenido no visible</span> and <span class=\'nowo-md-ref--hidden\'></span>more</p>'));
        self::assertSame('Kept', WikiPlainText::fromHtml('<p><span class="nowo-md-ref">Kept</span></p>'));
        // Audit NB-W-1: only a whole class token of the class attribute.
        self::assertSame('a b c d', WikiPlainText::fromHtml('<p><span class="foo-nowo-md-ref--hidden-x">a</span> <span class="nowo-md-ref--hidden-x">b</span> <span class="x-nowo-md-ref--hidden">c</span> <span data-class="nowo-md-ref--hidden">d</span></p>'));
        self::assertSame('e', WikiPlainText::fromHtml('<p><span class="a nowo-md-ref--hidden b">gone</span>e</p>'));
    }

    public function testDirectiveAttributeValuesAreNeverText(): void
    {
        $text = WikiPlainText::fromMarkdown(":::callout{icon=\"bulb\" type=\"info\"}\nUse a torch.\n:::");

        self::assertStringNotContainsString('bulb', $text);
        self::assertStringNotContainsString('info', $text);
    }
}
