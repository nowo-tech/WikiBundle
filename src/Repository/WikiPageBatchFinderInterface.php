<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Entity\WikiPage;

/**
 * Optional batch lookup of a page repository (1.13): one query for every page id of a document (page-link
 * resolution). {@see DoctrineOrmWikiPageRepository} implements it; a host repository without it is read one id at a
 * time through {@see WikiPageRepositoryInterface::findById()}.
 */
interface WikiPageBatchFinderInterface
{
    /**
     * Finds the pages of the given ids (archived pages included; unknown ids are left out).
     *
     * @param list<string> $ids page ids
     *
     * @return list<WikiPage> the pages found, in no particular order
     */
    public function findByIds(array $ids): array;
}
