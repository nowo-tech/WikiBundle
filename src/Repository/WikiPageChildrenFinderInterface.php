<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Entity\WikiPage;

/**
 * Optional lookup of a page repository (1.30, `web_ui.children`): the direct sub-pages of a page in one query.
 * {@see DoctrineOrmWikiPageRepository} implements it; with a host repository without it the sub-pages are taken from
 * {@see WikiPageRepositoryInterface::findActiveBySpace()} (one query too).
 */
interface WikiPageChildrenFinderInterface
{
    /**
     * The active (not archived) direct sub-pages of a page, in tree order (position, then title).
     *
     * @param WikiPage $parent the page
     * @param int $limit maximum number of pages
     * @param int $offset pages to skip (the next batch when the caller filtered the previous one)
     *
     * @return list<WikiPage>
     */
    public function findActiveChildren(WikiPage $parent, int $limit, int $offset = 0): array;
}
