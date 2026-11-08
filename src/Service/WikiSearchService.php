<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Markdown\WikiMarkdownLimitsInterface;
use Nowo\WikiBundle\Search\WikiPlainText;

use function is_array;
use function is_string;
use function mb_strtolower;
use function min;
use function str_contains;
use function trim;

/**
 * Full-text search across page titles and the plain text of the current revisions (per space or all accessible
 * spaces).
 *
 * 1.16: the body is matched on the revision's `content_text` ({@see WikiPlainText}), so Markdown punctuation, directive
 * markers and attribute values (`:::callout{icon="bulb"}`) are never hits. A revision written before 1.16 and not
 * backfilled yet (`content_text` null; run `wiki:search:backfill-text`) is still matched the 1.15 way.
 */
final readonly class WikiSearchService
{
    /** 1.24: the most rows {@see self::searchPlainText()} reads in one call (audit IF-I3). */
    public const MAX_ROWS = 1000;

    /**
     * Stores the entity manager (or the provider resolving it on each call).
     *
     * @param EntityManagerInterface|WikiEntityManagerProvider $entityManager wiki entity manager or its provider
     */
    public function __construct(
        private EntityManagerInterface|WikiEntityManagerProvider $entityManager,
    ) {
    }

    /**
     * Searches non-archived pages of one space by title or current revision content.
     *
     * @param WikiSpace $space space to search
     * @param string $query search text (matched literally; empty yields no results)
     * @param int $limit maximum number of hits
     *
     * @return list<array{page: WikiPage, space: WikiSpace, excerpt: string}>
     */
    public function search(WikiSpace $space, string $query, int $limit = 25): array
    {
        return $this->searchInSpaces([$space], $query, $limit);
    }

    /**
     * Searches non-archived pages of several spaces by title or current revision content.
     *
     * @param list<WikiSpace> $spaces spaces to search (empty yields no results)
     * @param string $query search text (matched literally; empty yields no results)
     * @param int $limit maximum number of hits
     *
     * @return list<array{page: WikiPage, space: WikiSpace, excerpt: string}>
     */
    public function searchAcrossSpaces(array $spaces, string $query, int $limit = 25): array
    {
        return $this->searchInSpaces($spaces, $query, $limit);
    }

    /**
     * 1.24 (spec 218): search for a reader who must not learn about a page's source. Matches titles and the plain text
     * of the current revision only; a revision without plain text yet is matched on its viewer-neutral `content_html`
     * cache, never on its Markdown (which holds ids and attributes the render hides). Case-insensitive on every
     * platform. Pages come in id order after `$afterPageId`, so a caller that filters hits can read on.
     *
     * A page whose Markdown is over the renderer's limits is matched on its title only, with the title as excerpt: its
     * cache is the source as escaped text, ids and comments included (audit IF-L1). A hit found only in the markup of an
     * HTML cache is dropped (audit IF-I3).
     *
     * @param list<WikiSpace> $spaces spaces to search (empty yields no results)
     * @param string $query search text (matched literally; empty yields no results)
     * @param int $limit maximum number of rows read (at most {@see self::MAX_ROWS})
     * @param string|null $afterPageId read pages after this id (keyset)
     * @param WikiMarkdownLimitsInterface|null $limits the renderer's limits
     *
     * @param-out string|null $lastPageId
     * @param-out int $rowsRead
     *
     * @return list<array{page: WikiPage, space: WikiSpace, excerpt: string}> the hits; `$lastPageId` and `$rowsRead` say
     *                                                                        where the read stopped and how many rows it read
     */
    public function searchPlainText(array $spaces, string $query, int $limit, ?string $afterPageId = null, ?WikiMarkdownLimitsInterface $limits = null, ?string &$lastPageId = null, ?int &$rowsRead = null): array
    {
        $lastPageId = null;
        $rowsRead   = 0;
        $limit      = min($limit, self::MAX_ROWS);
        $query      = trim($query);
        if ($query === '' || $spaces === [] || $limit < 1) {
            return [];
        }

        $like          = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($query)) . '%';
        $entityManager = $this->entityManager instanceof WikiEntityManagerProvider ? $this->entityManager->get() : $this->entityManager;
        $builder       = $entityManager->createQueryBuilder()
            ->select('p', 's', 'r.contentHtml AS contentHtml', 'r.contentText AS contentText', 'r.contentMarkdown AS contentMarkdown')
            ->from(WikiPage::class, 'p')
            ->innerJoin('p.space', 's')
            ->leftJoin('p.currentRevision', 'r')
            ->where('p.space IN (:spaces)')
            ->andWhere('p.archivedAt IS NULL')
            ->andWhere("LOWER(p.title) LIKE :q ESCAPE '!' OR LOWER(r.contentText) LIKE :q ESCAPE '!' OR (r.contentText IS NULL AND LOWER(r.contentHtml) LIKE :q ESCAPE '!')")
            ->setParameter('spaces', $spaces)
            ->setParameter('q', $like)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults($limit);
        if ($afterPageId !== null) {
            $builder->andWhere('p.id > :after')->setParameter('after', $afterPageId);
        }

        /** @var list<array{0: WikiPage, contentHtml: string|null, contentText: string|null, contentMarkdown: string|null}> $rows */
        $rows   = $builder->getQuery()->setHint(Query::HINT_REFRESH, true)->getResult();
        $needle = mb_strtolower($query);
        $kept   = [];
        foreach ($rows as $row) {
            $page       = $row[0];
            $lastPageId = $page->getId();
            ++$rowsRead;
            $inTitle  = str_contains(mb_strtolower($page->getTitle()), $needle);
            $markdown = $row['contentMarkdown'];
            if ($markdown !== null && $limits instanceof WikiMarkdownLimitsInterface && $limits->exceedsLimits($markdown)) {
                if ($inTitle) {
                    $kept[] = ['page' => $page, 'space' => $page->getSpace(), 'excerpt' => $page->getTitle()];
                }
                continue;
            }
            $text = $row['contentText'] ?? WikiPlainText::fromHtml($row['contentHtml'] ?? '');
            if ($inTitle || str_contains(mb_strtolower($text), $needle)) {
                $kept[] = ['page' => $page, 'space' => $page->getSpace(), 'excerpt' => $this->excerpt($page->getTitle(), $text, $query)];
            }
        }

        return $kept;
    }

    /**
     * @param list<WikiSpace> $spaces
     *
     * @return list<array{page: WikiPage, space: WikiSpace, excerpt: string}>
     */
    private function searchInSpaces(array $spaces, string $query, int $limit): array
    {
        $query = trim($query);
        if ($query === '' || $spaces === []) {
            return [];
        }

        // Escape LIKE wildcards (and the escape character itself) so user input matches literally on every platform.
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query) . '%';

        $entityManager = $this->entityManager instanceof WikiEntityManagerProvider ? $this->entityManager->get() : $this->entityManager;

        /** @var list<array{0: WikiPage, contentHtml: string|null, contentText: string|null}> $rows */
        $rows = $entityManager->createQueryBuilder()
            ->select('p', 's', 'r.contentHtml AS contentHtml', 'r.contentText AS contentText')
            ->from(WikiPage::class, 'p')
            ->innerJoin('p.space', 's')
            ->leftJoin('p.currentRevision', 'r')
            ->where('p.space IN (:spaces)')
            ->andWhere('p.archivedAt IS NULL')
            // 1.16: the plain text; a revision not backfilled yet: its source (Markdown) or HTML, as in 1.11.
            ->andWhere("p.title LIKE :q ESCAPE '!' OR r.contentText LIKE :q ESCAPE '!' OR (r.contentText IS NULL AND (r.contentMarkdown LIKE :q ESCAPE '!' OR (r.contentMarkdown IS NULL AND r.contentHtml LIKE :q ESCAPE '!')))")
            ->setParameter('spaces', $spaces)
            ->setParameter('q', $like)
            ->setMaxResults($limit)
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();

        if (!is_array($rows)) {
            return [];
        }

        return $this->hits($rows, $query);
    }

    /**
     * @param array<mixed> $rows
     *
     * @return list<array{page: WikiPage, space: WikiSpace, excerpt: string}>
     */
    private function hits(array $rows, string $query): array
    {
        $results = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row[0]) || !$row[0] instanceof WikiPage) {
                continue;
            }

            $page      = $row[0];
            $text      = is_string($row['contentText'] ?? null) ? $row['contentText'] : WikiPlainText::fromHtml(is_string($row['contentHtml'] ?? null) ? $row['contentHtml'] : '');
            $results[] = [
                'page'    => $page,
                'space'   => $page->getSpace(),
                'excerpt' => $this->excerpt($page->getTitle(), $text, $query),
            ];
        }

        return $results;
    }

    private function excerpt(string $title, string $text, string $query): string
    {
        if ($text === '') {
            return $title;
        }

        $pos = mb_stripos($text, $query);
        if ($pos === false) {
            return mb_substr($text, 0, 120) . (mb_strlen($text) > 120 ? '…' : '');
        }

        $start   = max(0, $pos - 40);
        $snippet = mb_substr($text, $start, 120);

        return ($start > 0 ? '…' : '') . $snippet . (mb_strlen($text) > $start + 120 ? '…' : '');
    }
}
