<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Ui;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Service\WikiPageTreeBuilder;
use Nowo\WikiBundle\Ui\WikiPageNav;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * 1.39 (web_ui.page_nav): reading order (depth first) and neighbours from the permission-filtered tree.
 */
final class WikiPageNavTest extends TestCase
{
    private WikiSpace $space;

    protected function setUp(): void
    {
        $this->space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, '1');
    }

    private function page(string $slug, ?WikiPage $parent = null): WikiPage
    {
        return new WikiPage($this->space, $slug, $slug, $parent);
    }

    public function testReadingOrderIsDepthFirst(): void
    {
        $intro    = $this->page('intro');
        $vars     = $this->page('vars');
        $byType   = $this->page('by-type', $vars);
        $lists    = $this->page('lists', $vars);
        $deep     = $this->page('deep', $byType);
        $appendix = $this->page('appendix');
        $tree     = [
            ['page' => $intro, 'children' => []],
            ['page' => $vars, 'children' => [
                ['page' => $byType, 'children' => [['page' => $deep, 'children' => []]]],
                ['page' => $lists, 'children' => []],
            ]],
            ['page' => $appendix, 'children' => []],
            'junk',
            ['page' => $intro, 'children' => []],
        ];

        self::assertSame(['intro', 'vars', 'by-type', 'deep', 'lists', 'appendix'], array_map(static fn (WikiPage $p): string => $p->getSlug(), WikiPageNav::readingOrder($tree)));
        self::assertSame(['prev' => $deep, 'next' => $appendix], WikiPageNav::neighbours($tree, $lists));
        self::assertSame(['prev' => $byType, 'next' => $lists], WikiPageNav::neighbours($tree, $deep));
    }

    public function testNothingAtTheEndsNorForAPageOutsideTheTree(): void
    {
        $first = $this->page('first');
        $last  = $this->page('last');
        $tree  = [['page' => $first, 'children' => []], ['page' => $last, 'children' => []]];

        self::assertSame(['prev' => null, 'next' => $last], WikiPageNav::neighbours($tree, $first));
        self::assertSame(['prev' => $first, 'next' => null], WikiPageNav::neighbours($tree, $last));
        self::assertSame(['prev' => null, 'next' => null], WikiPageNav::neighbours($tree, $this->page('other')));
        self::assertSame(['prev' => null, 'next' => null], WikiPageNav::neighbours([], $first));
    }

    public function testHiddenPagesAreSkippedBecauseTheTreeLeavesThemOut(): void
    {
        // The controller builds the tree from the readable active pages only: a hidden page is not in the list.
        $a      = $this->page('a');
        $hidden = $this->page('hidden');
        $b      = $this->page('b');
        $tree   = (new WikiPageTreeBuilder())->build([$a, $b]);

        self::assertSame(['prev' => $a, 'next' => null], WikiPageNav::neighbours($tree, $b));
        self::assertNotContains($hidden, WikiPageNav::readingOrder($tree));
    }
}
