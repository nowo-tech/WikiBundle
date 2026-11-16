<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Ui;

use Nowo\WikiBundle\Ui\WikiTreeMenu;
use PHPUnit\Framework\TestCase;

/**
 * 1.39 (tree.context_menu): the row marks of the tree menu.
 */
final class WikiTreeMenuTest extends TestCase
{
    public function testMarksEveryRowAndKeepsTheOtherKeys(): void
    {
        $nodes = [
            ['id' => 'a', 'label' => 'A', 'page_icon' => 'emoji:🚀', 'item_attr' => ['data-x' => '1'], 'children' => [
                ['id' => 'b', 'label' => 'B', 'page_icon' => null, 'children' => []],
            ]],
            ['label' => 'no id'],
        ];

        $marked = WikiTreeMenu::annotate($nodes, true);
        self::assertSame(['data-x' => '1', 'data-wiki-menu-edit' => '1', 'data-wiki-menu-icon' => '1', 'data-wiki-icon' => 'emoji:🚀'], $marked[0]['item_attr']);
        self::assertSame(['data-wiki-menu-edit' => '1', 'data-wiki-menu-icon' => '1', 'data-wiki-icon' => ''], $marked[0]['children'][0]['item_attr']);
        self::assertSame(['label' => 'no id'], $marked[1]);

        $noIcon = WikiTreeMenu::annotate($nodes, false);
        self::assertSame(['data-x' => '1', 'data-wiki-menu-edit' => '1'], $noIcon[0]['item_attr']);
    }
}
