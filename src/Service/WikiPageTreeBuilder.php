<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Nowo\WikiBundle\Entity\WikiPage;

/**
 * Builds a nested navigation tree from flat page lists.
 */
final class WikiPageTreeBuilder
{
    /**
     * Nests the pages under their parents (input order kept); pages whose parent is absent from the list become roots.
     *
     * @param list<WikiPage> $pages flat list of pages of one space
     *
     * @return list<array{page: WikiPage, children: list<mixed>}>
     */
    public function build(array $pages): array
    {
        /** @var array<string, true> $ids */
        $ids = [];
        foreach ($pages as $page) {
            $ids[$page->getId()] = true;
        }

        /** @var array<string, list<WikiPage>> $childrenByParent */
        $childrenByParent = [];
        $roots            = [];
        foreach ($pages as $page) {
            $parentId = $page->getParent()?->getId();
            if ($parentId !== null && isset($ids[$parentId]) && $parentId !== $page->getId()) {
                $childrenByParent[$parentId][] = $page;
            } else {
                $roots[] = $page;
            }
        }

        $tree = [];
        foreach ($roots as $root) {
            $tree[] = $this->node($root, $childrenByParent);
        }

        return $tree;
    }

    /**
     * Builds one node with its descendants (input order kept). Each page has a single parent, so a
     * node reachable from a root cannot be part of a parent cycle.
     *
     * @param array<string, list<WikiPage>> $childrenByParent
     *
     * @return array{page: WikiPage, children: list<mixed>}
     */
    private function node(WikiPage $page, array $childrenByParent): array
    {
        $children = [];
        foreach ($childrenByParent[$page->getId()] ?? [] as $child) {
            $children[] = $this->node($child, $childrenByParent);
        }

        return ['page' => $page, 'children' => $children];
    }
}
