<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageLink;

/**
 * Persistence of {@see WikiPageLink} (1.9).
 */
interface WikiPageLinkRepositoryInterface
{
    /** Persists and flushes a link. */
    public function save(WikiPageLink $link): void;

    /** Removes and flushes a link. */
    public function remove(WikiPageLink $link): void;

    /** Returns the link of the page to the object, or null. */
    public function findOne(WikiPage $page, string $objectType, string $objectId): ?WikiPageLink;

    /**
     * Links of active (not archived) pages to one host object, page and space fetched, newest first.
     *
     * @return list<WikiPageLink>
     */
    public function findByHostObject(string $objectType, string $objectId): array;

    /**
     * Links of active pages to several host objects of one type, in one query.
     *
     * @param list<string> $objectIds
     *
     * @return list<WikiPageLink>
     */
    public function findByHostObjects(string $objectType, array $objectIds): array;

    /**
     * Links of a page, oldest first.
     *
     * @return list<WikiPageLink>
     */
    public function findByPage(WikiPage $page): array;
}
