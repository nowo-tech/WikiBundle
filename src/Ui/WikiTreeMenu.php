<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ui;

use function is_array;
use function is_string;

/**
 * 1.39 (`tree.context_menu`): the page tree's context menu (right click, Shift+F10 / the ContextMenu key, the row's
 * «…» button, 1.41: a long press): Rename, Change icon / emoji, Add child page, Add sibling page, Copy link. Each row is
 * marked with what the menu may offer there; "Add child / sibling" reuse the 1.35 marks of {@see WikiNewPageMenu}. A
 * row without a mark gets no menu (readers keep the browser's own context menu). The server checks every write again.
 * Stateless.
 *
 * 1.41: also Open in new tab, Edit ({@see self::EDIT_HREF_ATTRIBUTE}), New page above / below (two entries), Duplicate
 * (the sibling mark and the tree's duplicate endpoint), Move up / down / to… ({@see self::MOVE_ATTRIBUTE}), Archive
 * ({@see self::ARCHIVE_ATTRIBUTE}) and Delete ({@see self::DELETE_ATTRIBUTE}, with the trash). The marks follow the
 * user's rights in the space (one check per page view, audit PT-L4); a page-level refusal comes from the endpoint.
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

    /** 1.41: item attribute of a row that offers Move up / down / to… (a movable tree). */
    public const MOVE_ATTRIBUTE = 'data-wiki-menu-move';

    /** 1.41: item attribute of a row that offers Archive. */
    public const ARCHIVE_ATTRIBUTE = 'data-wiki-menu-archive';

    /** 1.41: item attribute of a row that offers Delete (`trash.enabled`). */
    public const DELETE_ATTRIBUTE = 'data-wiki-menu-delete';

    /** 1.41: item attribute holding the address of the page's edit form (Edit). */
    public const EDIT_HREF_ATTRIBUTE = 'data-wiki-edit-href';

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
        return self::mark($nodes, [self::EDIT_ATTRIBUTE], $icon);
    }

    /**
     * 1.41: marks every UiKit tree node (any depth) with each attribute of `$flags`, with {@see self::ICON_ATTRIBUTE}
     * when `$icon`, and with its edit address when `$editHrefs` holds one for its id; every other key stays as it is.
     *
     * @param list<array<string, mixed>> $nodes UiKit tree nodes
     * @param list<string> $flags attributes set to "1" on every row
     * @param array<string, string> $editHrefs page id => edit form address
     *
     * @return list<array<string, mixed>>
     */
    public static function mark(array $nodes, array $flags, bool $icon, array $editHrefs = []): array
    {
        $out = [];
        foreach ($nodes as $node) {
            if (is_string($id = $node['id'] ?? null)) {
                $attr = is_array($node['item_attr'] ?? null) ? $node['item_attr'] : [];
                foreach ($flags as $flag) {
                    $attr[$flag] = '1';
                }
                if (isset($editHrefs[$id])) {
                    $attr[self::EDIT_HREF_ATTRIBUTE] = $editHrefs[$id];
                }
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
                $node['children'] = self::mark($children, $flags, $icon, $editHrefs);
            }
            $out[] = $node;
        }

        return $out;
    }
}
