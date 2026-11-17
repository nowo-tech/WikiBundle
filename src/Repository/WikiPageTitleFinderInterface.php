<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Entity\WikiPage;

/**
 * 1.40: title-only page lookups of the link picker (contract "section anchors and link picker" §3.1) and the current
 * HTML of pages for section labels, each in one query. {@see DoctrineOrmWikiPageRepository} implements it; spaces and
 * pages are named by id, so it works the same with attribute and XML mapping (standard and DDD hosts). A host page
 * repository without it is read through {@see WikiPageRepositoryInterface} (slower, same answers).
 *
 * Persistence adapter of the bundle's own entities, not a host port (REQ-ARCH-001): it is entity-typed by design;
 * hosts reach wiki data through the services, the id operations and the events. Permissions are the caller's job.
 */
interface WikiPageTitleFinderInterface
{
    /**
     * Non-archived pages of the spaces whose title contains `$query` (case-insensitive, matched literally: `%`, `_` and
     * the escape character are escaped), titles that start with it first, then by title and id. An empty query gives the
     * most recently changed pages.
     *
     * @param list<string> $spaceIds ids of the spaces to read (empty: no pages)
     * @param string $query text to look for in titles (never in the content)
     * @param int $limit most rows read
     *
     * @return list<WikiPage>
     */
    public function findByTitle(array $spaceIds, string $query, int $limit): array;

    /**
     * The viewer-neutral `content_html` cache of the current revision of each page (pages without a revision are left
     * out).
     *
     * @param list<string> $pageIds page ids
     *
     * @return array<string, string> page id => current revision HTML
     */
    public function currentHtml(array $pageIds): array;
}
