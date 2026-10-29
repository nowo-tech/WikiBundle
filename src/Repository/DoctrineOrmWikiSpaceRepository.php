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
     * Persists the space and flushes it immediately.
     *
     * @param WikiSpace $space space to save
     */
    public function save(WikiSpace $space): void
    {
        $entityManager = $this->entityManager();
        $entityManager->persist($space);
        $entityManager->flush();
    }

    /**
     * Finds a space by its identifier, refreshing it from the database when found.
     *
     * @param string $id space UUID
     *
     * @return WikiSpace|null the space, or null when none exists
     */
    public function findById(string $id): ?WikiSpace
    {
        $entityManager = $this->entityManager();
        $space         = $entityManager->find(WikiSpace::class, $id);
        if ($space instanceof WikiSpace) {
            $entityManager->refresh($space);
        }

        return $space;
    }

    /**
     * Finds a space by slug within an owner scope.
     *
     * @param WikiSpaceOwnerScope $scopeType kind of owner (team or user)
     * @param string $ownerScopeId identifier of the owning team or user
     * @param string $slug space slug
     *
     * @return WikiSpace|null the space, or null when none matches
     */
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

    /**
     * Finds the first space with the given slug, whatever its owner scope.
     *
     * @param string $slug space slug
     *
     * @return WikiSpace|null the first matching space, or null when none matches
     */
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
     * Returns the spaces of the given owner scope type owned by any of the given identifiers, ordered by name.
     * Returns an empty list without querying when no identifier is given.
     *
     * @param string $ownerScopeType owner scope type value (e.g. `team` or `user`)
     * @param list<string> $ownerScopeIds identifiers of the owning teams or users
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
