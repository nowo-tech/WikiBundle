<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Markdown;

use Nowo\WikiBundle\Markdown\WikiHeadingOutline;
use PHPUnit\Framework\TestCase;

use function array_column;
use function count;
use function str_repeat;

/**
 * 1.40: the sections of rendered page HTML — valid prefixed ids only, first occurrence wins, own text only (no page
 * titles, mentions, icons or math), top-level only on request, bounded.
 */
final class WikiHeadingOutlineTest extends TestCase
{
    public function testHtmlWithoutHeadingsHasNoSections(): void
    {
        self::assertSame([], WikiHeadingOutline::fromHtml(''));
        self::assertSame([], WikiHeadingOutline::fromHtml('<p>Text</p>'));
    }

    public function testSectionsWithLevelsAndOwnText(): void
    {
        $html = '<h1 id="nowo-h-intro">Intro</h1>'
            . '<h2 id="nowo-h-setup">Set <code>up</code> <a class="nowo-md-ref" href="/x">Other page</a> <span class="nowo-md-mention">@ana</span>'
            . '<span class="nowo-md-icon" data-icon="home"></span><span class="nowo-md-math">x^2</span> <strong>now</strong></h2>'
            . '<h3 id="nowo-h-setup">Duplicate</h3><h3 id="Bad Id">Invalid</h3><h3>No id</h3><h4 id="setup2">No prefix</h4>'
            . '<h5 id="nowo-h-empty"><span class="nowo-md-ref nowo-md-ref--hidden"></span></h5>'
            . '<h6 id="nowo-h-a&amp;b">Entity</h6>';

        self::assertSame([
            ['id' => 'intro', 'level' => 1, 'text' => 'Intro'],
            ['id' => 'setup', 'level' => 2, 'text' => 'Set up now'],
        ], WikiHeadingOutline::fromHtml($html));
    }

    public function testNestedHeadingsOnlyWithoutTheTopLevelRule(): void
    {
        $html = '<h2 id="nowo-h-top">Top</h2><div class="nowo-md-callout"><h3 id="nowo-h-inside">Inside</h3></div>'
            . '<details><summary>More</summary><h3 id="nowo-h-hidden">Hidden</h3></details>';

        self::assertSame(['top', 'inside', 'hidden'], array_column(WikiHeadingOutline::fromHtml($html), 'id'));
        self::assertSame(['top'], array_column(WikiHeadingOutline::fromHtml($html, true), 'id'));
    }

    public function testUnicodeTextAndIds(): void
    {
        self::assertSame([['id' => 'año-nuevo', 'level' => 2, 'text' => 'Año nuevo']], WikiHeadingOutline::fromHtml('<h2 id="nowo-h-año-nuevo">Año  nuevo</h2>'));
    }

    public function testTheOutlineIsBounded(): void
    {
        $html = '';
        for ($i = 1; $i <= WikiHeadingOutline::MAX_SECTIONS + 5; ++$i) {
            $html .= '<h2 id="nowo-h-s' . $i . '">' . str_repeat('x', 300) . '</h2>';
        }
        $sections = WikiHeadingOutline::fromHtml($html);

        self::assertCount(WikiHeadingOutline::MAX_SECTIONS, $sections);
        self::assertSame(WikiHeadingOutline::MAX_TEXT, mb_strlen($sections[0]['text']));

        $deep = str_repeat('<div>', WikiHeadingOutline::MAX_DEPTH + 5) . '<h2 id="nowo-h-deep">Deep</h2>' . str_repeat('</div>', WikiHeadingOutline::MAX_DEPTH + 5);
        self::assertSame([], WikiHeadingOutline::fromHtml($deep));
        $deepText = '<h2 id="nowo-h-t">A' . str_repeat('<em>', WikiHeadingOutline::MAX_DEPTH + 5) . 'B' . str_repeat('</em>', WikiHeadingOutline::MAX_DEPTH + 5) . '</h2>';
        self::assertSame('A', WikiHeadingOutline::fromHtml($deepText)[0]['text']);
        self::assertSame(1, count(WikiHeadingOutline::fromHtml($deepText)));
    }
}
