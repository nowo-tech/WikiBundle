<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;

final readonly class DoctrineOrmWikiSpaceRepository implements WikiSpaceRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(WikiSpace $space): void
    {
        $this->entityManager->persist($space);
        $this->entityManager->flush();
    }

    public function findById(string $id): ?WikiSpace
    {
        return $this->entityManager->find(WikiSpace::class, $id);
    }

    public function findBySlug(WikiSpaceOwnerScope $scopeType, string $ownerScopeId, string $slug): ?WikiSpace
    {
        return $this->entityManager->getRepository(WikiSpace::class)->findOneBy([
            'ownerScopeType' => $scopeType,
            'ownerScopeId'   => $ownerScopeId,
            'slug'           => $slug,
        ]);
    }

    public function findFirstBySlug(string $slug): ?WikiSpace
    {
        /** @var WikiSpace|null $space */
        $space = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(WikiSpace::class, 's')
            ->where('s.slug = :slug')
            ->setParameter('slug', $slug)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $space;
    }

    public function findAccessible(string $ownerScopeType, array $ownerScopeIds): array
    {
        if ($ownerScopeIds === []) {
            return [];
        }

        /* @var list<WikiSpace> */
        return $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(WikiSpace::class, 's')
            ->where('s.ownerScopeType = :scopeType')
            ->andWhere('s.ownerScopeId IN (:scopeIds)')
            ->setParameter('scopeType', $ownerScopeType)
            ->setParameter('scopeIds', $ownerScopeIds)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
