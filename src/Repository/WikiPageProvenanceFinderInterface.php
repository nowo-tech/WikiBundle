<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

/**
 * Spec 234: the provenance of several pages in a fixed number of queries (the API's page lists and bodies).
 * Implemented by the bundle's Doctrine revision repository; a host store that does not implement it gives `created_by`,
 * `channel` and `token_name` as null.
 */
interface WikiPageProvenanceFinderInterface
{
    /**
     * @param list<string> $pageIds page ids
     *
     * @return array<string, WikiPageProvenance> by page id; a page without revisions is missing
     */
    public function pageProvenance(array $pageIds): array;
}
