<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use SortDirection;

/**
 * Lookups refresh already-managed pages ({@see Query::HINT_REFRESH}) so a page renamed or archived
 * by another worker is not served from a stale identity map.
 */
final readonly class DoctrineOrmWikiPageRepository implements WikiPageRepositoryInterface
{
    /**
     * Creates the repository.
     *
     * @param EntityManagerInterface|WikiEntityManagerProvider $entityManager entity manager, or a provider resolving the configured one lazily
     */
    public function __construct(
        private EntityManagerInterface|WikiEntityManagerProvider $entityManager,
    ) {
    }

    /**
     * Persists the page and flushes it immediately.
     *
     * @param WikiPage $page page to save
     */
    public function save(WikiPage $page): void
    {
        $entityManager = $this->entityManager();
        $entityManager->persist($page);
        $entityManager->flush();
    }

    /**
     * Finds a page by its identifier, refreshing it from the database when found.
     *
     * @param string $id page UUID
     *
     * @return WikiPage|null the page, or null when none exists
     */
    public function findById(string $id): ?WikiPage
    {
        $entityManager = $this->entityManager();
        $page          = $entityManager->find(WikiPage::class, $id);
        if ($page instanceof WikiPage) {
            $entityManager->refresh($page);
        }

        return $page;
    }

    /**
     * Finds a page by slug inside a space (archived pages included).
     *
     * @param WikiSpace $space space to search
     * @param string $slug page slug
     *
     * @return WikiPage|null the page, or null when none matches
     */
    public function findBySlug(WikiSpace $space, string $slug): ?WikiPage
    {
        /** @var WikiPage|null $page */
        $page = $this->entityManager()->createQueryBuilder()
            ->select('p')
            ->from(WikiPage::class, 'p')
            ->where('p.space = :space')
            ->andWhere('p.slug = :slug')
            ->setParameter('space', $space)
            ->setParameter('slug', $slug)
            ->setMaxResults(1)
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();

        return $page;
    }

    /**
     * Returns the non-archived pages of a space ordered by position, then title.
     *
     * @param WikiSpace $space space to read
     * @param int|null $limit maximum number of pages (null = all; list UIs always pass a cap)
     *
     * @return list<WikiPage>
     */
    public function findActiveBySpace(WikiSpace $space, ?int $limit = null): array
    {
        /* @var list<WikiPage> */
        return $this->entityManager()->createQueryBuilder()
            ->select('p')
            ->from(WikiPage::class, 'p')
            ->where('p.space = :space')
            ->andWhere('p.archivedAt IS NULL')
            ->setParameter('space', $space)
            ->orderBy('p.position', SortDirection::Ascending)
            ->addOrderBy('p.title', SortDirection::Ascending)
            ->setMaxResults($limit)
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();
    }

    public function findArchivedBySpace(WikiSpace $space, int $limit = 100): array
    {
        /* @var list<WikiPage> */
        return $this->entityManager()->createQueryBuilder()
            ->select('p')
            ->from(WikiPage::class, 'p')
            ->where('p.space = :space')
            ->andWhere('p.archivedAt IS NOT NULL')
            ->setParameter('space', $space)
            ->orderBy('p.archivedAt', SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Counts the pages of a space using the given slug, optionally ignoring one page (slug uniqueness checks).
     *
     * @param WikiSpace $space space to search
     * @param string $slug slug to look for
     * @param string|null $excludePageId page UUID to ignore (e.g. the page being edited), or null
     *
     * @return int number of matching pages
     */
    public function countBySpaceAndSlug(WikiSpace $space, string $slug, ?string $excludePageId = null): int
    {
        $qb = $this->entityManager()->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(WikiPage::class, 'p')
            ->where('p.space = :space')
            ->andWhere('p.slug = :slug')
            ->setParameter('space', $space)
            ->setParameter('slug', $slug);

        if ($excludePageId !== null) {
            $qb->andWhere('p.id != :excludeId')->setParameter('excludeId', $excludePageId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->entityManager instanceof WikiEntityManagerProvider ? $this->entityManager->get() : $this->entityManager;
    }
}
