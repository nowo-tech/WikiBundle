<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;

final readonly class DoctrineOrmWikiPageRepository implements WikiPageRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(WikiPage $page): void
    {
        $this->entityManager->persist($page);
        $this->entityManager->flush();
    }

    public function findById(string $id): ?WikiPage
    {
        return $this->entityManager->find(WikiPage::class, $id);
    }

    public function findBySlug(WikiSpace $space, string $slug): ?WikiPage
    {
        return $this->entityManager->getRepository(WikiPage::class)->findOneBy([
            'space' => $space,
            'slug'  => $slug,
        ]);
    }

    public function findActiveBySpace(WikiSpace $space): array
    {
        /* @var list<WikiPage> */
        return $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(WikiPage::class, 'p')
            ->where('p.space = :space')
            ->andWhere('p.archivedAt IS NULL')
            ->setParameter('space', $space)
            ->orderBy('p.position', 'ASC')
            ->addOrderBy('p.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countBySpaceAndSlug(WikiSpace $space, string $slug, ?string $excludePageId = null): int
    {
        $qb = $this->entityManager->createQueryBuilder()
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
}
