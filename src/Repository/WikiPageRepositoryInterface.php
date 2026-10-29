<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;

/**
 * Persistence port for wiki pages.
 */
interface WikiPageRepositoryInterface
{
    /**
     * Persists the page and flushes it immediately.
     *
     * @param WikiPage $page page to save
     */
    public function save(WikiPage $page): void;

    /**
     * Finds a page by its identifier.
     *
     * @param string $id page UUID
     *
     * @return WikiPage|null the page, or null when none exists
     */
    public function findById(string $id): ?WikiPage;

    /**
     * Finds a page by slug inside a space (archived pages included).
     *
     * @param WikiSpace $space space to search
     * @param string $slug page slug
     *
     * @return WikiPage|null the page, or null when none matches
     */
    public function findBySlug(WikiSpace $space, string $slug): ?WikiPage;

    /**
     * Returns the non-archived pages of a space ordered by position, then title.
     *
     * @param WikiSpace $space space to read
     * @param int|null $limit maximum number of pages (null = all; list UIs always pass a cap)
     *
     * @return list<WikiPage>
     */
    public function findActiveBySpace(WikiSpace $space, ?int $limit = null): array;

    /**
     * Counts the pages of a space using the given slug, optionally ignoring one page (slug uniqueness checks).
     *
     * @param WikiSpace $space space to search
     * @param string $slug slug to look for
     * @param string|null $excludePageId page UUID to ignore (e.g. the page being edited), or null
     *
     * @return int number of matching pages
     */
    public function countBySpaceAndSlug(WikiSpace $space, string $slug, ?string $excludePageId = null): int;
}
