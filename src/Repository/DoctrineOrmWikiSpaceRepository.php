<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Doctrine\WikiFlusher;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use SortDirection;

use function array_chunk;
use function array_filter;
use function array_unique;
use function array_values;
use function in_array;

/**
 * Lookups refresh already-managed spaces ({@see Query::HINT_REFRESH}) so changes made by another
 * worker are not hidden by a stale identity map. 1.55.2: {@see WikiSpaceBatchFinderInterface} adds a batch read (one
 * query, still fresh) and a no-refresh read for read paths.
 */
final readonly class DoctrineOrmWikiSpaceRepository implements WikiSpaceRepositoryInterface, WikiSpaceBatchFinderInterface
{
    /** 1.55.2: ids per `findByIds()` statement. */
    public const FIND_BY_IDS_CHUNK = 500;

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
        WikiFlusher::flush($entityManager, $this->entityManager);
    }

    /**
     * Finds a space by its identifier, always read from the database: a space the manager already holds is refreshed,
     * any other one is loaded (1.55.2: one query either way, no refresh right after the load).
     *
     * @param string $id space UUID
     *
     * @return WikiSpace|null the space, or null when none exists
     */
    public function findById(string $id): ?WikiSpace
    {
        $entityManager = $this->entityManager();
        $held          = $entityManager->getUnitOfWork()->tryGetById($id, WikiSpace::class);
        if ($held instanceof WikiSpace) {
            $entityManager->refresh($held);

            return $held;
        }
        $space = $entityManager->find(WikiSpace::class, $id);

        // Audit ZBO-3: a folding collation may answer a look-alike space; only the exact id counts.
        return $space instanceof WikiSpace && $space->getId() === $id ? $space : null;
    }

    /**
     * Finds the spaces of the given ids in one query per {@see self::FIND_BY_IDS_CHUNK} ids, refreshed by the query itself (1.55.2).
     *
     * @param list<string> $ids space ids (duplicates are ignored)
     *
     * @return array<string, WikiSpace> the spaces found, keyed by id, in the order of `$ids`
     */
    public function findByIds(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return [];
        }

        // Audit ZBO-4: at most FIND_BY_IDS_CHUNK ids per statement (driver placeholder limits).
        $byId = [];
        foreach (array_chunk($ids, self::FIND_BY_IDS_CHUNK) as $chunk) {
            /** @var list<WikiSpace> $spaces */
            $spaces = $this->entityManager()->createQueryBuilder()
                ->select('s')
                ->from(WikiSpace::class, 's')
                ->where('s.id IN (:ids)')
                ->setParameter('ids', $chunk)
                ->getQuery()
                ->setHint(Query::HINT_REFRESH, true)
                ->getResult();
            foreach ($spaces as $space) {
                $byId[$space->getId()] = $space;
            }
        }
        // House rule LQ: only the exact ids count (a folding collation may also match a look-alike one).
        $found = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $found[$id] = $byId[$id];
            }
        }

        return $found;
    }

    /**
     * Finds a space by id through the identity map, loading it only when the manager does not hold it yet (1.55.2;
     * read paths only, see {@see WikiSpaceBatchFinderInterface}).
     *
     * @param string $id space id
     *
     * @return WikiSpace|null the space, or null when none has exactly that id
     */
    public function findByIdWithoutRefresh(string $id): ?WikiSpace
    {
        $space = $this->entityManager()->find(WikiSpace::class, $id);

        return $space instanceof WikiSpace && $space->getId() === $id ? $space : null;
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
        /** @var list<WikiSpace> $spaces */
        $spaces = $this->entityManager()->createQueryBuilder()
            ->select('s')
            ->from(WikiSpace::class, 's')
            ->where('s.ownerScopeType = :scopeType')
            ->andWhere('s.ownerScopeId = :scopeId')
            ->andWhere('s.slug = :slug')
            ->setParameter('scopeType', $scopeType->value)
            ->setParameter('scopeId', $ownerScopeId)
            ->setParameter('slug', $slug)
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();

        // 1.31.1 (house rule LQ): the SQL match is only a pre-filter. A case- or accent-folding or PAD SPACE
        // collation also returns the space of a look-alike owner (`Alice`, `alice `); only the exact one counts.
        foreach ($spaces as $space) {
            if ($space->getOwnerScopeType() === $scopeType && $space->getOwnerScopeId() === $ownerScopeId && $space->getSlug() === $slug) {
                return $space;
            }
        }

        return null;
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
        /** @var list<WikiSpace> $spaces */
        $spaces = $this->entityManager()->createQueryBuilder()
            ->select('s')
            ->from(WikiSpace::class, 's')
            ->where('s.slug = :slug')
            ->setParameter('slug', $slug)
            ->orderBy('s.id', 'ASC')
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();
        // 1.37 (audit QL-M3): no LIMIT before the exact check, so a look-alike row never takes the exact one's place.
        foreach ($spaces as $space) {
            if ($space->getSlug() === $slug) {
                return $space;
            }
        }

        return null;
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

        /** @var list<WikiSpace> $spaces */
        $spaces = $this->entityManager()->createQueryBuilder()
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

        // 1.31.1 (house rule LQ): re-check the owner with `===`. The column collation may fold case and accents and
        // ignore trailing spaces, so the SQL `IN` also returns the spaces of look-alike owners (`Alice`, `alice `).
        return array_values(array_filter(
            $spaces,
            static fn (WikiSpace $space): bool => $space->getOwnerScopeType()->value === $ownerScopeType
                && in_array($space->getOwnerScopeId(), $ownerScopeIds, true),
        ));
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->entityManager instanceof WikiEntityManagerProvider ? $this->entityManager->get() : $this->entityManager;
    }
}
