<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ui;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Service\WikiPageTreeBuilder;

use function is_array;

/**
 * 1.39 (`web_ui.page_nav`): the previous and next page of the page view in the tree's reading order, depth first (a
 * page, its children in order, then its next sibling). It walks the sidebar tree the view already built (readable,
 * active pages only, capped by `pagination.max_tree_pages`): no query, and a page the viewer may not open is never a
 * neighbour. Stateless: pure functions only.
 */
final class WikiPageNav
{
    /** Values of `web_ui.page_nav` that print the links above the content. */
    public const TOP = ['top', 'both'];

    /** Values of `web_ui.page_nav` that print the links after the content. */
    public const BOTTOM = ['bottom', 'both'];

    /**
     * The pages of the tree in reading order.
     *
     * @param list<array{page: WikiPage, children: list<mixed>}> $tree nodes of {@see WikiPageTreeBuilder::build()}
     *
     * @return list<WikiPage>
     */
    public static function readingOrder(array $tree): array
    {
        $out  = [];
        $seen = [];
        // Depth-first walk (the tree's depth is bounded by tree.max_depth); a page met twice is kept once.
        $walk = static function (array $nodes) use (&$walk, &$out, &$seen): void {
            foreach ($nodes as $node) {
                if (!is_array($node) || !($node['page'] ?? null) instanceof WikiPage) {
                    continue;
                }
                $page = $node['page'];
                if (isset($seen[$page->getId()])) {
                    continue;
                }
                $seen[$page->getId()] = true;
                $out[]                = $page;
                $children             = $node['children'] ?? [];
                if (is_array($children) && $children !== []) {
                    $walk($children);
                }
            }
        };
        $walk($tree);

        return $out;
    }

    /**
     * The neighbours of a page in reading order; both null when the page is not in the tree.
     *
     * @param list<array{page: WikiPage, children: list<mixed>}> $tree
     *
     * @return array{prev: WikiPage|null, next: WikiPage|null}
     */
    public static function neighbours(array $tree, WikiPage $current): array
    {
        $order = self::readingOrder($tree);
        foreach ($order as $index => $page) {
            if ($page->getId() === $current->getId()) {
                return ['prev' => $order[$index - 1] ?? null, 'next' => $order[$index + 1] ?? null];
            }
        }

        return ['prev' => null, 'next' => null];
    }
}
