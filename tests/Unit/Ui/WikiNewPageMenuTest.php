<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Ui;

use Nowo\WikiBundle\Ui\WikiNewPageMenu;
use PHPUnit\Framework\TestCase;

/**
 * 1.35: the tree nodes of the new-page menu mark the pages the user may create pages under.
 */
final class WikiNewPageMenuTest extends TestCase
{
    public function testMarksTheEditableNodesAtAnyDepthAndKeepsTheRest(): void
    {
        $nodes = [
            ['id' => 'a', 'label' => 'A', 'children' => [
                ['id' => 'a1', 'label' => 'A1', 'children' => []],
                ['id' => 'a2', 'label' => 'A2', 'item_attr' => ['class' => 'x'], 'children' => []],
            ]],
            ['id' => 'b', 'label' => 'B', 'children' => []],
            ['label' => 'no id'],
        ];

        $out = WikiNewPageMenu::annotate($nodes, ['a' => true, 'a2' => true]);

        self::assertSame(['data-wiki-new-child' => '1'], $out[0]['item_attr']);
        self::assertArrayNotHasKey('item_attr', $out[0]['children'][0]);
        self::assertSame(['class' => 'x', 'data-wiki-new-child' => '1'], $out[0]['children'][1]['item_attr']);
        self::assertArrayNotHasKey('item_attr', $out[1]);
        self::assertSame(['label' => 'no id'], $out[2]);
        self::assertSame('A1', $out[0]['children'][0]['label']);
        self::assertSame([], WikiNewPageMenu::annotate([], ['a' => true]));
        self::assertSame('nowo-wiki-new-page-dialog', WikiNewPageMenu::DIALOG_ID);
    }
}
