<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use SortDirection;

/**
 * Lookups refresh already-managed spaces ({@see Query::HINT_REFRESH}) so changes made by another
 * worker are not hidden by a stale identity map.
 */
final readonly class DoctrineOrmWikiSpaceRepository implements WikiSpaceRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface|WikiEntityManagerProvider $entityManager,
    ) {
    }

    public function save(WikiSpace $space): void
    {
        $entityManager = $this->entityManager();
        $entityManager->persist($space);
        $entityManager->flush();
    }

    public function findById(string $id): ?WikiSpace
    {
        $entityManager = $this->entityManager();
        $space         = $entityManager->find(WikiSpace::class, $id);
        if ($space instanceof WikiSpace) {
            $entityManager->refresh($space);
        }

        return $space;
    }

    public function findBySlug(WikiSpaceOwnerScope $scopeType, string $ownerScopeId, string $slug): ?WikiSpace
    {
        /** @var WikiSpace|null $space */
        $space = $this->entityManager()->createQueryBuilder()
            ->select('s')
            ->from(WikiSpace::class, 's')
            ->where('s.ownerScopeType = :scopeType')
            ->andWhere('s.ownerScopeId = :scopeId')
            ->andWhere('s.slug = :slug')
            ->setParameter('scopeType', $scopeType->value)
            ->setParameter('scopeId', $ownerScopeId)
            ->setParameter('slug', $slug)
            ->setMaxResults(1)
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();

        return $space;
    }

    public function findFirstBySlug(string $slug): ?WikiSpace
    {
        /** @var WikiSpace|null $space */
        $space = $this->entityManager()->createQueryBuilder()
            ->select('s')
            ->from(WikiSpace::class, 's')
            ->where('s.slug = :slug')
            ->setParameter('slug', $slug)
            ->setMaxResults(1)
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();

        return $space;
    }

    /**
     * @param list<string> $ownerScopeIds
     *
     * @return list<WikiSpace>
     */
    public function findAccessible(string $ownerScopeType, array $ownerScopeIds): array
    {
        if ($ownerScopeIds === []) {
            return [];
        }

        /* @var list<WikiSpace> */
        return $this->entityManager()->createQueryBuilder()
            ->select('s')
            ->from(WikiSpace::class, 's')
            ->where('s.ownerScopeType = :scopeType')
            ->andWhere('s.ownerScopeId IN (:scopeIds)')
            ->setParameter('scopeType', $ownerScopeType)
            ->setParameter('scopeIds', $ownerScopeIds)
            ->orderBy('s.name', SortDirection::Ascending)
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->entityManager instanceof WikiEntityManagerProvider ? $this->entityManager->get() : $this->entityManager;
    }
}
