<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Ui;

use Nowo\WikiBundle\Ui\WikiNewPageMenu;
use PHPUnit\Framework\TestCase;

/**
 * 1.35: the tree nodes of the new-page menu: "as child" everywhere, "above / below" but on top-level orphans.
 */
final class WikiNewPageMenuTest extends TestCase
{
    public function testMarksEveryNodeAndLeavesOrphansWithoutSiblings(): void
    {
        $nodes = [
            ['id' => 'a', 'label' => 'A', 'children' => [
                ['id' => 'a1', 'label' => 'A1', 'children' => []],
                ['id' => 'a2', 'label' => 'A2', 'item_attr' => ['class' => 'x'], 'children' => []],
            ]],
            ['id' => 'o', 'label' => 'Orphan', 'children' => []],
            ['label' => 'no id'],
        ];

        $out  = WikiNewPageMenu::annotate($nodes, ['o' => true]);
        $both = ['data-wiki-new-child' => '1', 'data-wiki-new-sibling' => '1'];

        self::assertSame($both, $out[0]['item_attr']);
        self::assertSame($both, $out[0]['children'][0]['item_attr']);
        self::assertSame(['class' => 'x'] + $both, $out[0]['children'][1]['item_attr']);
        self::assertSame(['data-wiki-new-child' => '1'], $out[1]['item_attr']);
        self::assertSame(['label' => 'no id'], $out[2]);
        self::assertSame('A1', $out[0]['children'][0]['label']);
        self::assertSame([], WikiNewPageMenu::annotate([]));
        self::assertSame('nowo-wiki-new-page-dialog', WikiNewPageMenu::DIALOG_ID);
    }
}
