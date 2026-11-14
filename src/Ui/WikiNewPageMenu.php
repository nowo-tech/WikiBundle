<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ui;

use function is_array;
use function is_string;

/**
 * 1.35 (`web_ui.new_page_dialog`): the "New page above / below / as child" menu of the page tree. The server marks the
 * rows under which the user may create pages ({@see self::CHILD_ATTRIBUTE}: Edit on that page, as the API create and
 * the tree's moves ask of a parent); wiki.js offers "as child" on those rows, and "above" / "below" on rows whose
 * parent is marked or that sit at the top level (anyone with the create right). The server checks every create again.
 * Stateless: pure functions only.
 */
final class WikiNewPageMenu
{
    /** Id of the new-page dialog (`<dialog>`, UiKit `dialog` partial). */
    public const DIALOG_ID = 'nowo-wiki-new-page-dialog';

    /** Item attribute of a page the user may create pages under. */
    public const CHILD_ATTRIBUTE = 'data-wiki-new-child';

    /** The UiKit tree `actions_template`: the «…» button of each row. */
    public const ACTIONS_TEMPLATE = '@NowoWikiBundle/manage/_tree_new_page_button.html.twig';

    /**
     * Adds {@see self::CHILD_ATTRIBUTE} to the `item_attr` of the UiKit tree nodes whose id is in `$editable`, at any
     * depth; every other key of a node stays as it is.
     *
     * @param list<array<string, mixed>> $nodes UiKit tree nodes ({@see WikiPageFactory::uiTree()})
     * @param array<string, true> $editable ids of the pages the user may create pages under
     *
     * @return list<array<string, mixed>>
     */
    public static function annotate(array $nodes, array $editable): array
    {
        $out = [];
        foreach ($nodes as $node) {
            $id = $node['id'] ?? null;
            if (is_string($id) && isset($editable[$id])) {
                $attr                        = is_array($node['item_attr'] ?? null) ? $node['item_attr'] : [];
                $attr[self::CHILD_ATTRIBUTE] = '1';
                $node['item_attr']           = $attr;
            }
            if (is_array($node['children'] ?? null) && $node['children'] !== []) {
                /** @var list<array<string, mixed>> $children */
                $children         = $node['children'];
                $node['children'] = self::annotate($children, $editable);
            }
            $out[] = $node;
        }

        return $out;
    }
}
