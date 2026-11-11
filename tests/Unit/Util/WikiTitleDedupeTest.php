<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Util;

use Nowo\WikiBundle\Util\WikiTitleDedupe;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 1.29 web_ui.page_header.title: dedupe — only a leading <h1> whose normalised text equals the title is dropped.
 */
final class WikiTitleDedupeTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function bodies(): iterable
    {
        yield 'exact' => ['<h1>Deploy runbook</h1><p>Steps</p>', 'Deploy runbook', '<p>Steps</p>'];
        yield 'case, spaces, inline markup' => ["\n <h1 class=\"x\">  deploy <em>RUNBOOK</em>\n</h1>\n<p>Steps</p>", 'Deploy  Runbook', '<p>Steps</p>'];
        // MA-I1: the ids of the heading (and inside it) stay as empty anchors; an empty id is dropped.
        yield 'ids kept' => ['<h1 id="nowo-h-deploy">Deploy <a id=\'top&amp;"x\'></a></h1><p>Steps</p>', 'Deploy', '<sup id="nowo-h-deploy" class="nowo-wiki-anchor"></sup><sup id="top&amp;&quot;x" class="nowo-wiki-anchor"></sup><p>Steps</p>'];
        yield 'empty id' => ['<h1 id="">T</h1><p>x</p>', 'T', '<p>x</p>'];
        // MA-I1: a heading with more than text is kept.
        yield 'image' => ['<h1>Deploy <img src="/logo.png" alt=""></h1><p>x</p>', 'Deploy', '<h1>Deploy <img src="/logo.png" alt=""></h1><p>x</p>'];
        yield 'math' => ['<h1><math><mi>T</mi></math></h1>', 'T', '<h1><math><mi>T</mi></math></h1>'];
        yield 'entities' => ['<h1>Q&amp;A</h1><p>x</p>', 'Q&A', '<p>x</p>'];
        yield 'after a comment' => ['<!-- x --><h1>Title</h1><p>x</p>', 'Title', '<p>x</p>'];
        yield 'other words' => ['<h1>Deploy runbook v2</h1><p>x</p>', 'Deploy runbook', '<h1>Deploy runbook v2</h1><p>x</p>'];
        yield 'h2' => ['<h2>Deploy runbook</h2><p>x</p>', 'Deploy runbook', '<h2>Deploy runbook</h2><p>x</p>'];
        yield 'not first' => ['<p>Intro</p><h1>Deploy runbook</h1>', 'Deploy runbook', '<p>Intro</p><h1>Deploy runbook</h1>'];
        yield 'empty title' => ['<h1></h1><p>x</p>', '  ', '<h1></h1><p>x</p>'];
        yield 'only the first' => ['<h1>T</h1><h1>T</h1>', 'T', '<h1>T</h1>'];
        yield 'empty body' => ['', 'T', ''];
    }

    #[DataProvider('bodies')]
    public function testStrip(string $html, string $title, string $expected): void
    {
        self::assertSame($expected, WikiTitleDedupe::strip($html, $title));
        // 1.30 (title: body): the same match tells whether the body starts with the title.
        self::assertSame($expected !== $html, WikiTitleDedupe::startsWithTitle($html, $title));
    }

    public function testNormalise(): void
    {
        self::assertSame('a b c', WikiTitleDedupe::normalise("  A\t b\nC "));
        self::assertSame('ñandú', WikiTitleDedupe::normalise('ÑANDÚ'));
        // Invalid UTF-8 never matches anything.
        self::assertSame('', WikiTitleDedupe::normalise("\xff"));
    }
}
