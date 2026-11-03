<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use SortDirection;
use Throwable;

use function array_values;

/**
 * Lookups refresh already-managed pages ({@see Query::HINT_REFRESH}) so a page renamed or archived
 * by another worker is not served from a stale identity map.
 */
final readonly class DoctrineOrmWikiPageRepository implements WikiPageRepositoryInterface, WikiPageBatchFinderInterface, WikiPageTreeWriterInterface
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
     * Starts the space-locked transaction of a tree change (1.14): a write lock on the space row.
     */
    public function beginSpaceTransaction(WikiSpace $space): void
    {
        $entityManager = $this->entityManager();
        $entityManager->beginTransaction();
        try {
            // The space row is the lock of its tree: concurrent moves of one space wait for each other.
            $entityManager->createQueryBuilder()
                ->select('s.id')
                ->from(WikiSpace::class, 's')
                ->where('s.id = :id')
                ->setParameter('id', $space->getId())
                ->getQuery()
                ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                ->getSingleColumnResult();
        } catch (Throwable $exception) {
            $entityManager->rollback();

            throw $exception;
        }
    }

    public function commitSpaceTransaction(): void
    {
        $entityManager = $this->entityManager();
        $entityManager->flush();
        $entityManager->commit();
    }

    public function rollbackSpaceTransaction(): void
    {
        $this->entityManager()->rollback();
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
     * Finds the pages of the given ids in one query (1.13; archived pages included, unknown ids left out).
     *
     * @param list<string> $ids page ids
     *
     * @return list<WikiPage>
     */
    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /* @var list<WikiPage> */
        return $this->entityManager()->createQueryBuilder()
            ->select('p', 's')
            ->from(WikiPage::class, 'p')
            ->join('p.space', 's')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', array_values($ids))
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();
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
