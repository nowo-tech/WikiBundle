<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageLink;

/**
 * Persistence of {@see WikiPageLink} (1.9).
 *
 * Persistence adapter of the bundle's own entities, not a host port (REQ-ARCH-001): it is entity-typed by design;
 * hosts reach wiki data through the services, the id operations and the events.
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
