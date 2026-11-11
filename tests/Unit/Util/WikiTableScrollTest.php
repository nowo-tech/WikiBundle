<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Util;

use Nowo\WikiBundle\Util\WikiTableScroll;
use PHPUnit\Framework\TestCase;

/**
 * 1.29 (audit MA-L1): each table of a sanitized body in a labelled scroll box; the table itself is untouched.
 */
final class WikiTableScrollTest extends TestCase
{
    private const BOX = '<div class="nowo-wiki-table-scroll" data-nowo-ui-scroll-region data-nowo-ui-scroll-label="Table">';

    public function testEveryTableGetsItsOwnBox(): void
    {
        self::assertSame('<p>x</p>' . self::BOX . '<table class="t"><tr><td>1</td></tr></table></div>', WikiTableScroll::wrap('<p>x</p><table class="t"><tr><td>1</td></tr></table>', 'Table'));
        // Nested and upper case (balanced: one box per table; tags written in lower case), "<tablex" and escaped text are not tables.
        self::assertSame(
            self::BOX . '<table><tr><td>' . self::BOX . '<table><tr><td>2</td></tr></table></div></td></tr></table></div><tablex></tablex><p>&lt;table&gt;</p>',
            WikiTableScroll::wrap('<TABLE><tr><td><table><tr><td>2</td></tr></table></td></tr></table ><tablex></tablex><p>&lt;table&gt;</p>', 'Table'),
        );
        self::assertSame('<p>no table</p>', WikiTableScroll::wrap('<p>no table</p>', 'Table'));
    }

    public function testTheLabelIsEscaped(): void
    {
        self::assertStringContainsString('data-nowo-ui-scroll-label="&quot;&gt;&lt;x&gt;"', WikiTableScroll::wrap('<table></table>', '"><x>'));
    }
}
