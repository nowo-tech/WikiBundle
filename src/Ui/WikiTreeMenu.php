<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ui;

use function is_array;
use function is_string;

/**
 * 1.39 (`tree.context_menu`): the page tree's context menu (right click, Shift+F10 / the ContextMenu key, the row's
 * «…» button): Rename, Change icon / emoji, Add child page, Add sibling page, Copy link. Each row is marked with what
 * the menu may offer there; "Add child / sibling" reuse the 1.35 marks of {@see WikiNewPageMenu}. A row without a mark
 * gets no menu (readers keep the browser's own context menu). The server checks every write again. Stateless.
 */
final class WikiTreeMenu
{
    /** Id of the menu element. */
    public const MENU_ID = 'nowo-wiki-tree-menu';

    /** Item attribute of a row that offers Rename. */
    public const EDIT_ATTRIBUTE = 'data-wiki-menu-edit';

    /** Item attribute of a row that offers Change icon / emoji (`page_icon.form_field`). */
    public const ICON_ATTRIBUTE = 'data-wiki-menu-icon';

    /** Item attribute holding the row's current icon value (with {@see self::ICON_ATTRIBUTE}). */
    public const ICON_VALUE_ATTRIBUTE = 'data-wiki-icon';

    /** The UiKit tree `actions_template`: the «…» button of each row. */
    public const ACTIONS_TEMPLATE = '@NowoWikiBundle/manage/_tree_menu_button.html.twig';

    /**
     * Marks every UiKit tree node (any depth) with {@see self::EDIT_ATTRIBUTE} and, when `$icon`, with
     * {@see self::ICON_ATTRIBUTE}; every other key of a node stays as it is.
     *
     * @param list<array<string, mixed>> $nodes UiKit tree nodes
     *
     * @return list<array<string, mixed>>
     */
    public static function annotate(array $nodes, bool $icon): array
    {
        $out = [];
        foreach ($nodes as $node) {
            if (is_string($node['id'] ?? null)) {
                $attr                       = is_array($node['item_attr'] ?? null) ? $node['item_attr'] : [];
                $attr[self::EDIT_ATTRIBUTE] = '1';
                if ($icon) {
                    $attr[self::ICON_ATTRIBUTE] = '1';
                    // The current icon, the value the "Change icon" dialog opens with.
                    $attr[self::ICON_VALUE_ATTRIBUTE] = is_string($node['page_icon'] ?? null) ? $node['page_icon'] : '';
                }
                $node['item_attr'] = $attr;
            }
            if (is_array($node['children'] ?? null) && $node['children'] !== []) {
                /** @var list<array<string, mixed>> $children */
                $children         = $node['children'];
                $node['children'] = self::annotate($children, $icon);
            }
            $out[] = $node;
        }

        return $out;
    }
}
