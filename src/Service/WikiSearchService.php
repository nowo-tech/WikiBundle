<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;

use function html_entity_decode;
use function is_array;
use function is_string;
use function preg_replace;
use function strip_tags;
use function trim;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * Full-text search across page titles and current revision HTML (per space or all accessible spaces).
 */
final readonly class WikiSearchService
{
    /** Tags that start or end a block of text (a space is inserted before each one when the excerpt is built). */
    private const BLOCK_BOUNDARY = '~</?(?:address|article|aside|blockquote|br|dd|div|dl|dt|figcaption|figure|footer|h[1-6]|header|hr|img|li|ol|p|pre|section|table|tbody|td|tfoot|th|thead|tr|ul)\b[^>]*>~i';

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

        /** @var list<array{0: WikiPage, contentHtml: string}> $rows */
        $rows = $entityManager->createQueryBuilder()
            ->select('p', 's', 'r.contentHtml AS contentHtml')
            ->from(WikiPage::class, 'p')
            ->innerJoin('p.space', 's')
            ->leftJoin('p.currentRevision', 'r')
            ->where('p.space IN (:spaces)')
            ->andWhere('p.archivedAt IS NULL')
            // 1.11: a Markdown revision is searched in its source, an HTML revision in its HTML.
            ->andWhere("p.title LIKE :q ESCAPE '!' OR r.contentMarkdown LIKE :q ESCAPE '!' OR (r.contentMarkdown IS NULL AND r.contentHtml LIKE :q ESCAPE '!')")
            ->setParameter('spaces', $spaces)
            ->setParameter('q', $like)
            ->setMaxResults($limit)
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();

        if (!is_array($rows)) {
            return [];
        }

        $results = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row[0]) || !$row[0] instanceof WikiPage) {
                continue;
            }

            $page      = $row[0];
            $content   = is_string($row['contentHtml'] ?? null) ? $row['contentHtml'] : '';
            $results[] = [
                'page'    => $page,
                'space'   => $page->getSpace(),
                'excerpt' => $this->excerpt($page->getTitle(), $content, $query),
            ];
        }

        return $results;
    }

    private function excerpt(string $title, string $contentHtml, string $query): string
    {
        $text = $this->plainText($contentHtml);
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

    /**
     * The text of a revision body with one space between blocks (1.12): a line break, a block element boundary
     * (paragraph, heading, list item, table cell, …) or an image becomes a space before the tags are stripped, so
     * "<h2>Overview</h2><p>This runbook…" reads "Overview This runbook…"; entities are decoded and runs of
     * whitespace (no-break spaces included) collapse to one space.
     */
    private function plainText(string $html): string
    {
        $spaced = preg_replace(self::BLOCK_BOUNDARY, ' $0', $html) ?? $html;
        $text   = html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? $text);
    }
}
