<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;

use function is_array;
use function is_string;

/**
 * Full-text search across page titles and current revision HTML (per space or all accessible spaces).
 */
final readonly class WikiSearchService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<array{page: WikiPage, space: WikiSpace, excerpt: string}>
     */
    public function search(WikiSpace $space, string $query, int $limit = 25): array
    {
        return $this->searchInSpaces([$space], $query, $limit);
    }

    /**
     * @param list<WikiSpace> $spaces
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

        $like = '%' . addcslashes($query, '%_') . '%';

        /** @var list<array{0: WikiPage, contentHtml: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('p', 's', 'r.contentHtml AS contentHtml')
            ->from(WikiPage::class, 'p')
            ->innerJoin('p.space', 's')
            ->leftJoin('p.currentRevision', 'r')
            ->where('p.space IN (:spaces)')
            ->andWhere('p.archivedAt IS NULL')
            ->andWhere('p.title LIKE :q OR r.contentHtml LIKE :q')
            ->setParameter('spaces', $spaces)
            ->setParameter('q', $like)
            ->setMaxResults($limit)
            ->getQuery()
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
        $text = trim(strip_tags($contentHtml));
        if ($text === '') {
            return $title;
        }

        $pos = stripos($text, $query);
        if ($pos === false) {
            return mb_substr($text, 0, 120) . (mb_strlen($text) > 120 ? '…' : '');
        }

        $start   = max(0, $pos - 40);
        $snippet = mb_substr($text, $start, 120);

        return ($start > 0 ? '…' : '') . $snippet . (mb_strlen($text) > $start + 120 ? '…' : '');
    }
}
