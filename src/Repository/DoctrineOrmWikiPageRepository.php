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
    public function __construct(
        private EntityManagerInterface|WikiEntityManagerProvider $entityManager,
    ) {
    }

    public function save(WikiPage $page): void
    {
        $entityManager = $this->entityManager();
        $entityManager->persist($page);
        $entityManager->flush();
    }

    public function findById(string $id): ?WikiPage
    {
        $entityManager = $this->entityManager();
        $page          = $entityManager->find(WikiPage::class, $id);
        if ($page instanceof WikiPage) {
            $entityManager->refresh($page);
        }

        return $page;
    }

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

    public function findActiveBySpace(WikiSpace $space): array
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
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();
    }

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
