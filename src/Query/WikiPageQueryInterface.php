<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Query;

use InvalidArgumentException;
use Nowo\WikiBundle\Security\WikiPageAccessInterface;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * 1.51: the pages an application lists, counts, exports, searches and links back to, without writing queries over the
 * wiki's tables (public service, SemVer; REQ-ARCH-001 rule 1, DDD-host checklist B7). Framework-free: ids and criteria
 * in, snapshots and plain values out, never an entity.
 *
 * - **Access.** The list, count, tree and content reads answer what is stored, with no access decision: show a person
 *   only what {@see WikiPageAccessInterface} (or your own rules) lets them read. {@see self::backlinks()} and
 *   {@see self::searchPages()} take an optional viewer and then return only the pages that person may read (the page
 *   view's rule), before the limit.
 * - **Exact ids.** Ids and owners read back are compared byte for byte in PHP after the SQL (house rule LQ).
 * - **Stateless and read-only.** Nothing is kept between calls (FrankenPHP worker mode with the kernel kept alive) and no
 *   row is locked or written. Lists, counts, trees and contents are read as columns; the search and the viewer checks
 *   load pages into the wiki's entity manager (the private one when it is on) like the wiki's own pages do.
 */
interface WikiPageQueryInterface
{
    /**
     * The pages matching the criteria, in its order, one page of results.
     *
     * @param int $offset rows skipped
     * @param int $limit rows returned at most (1 to 500)
     *
     * @return list<WikiPageSnapshot>
     *
     * @throws InvalidArgumentException when the offset or limit is out of bounds
     */
    public function findPages(WikiPageCriteria $criteria, int $offset = 0, int $limit = 100): array;

    /**
     * How many pages match the criteria.
     */
    public function countPages(WikiPageCriteria $criteria): int;

    /**
     * The pages with these ids (unknown ids are left out), any size of list.
     *
     * @param list<string> $pageIds
     *
     * @return array<string, WikiPageSnapshot> by page id, in the order asked
     */
    public function findPagesByIds(array $pageIds): array;

    /**
     * Live pages and last change per space (a list of wikis). Every space asked has an entry: zero pages and no date
     * when it has no live page (an unknown id too). One grouped query per 500 spaces.
     *
     * @param list<string> $spaceIds
     *
     * @return array<string, WikiSpacePageStats> by space id, in the order asked
     */
    public function pageStats(array $spaceIds): array;

    /**
     * The ancestors of each page, root first, the parent last (breadcrumbs, search paths); an empty list for a root page.
     * A parent cycle in the data stops at the first page met twice. Unknown pages are left out. One query per tree level.
     *
     * @param list<string> $pageIds
     *
     * @return array<string, list<WikiPageSnapshot>> by page id, in the order asked
     */
    public function ancestors(array $pageIds): array;

    /**
     * The current revision of each page with its body (an export, a conversion). Pages without a revision are left out.
     * Bodies can be large: ask for pages in pages of results.
     *
     * @param list<string> $pageIds
     *
     * @return array<string, WikiPageContent> by page id, in the order asked
     */
    public function pageContents(array $pageIds): array;

    /**
     * The live pages whose current revision links the page (`[[wiki:<id>]]`, `[[wiki:<id>#anchor]]`), by title. With
     * `backlinks.enabled` the backlink index answers (links inside code are not links); without it, the current
     * revisions' Markdown is scanned and each candidate is parsed with the renderer's own reference parser. At most
     * `backlinks.candidates` linking rows are read per call.
     *
     * @param WikiUserRef|null $viewer only the pages this person may read (the page view's rule); null for every page
     * @param list<string>|null $spaceIds only pages of these spaces (the viewer's tenant); null for every space
     * @param int $limit pages returned at most (1 to 500)
     *
     * @return list<WikiPageSnapshot>
     *
     * @throws InvalidArgumentException when the limit is out of bounds or more than 1,000 space ids are given
     */
    public function backlinks(string $pageId, ?WikiUserRef $viewer = null, ?array $spaceIds = null, int $limit = 50): array;

    /**
     * The live pages of these spaces whose title or current plain text contains the text (literally, case-insensitive;
     * the wiki's own search, never the Markdown source). Archived spaces are never searched. Title matches first, then
     * text matches; each of the two scans reads at most 200 matching rows (see
     * {@see WikiPageSearchResult::$complete}).
     *
     * @param list<string> $spaceIds the spaces to search (the ones your rules let the person search)
     * @param WikiUserRef|null $viewer only the pages this person may read (the page view's rule); null for every page
     * @param int $limit hits returned at most (1 to 500)
     *
     * @throws InvalidArgumentException when the limit is out of bounds or more than 1,000 space ids are given
     */
    public function searchPages(array $spaceIds, string $text, ?WikiUserRef $viewer = null, int $limit = 25): WikiPageSearchResult;
}
