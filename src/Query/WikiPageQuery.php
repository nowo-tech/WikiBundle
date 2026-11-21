<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Query;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageReference;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Markdown\WikiMarkdownLimitsInterface;
use Nowo\WikiBundle\Markdown\WikiMarkdownReferenceExtractor;
use Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface;
use Nowo\WikiBundle\Port\WikiSnapshots;
use Nowo\WikiBundle\Security\WikiPageAccessInterface;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

use function array_filter;
use function array_flip;
use function array_intersect_key;
use function array_keys;
use function array_map;
use function array_reverse;
use function array_slice;
use function array_values;
use function count;
use function in_array;
use function min;
use function str_replace;
use function strcmp;
use function strnatcasecmp;
use function trim;
use function usort;

/**
 * 1.51: {@see WikiPageQueryInterface} on the wiki's entity manager (the private one when it is on). Stateless.
 */
final readonly class WikiPageQuery implements WikiPageQueryInterface
{
    /** Rows read per database round of a bounded scan. */
    private const BATCH = 100;

    /**
     * @param WikiTransactionRunner $runner gives the wiki's entity manager
     * @param WikiPageAccessInterface $access the page rule for a viewer
     * @param WikiSearchService $search the wiki's plain-text search
     * @param WikiMarkdownReferenceExtractor $references the renderer's reference parser (backlinks without the index)
     * @param bool $backlinkIndex whether `backlinks.enabled` keeps the backlink index
     * @param int $candidates linking rows read at most per backlink call (`backlinks.candidates`)
     * @param WikiMarkdownRendererInterface|null $renderer with limits, a page over them matches the search on its title only
     * @param int $searchCandidates matching rows read at most per search scan
     */
    public function __construct(
        private WikiTransactionRunner $runner,
        private WikiPageAccessInterface $access,
        private WikiSearchService $search,
        private WikiMarkdownReferenceExtractor $references,
        private bool $backlinkIndex = false,
        private int $candidates = 200,
        private ?WikiMarkdownRendererInterface $renderer = null,
        private int $searchCandidates = 200,
    ) {
    }

    public function findPages(WikiPageCriteria $criteria, int $offset = 0, int $limit = 100): array
    {
        WikiQueryIds::paging($offset, $limit);
        if ($criteria->matchesNothing()) {
            return [];
        }
        $builder = $this->filtered($criteria)->addSelect(...WikiQueryRows::PAGE_FIELDS);
        match ($criteria->sort) {
            WikiPageSort::TitleAsc    => $builder->orderBy('p.title', 'ASC'),
            WikiPageSort::CreatedAsc  => $builder->orderBy('p.createdAt', 'ASC'),
            WikiPageSort::UpdatedDesc => $builder->orderBy('p.updatedAt', 'DESC'),
            WikiPageSort::Position    => $builder->orderBy('spaceId', 'ASC')->addOrderBy('p.position', 'ASC'),
        };
        /** @var list<array<string, mixed>> $rows */
        $rows = $builder->addOrderBy('p.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        $pages = [];
        foreach ($rows as $row) {
            $page = WikiQueryRows::page($row);
            if (self::matches($criteria, $page->id, $page->spaceId, WikiQueryRows::string($row['owner']))) {
                $pages[] = $page;
            }
        }

        return $pages;
    }

    public function countPages(WikiPageCriteria $criteria): int
    {
        if ($criteria->matchesNothing()) {
            return 0;
        }
        if ($criteria->pageIds !== null) {
            // At most 1,000 rows: each one re-checked.
            /** @var list<array{id: mixed, spaceId: mixed, owner: mixed}> $rows */
            $rows  = $this->filtered($criteria)->addSelect('p.id AS id', 'IDENTITY(p.space) AS spaceId')->getQuery()->getArrayResult();
            $count = 0;
            foreach ($rows as $row) {
                if (self::matches($criteria, WikiQueryRows::string($row['id']), WikiQueryRows::string($row['spaceId']), WikiQueryRows::string($row['owner']))) {
                    ++$count;
                }
            }

            return $count;
        }
        // Grouped by space (and its owner), each group re-checked byte for byte: a folding collation never adds one.
        /** @var list<array{spaceId: mixed, owner: mixed, total: mixed}> $groups */
        $groups = $this->filtered($criteria)
            ->addSelect('IDENTITY(p.space) AS spaceId', 'COUNT(p.id) AS total')
            ->groupBy('p.space')
            ->addGroupBy('s.ownerScopeId')
            ->getQuery()
            ->getArrayResult();
        $count = 0;
        foreach ($groups as $group) {
            if (self::matches($criteria, null, WikiQueryRows::string($group['spaceId']), WikiQueryRows::string($group['owner']))) {
                $count += WikiQueryRows::int($group['total']);
            }
        }

        return $count;
    }

    public function findPagesByIds(array $pageIds): array
    {
        $pageIds = WikiQueryIds::ids($pageIds);
        $found   = [];
        foreach (WikiQueryIds::chunks($pageIds) as $chunk) {
            foreach ($this->pageRows($chunk) as $page) {
                $found[$page->id] = $page;
            }
        }

        return self::ordered($pageIds, $found);
    }

    public function pageStats(array $spaceIds): array
    {
        $spaceIds = WikiQueryIds::ids($spaceIds);
        $stats    = [];
        foreach (WikiQueryIds::chunks($spaceIds) as $chunk) {
            $manager = $this->runner->manager();
            /** @var list<array{spaceId: mixed, pages: mixed, updated: mixed}> $rows */
            $rows = $manager->createQueryBuilder()
                ->select('IDENTITY(p.space) AS spaceId', 'COUNT(p.id) AS pages', 'MAX(p.updatedAt) AS updated')
                ->from(WikiPage::class, 'p')
                ->where('IDENTITY(p.space) IN (:spaces)')
                ->andWhere('p.archivedAt IS NULL')
                ->groupBy('p.space')
                ->setParameter('spaces', $chunk)
                ->getQuery()
                ->getArrayResult();
            $platform = $manager->getConnection()->getDatabasePlatform();
            foreach ($rows as $row) {
                $spaceId         = WikiQueryRows::string($row['spaceId']);
                $stats[$spaceId] = new WikiSpacePageStats($spaceId, WikiQueryRows::int($row['pages']), WikiQueryRows::aggregateDate($row['updated'], $platform));
            }
        }

        $ordered = [];
        foreach ($spaceIds as $id) {
            $ordered[$id] = $stats[$id] ?? new WikiSpacePageStats($id, 0, null);
        }

        return $ordered;
    }

    public function ancestors(array $pageIds): array
    {
        $pages = $this->findPagesByIds($pageIds);
        $known = $pages;
        $level = $pages;
        // One query per tree level: the parents not read yet.
        while ($level !== []) {
            $missing = [];
            foreach ($level as $page) {
                if ($page->parentId !== null && !isset($known[$page->parentId])) {
                    $missing[$page->parentId] = true;
                }
            }
            $level = $this->findPagesByIds(array_keys($missing));
            $known += $level;
        }

        $ancestors = [];
        foreach ($pages as $id => $page) {
            $chain = [];
            $seen  = [$id => true];
            for ($parentId = $page->parentId; $parentId !== null && isset($known[$parentId]) && !isset($seen[$parentId]); $parentId = $known[$parentId]->parentId) {
                $seen[$parentId] = true;
                $chain[]         = $known[$parentId];
            }
            $ancestors[$id] = array_reverse($chain);
        }

        return $ancestors;
    }

    public function pageContents(array $pageIds): array
    {
        $pageIds = WikiQueryIds::ids($pageIds);
        $found   = [];
        foreach (WikiQueryIds::chunks($pageIds) as $chunk) {
            /** @var list<array{pageId: mixed, revisionId: mixed, number: mixed, format: mixed, markdown: mixed, html: mixed, text: mixed}> $rows */
            $rows = $this->runner->manager()->createQueryBuilder()
                ->select('p.id AS pageId', 'r.id AS revisionId', 'r.revisionNumber AS number', 'r.format AS format', 'r.contentMarkdown AS markdown', 'r.contentHtml AS html', 'r.contentText AS text')
                ->from(WikiPage::class, 'p')
                ->innerJoin('p.currentRevision', 'r')
                ->where('p.id IN (:ids)')
                ->setParameter('ids', $chunk)
                ->getQuery()
                ->getArrayResult();
            foreach ($rows as $row) {
                $pageId         = WikiQueryRows::string($row['pageId']);
                $found[$pageId] = new WikiPageContent(
                    $pageId,
                    WikiQueryRows::string($row['revisionId']),
                    WikiQueryRows::int($row['number']),
                    WikiQueryRows::string($row['format']),
                    WikiQueryRows::stringOrNull($row['markdown']),
                    WikiQueryRows::string($row['html']),
                    WikiQueryRows::stringOrNull($row['text']),
                );
            }
        }

        return self::ordered($pageIds, $found);
    }

    public function backlinks(string $pageId, ?WikiUserRef $viewer = null, ?array $spaceIds = null, int $limit = 50): array
    {
        WikiQueryIds::paging(0, $limit);
        $spaceIds = $spaceIds === null ? null : WikiQueryIds::filter($spaceIds);
        if ($spaceIds === [] || $pageId === '') {
            return [];
        }
        $found = [];
        $after = '';
        $read  = 0;
        while ($read < $this->candidates && count($found) < $limit) {
            $batch = min(self::BATCH, $this->candidates - $read);
            $rows  = $this->linkingRows($pageId, $spaceIds, $after, $batch);
            $read += count($rows);
            $sources = [];
            foreach ($rows as $row) {
                $after = WikiQueryRows::string($row['id']);
                if ($this->links($row, $pageId) && ($spaceIds === null || in_array(WikiQueryRows::string($row['spaceId']), $spaceIds, true))) {
                    $sources[$after] = WikiQueryRows::page($row);
                }
            }
            if ($viewer instanceof WikiUserRef) {
                $sources = array_intersect_key($sources, array_flip($this->access->readablePageIds($viewer, array_keys($sources))));
            }
            foreach ($sources as $source) {
                if (count($found) < $limit) {
                    $found[] = $source;
                }
            }
            if (count($rows) < $batch) {
                break;
            }
        }
        usort($found, static fn (WikiPageSnapshot $a, WikiPageSnapshot $b): int => strnatcasecmp($a->title, $b->title) ?: strcmp($a->id, $b->id));

        return $found;
    }

    public function searchPages(array $spaceIds, string $text, ?WikiUserRef $viewer = null, int $limit = 25): WikiPageSearchResult
    {
        WikiQueryIds::paging(0, $limit);
        $spaces = $this->spaces(WikiQueryIds::filter($spaceIds));
        $text   = trim($text);
        if ($text === '' || $spaces === []) {
            return new WikiPageSearchResult([], true);
        }
        $limits = $this->renderer instanceof WikiMarkdownLimitsInterface ? $this->renderer : null;
        $hits   = [];
        // Title matches first, so text matches (or hidden pages) never crowd a title hit out of the budget.
        $this->scan($viewer, $hits, fn (?string $after, int $batch, ?string &$last, ?int &$rows): array => $this->search->searchTitles($spaces, $text, $batch, $after, $last, $rows));
        $complete = $this->scan($viewer, $hits, fn (?string $after, int $batch, ?string &$last, ?int &$rows): array => $this->search->searchPlainText($spaces, $text, $batch, $after, $limits, $last, $rows));

        return new WikiPageSearchResult(array_values(array_slice($hits, 0, $limit)), $complete && count($hits) <= $limit);
    }

    /**
     * Reads matching rows in batches (at most `searchCandidates`), adding the hits not seen yet that the viewer may read.
     *
     * @param array<string, WikiPageSearchHit> $hits
     * @param callable(?string, int, ?string, ?int): list<array{page: WikiPage, space: WikiSpace, excerpt: string}> $read
     *
     * @param-out array<string, WikiPageSearchHit> $hits
     *
     * @return bool whether every matching row was read
     */
    private function scan(?WikiUserRef $viewer, array &$hits, callable $read): bool
    {
        $done     = 0;
        $after    = null;
        $complete = false;
        while (!$complete && $done < $this->searchCandidates) {
            $batch    = min(self::BATCH, $this->searchCandidates - $done);
            $last     = null;
            $rows     = 0;
            $found    = $read($after, $batch, $last, $rows);
            $after    = $last;
            $done += $rows;
            $complete = $rows < $batch;
            $batchHits = [];
            foreach ($found as $hit) {
                $id = $hit['page']->getId();
                if (!isset($hits[$id])) {
                    $batchHits[$id] = new WikiPageSearchHit(WikiSnapshots::page($hit['page']), WikiSnapshots::space($hit['space']), $hit['excerpt']);
                }
            }
            if ($viewer instanceof WikiUserRef) {
                $batchHits = array_intersect_key($batchHits, array_flip($this->access->readablePageIds($viewer, array_keys($batchHits))));
            }
            $hits += $batchHits;
        }

        return $complete;
    }

    /**
     * The spaces with exactly these ids, as entities for the search service.
     *
     * @param list<string> $spaceIds
     *
     * @return list<WikiSpace>
     */
    private function spaces(array $spaceIds): array
    {
        if ($spaceIds === []) {
            return [];
        }
        /** @var list<WikiSpace> $spaces */
        $spaces = $this->runner->manager()->createQueryBuilder()
            ->select('s')
            ->from(WikiSpace::class, 's')
            ->where('s.id IN (:ids)')
            ->setParameter('ids', $spaceIds)
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();

        return array_values(array_filter($spaces, static fn (WikiSpace $space): bool => in_array($space->getId(), $spaceIds, true)));
    }

    /**
     * One batch of candidate linking pages after `$after`, in id order, with what proves the link.
     *
     * @param list<string>|null $spaceIds
     *
     * @return list<array<string, mixed>>
     */
    private function linkingRows(string $pageId, ?array $spaceIds, string $after, int $batch): array
    {
        $builder = $this->runner->manager()->createQueryBuilder()
            ->select(...WikiQueryRows::PAGE_FIELDS)
            ->from(WikiPage::class, 'p')
            ->where('p.archivedAt IS NULL')
            ->andWhere('p.id <> :target')
            ->andWhere('p.id > :after')
            ->orderBy('p.id', 'ASC')
            ->setParameter('target', $pageId)
            ->setParameter('after', $after)
            ->setMaxResults($batch);
        if ($this->backlinkIndex) {
            $builder->addSelect('r.targetPageId AS target')
                ->innerJoin(WikiPageReference::class, 'r', 'WITH', 'r.source = p')
                ->andWhere('r.targetPageId = :target');
        } else {
            $builder->addSelect('rev.contentMarkdown AS markdown')
                ->innerJoin('p.currentRevision', 'rev')
                ->andWhere("rev.contentMarkdown LIKE :link ESCAPE '!'")
                ->setParameter('link', '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], '[[wiki:' . $pageId) . '%');
        }
        if ($spaceIds !== null) {
            $builder->andWhere('IDENTITY(p.space) IN (:spaces)')->setParameter('spaces', $spaceIds);
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $builder->getQuery()->getArrayResult();

        return $rows;
    }

    /**
     * Whether a candidate row really links the page: the index row's target byte for byte, or the target among the
     * references the renderer's parser finds in the Markdown (never inside code).
     *
     * @param array<string, mixed> $row
     */
    private function links(array $row, string $pageId): bool
    {
        if ($this->backlinkIndex) {
            return WikiQueryRows::string($row['target']) === $pageId;
        }

        return in_array($pageId, $this->references->pageIds(WikiQueryRows::stringOrNull($row['markdown'])), true);
    }

    /**
     * The page query with the criteria's filters (aliases `p` and its space `s`), selecting the owner as `owner`.
     */
    private function filtered(WikiPageCriteria $criteria): QueryBuilder
    {
        $builder = $this->runner->manager()->createQueryBuilder()
            ->select('s.ownerScopeId AS owner')
            ->from(WikiPage::class, 'p')
            ->innerJoin('p.space', 's');
        if ($criteria->spaceIds !== null) {
            $builder->andWhere('IDENTITY(p.space) IN (:spaces)')->setParameter('spaces', $criteria->spaceIds);
        }
        if ($criteria->ownerScopeType instanceof WikiSpaceOwnerScope) {
            $builder->andWhere('s.ownerScopeType = :type')->setParameter('type', $criteria->ownerScopeType);
        }
        if ($criteria->ownerScopeIds !== null) {
            $builder->andWhere('s.ownerScopeId IN (:owners)')->setParameter('owners', $criteria->ownerScopeIds);
        }
        if ($criteria->pageIds !== null) {
            $builder->andWhere('p.id IN (:ids)')->setParameter('ids', $criteria->pageIds);
        }
        match ($criteria->archived) {
            WikiArchiveFilter::Active   => $builder->andWhere('p.archivedAt IS NULL'),
            WikiArchiveFilter::Archived => $builder->andWhere('p.archivedAt IS NOT NULL'),
            WikiArchiveFilter::Any      => $builder,
        };

        return $builder;
    }

    /**
     * The pages with these ids (one chunk), read as columns.
     *
     * @param list<string> $ids
     *
     * @return list<WikiPageSnapshot>
     */
    private function pageRows(array $ids): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->runner->manager()->createQueryBuilder()
            ->select(...WikiQueryRows::PAGE_FIELDS)
            ->from(WikiPage::class, 'p')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getArrayResult();

        return array_map(WikiQueryRows::page(...), $rows);
    }

    /** Whether a row read back is exactly one the criteria asked for (null id: a group, not a page). */
    private static function matches(WikiPageCriteria $criteria, ?string $pageId, string $spaceId, string $ownerScopeId): bool
    {
        return ($criteria->pageIds === null || in_array($pageId, $criteria->pageIds, true))
            && ($criteria->spaceIds === null || in_array($spaceId, $criteria->spaceIds, true))
            && ($criteria->ownerScopeIds === null || in_array($ownerScopeId, $criteria->ownerScopeIds, true));
    }

    /**
     * The found values in the order of the ids asked, exact keys only.
     *
     * @template T
     *
     * @param list<string> $ids
     * @param array<string, T> $found
     *
     * @return array<string, T>
     */
    private static function ordered(array $ids, array $found): array
    {
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($found[$id])) {
                $ordered[$id] = $found[$id];
            }
        }

        return $ordered;
    }
}
