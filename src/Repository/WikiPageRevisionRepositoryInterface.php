<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;

/**
 * Persistence port for wiki page revisions.
 */
interface WikiPageRevisionRepositoryInterface
{
    /**
     * Persists the revision and flushes it immediately.
     *
     * @param WikiPageRevision $revision revision to save
     */
    public function save(WikiPageRevision $revision): void;

    /**
     * Finds a revision by its identifier.
     *
     * @param string $id revision UUID
     *
     * @return WikiPageRevision|null the revision, or null when none exists
     */
    public function findById(string $id): ?WikiPageRevision;

    /**
     * Returns the revisions of a page, newest first.
     *
     * @param WikiPage $page page to read
     * @param int|null $limit maximum number of revisions (null = all)
     * @param int $offset number of newest revisions to skip
     *
     * @return list<WikiPageRevision>
     */
    public function findByPage(WikiPage $page, ?int $limit = null, int $offset = 0): array;

    /**
     * Finds a revision of a page by its revision number.
     *
     * @param WikiPage $page page the revision belongs to
     * @param int $number revision number
     *
     * @return WikiPageRevision|null the revision, or null when none matches
     */
    public function findByPageAndNumber(WikiPage $page, int $number): ?WikiPageRevision;

    /**
     * Returns the number to use for the next revision of a page (highest existing number plus one, 1 for a page without revisions).
     *
     * @param WikiPage $page page to inspect
     *
     * @return int next revision number
     */
    public function getNextRevisionNumber(WikiPage $page): int;
}
