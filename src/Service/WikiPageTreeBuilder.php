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
     * @param list<WikiPage> $pages
     *
     * @return list<array{page: WikiPage, children: list<mixed>}>
     */
    public function build(array $pages): array
    {
        /** @var array<string, array{page: WikiPage, children: list<array{page: WikiPage, children: list<mixed>}>}> $byId */
        $byId = [];
        foreach ($pages as $page) {
            $byId[$page->getId()] = ['page' => $page, 'children' => []];
        }

        foreach ($pages as $page) {
            $parentId = $page->getParent()?->getId();
            if ($parentId !== null && isset($byId[$parentId])) {
                $byId[$parentId]['children'][] = $byId[$page->getId()];
            }
        }

        $roots = [];
        foreach ($pages as $page) {
            $parentId = $page->getParent()?->getId();
            if ($parentId === null || !isset($byId[$parentId])) {
                $roots[] = $byId[$page->getId()];
            }
        }

        return $roots;
    }
}
