<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Service;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Service\WikiPageTreeBuilder;
use PHPUnit\Framework\TestCase;

final class WikiPageTreeBuilderTest extends TestCase
{
    public function testBuildsNestedTree(): void
    {
        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $root  = new WikiPage($space, 'index', 'Index');
        $child = new WikiPage($space, 'child', 'Child', $root);

        $tree = (new WikiPageTreeBuilder())->build([$root, $child]);

        self::assertCount(1, $tree);
        self::assertSame('Index', $tree[0]['page']->getTitle());
        self::assertCount(1, $tree[0]['children']);
        self::assertSame('Child', $tree[0]['children'][0]['page']->getTitle());
    }

    public function testKeepsGrandchildren(): void
    {
        $space      = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $root       = new WikiPage($space, 'index', 'Index');
        $child      = new WikiPage($space, 'child', 'Child', $root);
        $grandchild = new WikiPage($space, 'grandchild', 'Grandchild', $child);

        foreach ([[$root, $child, $grandchild], [$grandchild, $child, $root]] as $pages) {
            $tree = (new WikiPageTreeBuilder())->build($pages);

            self::assertCount(1, $tree);
            self::assertCount(1, $tree[0]['children']);
            self::assertCount(1, $tree[0]['children'][0]['children']);
            self::assertSame('Grandchild', $tree[0]['children'][0]['children'][0]['page']->getTitle());
        }
    }
}
