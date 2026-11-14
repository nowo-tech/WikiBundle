<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ui;

use function is_array;
use function is_string;

/**
 * 1.35 (`web_ui.new_page_dialog`): the "New page above / below / as child" menu of the page tree. The menu is shown to
 * users who may create pages and edit in the space (one check per page, audit PT-L4); the server checks every create
 * again against the page itself. Each row is marked with what the menu may offer there: {@see self::CHILD_ATTRIBUTE}
 * ("as child") on every row, {@see self::SIBLING_ATTRIBUTE} ("above" / "below") on every row but a top-level row whose
 * page has a parent (archived: it only looks like a root page; the server refuses a place next to it). Stateless: pure functions only.
 */
final class WikiNewPageMenu
{
    /** Id of the new-page dialog (`<dialog>`, UiKit `dialog` partial). */
    public const DIALOG_ID = 'nowo-wiki-new-page-dialog';

    /** Item attribute of a row that offers "as child". */
    public const CHILD_ATTRIBUTE = 'data-wiki-new-child';

    /** Item attribute of a row that offers "above" / "below". */
    public const SIBLING_ATTRIBUTE = 'data-wiki-new-sibling';

    /** The UiKit tree `actions_template`: the «…» button of each row. */
    public const ACTIONS_TEMPLATE = '@NowoWikiBundle/manage/_tree_new_page_button.html.twig';

    /**
     * Marks every UiKit tree node (any depth) with {@see self::CHILD_ATTRIBUTE}, and with {@see self::SIBLING_ATTRIBUTE}
     * unless its id is in `$orphans`; every other key of a node stays as it is.
     *
     * @param list<array<string, mixed>> $nodes UiKit tree nodes ({@see WikiPageFactory::uiTree()})
     * @param array<string, true> $orphans ids of the top-level rows whose page has a parent (an archived one)
     *
     * @return list<array<string, mixed>>
     */
    public static function annotate(array $nodes, array $orphans = []): array
    {
        $out = [];
        foreach ($nodes as $node) {
            $id = $node['id'] ?? null;
            if (is_string($id)) {
                $attr                        = is_array($node['item_attr'] ?? null) ? $node['item_attr'] : [];
                $attr[self::CHILD_ATTRIBUTE] = '1';
                if (!isset($orphans[$id])) {
                    $attr[self::SIBLING_ATTRIBUTE] = '1';
                }
                $node['item_attr'] = $attr;
            }
            if (is_array($node['children'] ?? null) && $node['children'] !== []) {
                /** @var list<array<string, mixed>> $children */
                $children         = $node['children'];
                $node['children'] = self::annotate($children);
            }
            $out[] = $node;
        }

        return $out;
    }
}
