<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Query;

use Doctrine\ORM\QueryBuilder;
use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;

use function array_flip;
use function in_array;

/**
 * 1.51: {@see WikiSpaceQueryInterface} on the wiki's entity manager (the private one when it is on). Stateless.
 */
final readonly class WikiSpaceQuery implements WikiSpaceQueryInterface
{
    /**
     * @param WikiTransactionRunner $runner gives the wiki's entity manager
     */
    public function __construct(
        private WikiTransactionRunner $runner,
    ) {
    }

    public function findSpaces(WikiSpaceCriteria $criteria, int $offset = 0, int $limit = 100): array
    {
        WikiQueryIds::paging($offset, $limit);
        if ($criteria->matchesNothing()) {
            return [];
        }
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->sorted($this->filtered($criteria)->select(...WikiQueryRows::SPACE_FIELDS), $criteria->sort)
            ->addOrderBy('s.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        $spaces = [];
        foreach ($rows as $row) {
            $space = WikiQueryRows::space($row);
            if ($this->matches($criteria, $space->id, $space->ownerScopeId)) {
                $spaces[] = $space;
            }
        }

        return $spaces;
    }

    public function countSpaces(WikiSpaceCriteria $criteria): int
    {
        if ($criteria->matchesNothing()) {
            return 0;
        }
        // Ids and owners only, each re-checked byte for byte, so a folding collation never adds one.
        /** @var list<array{id: mixed, owner: mixed}> $rows */
        $rows = $this->filtered($criteria)
            ->select('s.id AS id', 's.ownerScopeId AS owner')
            ->getQuery()
            ->getArrayResult();
        $count = 0;
        foreach ($rows as $row) {
            if ($this->matches($criteria, WikiQueryRows::string($row['id']), WikiQueryRows::string($row['owner']))) {
                ++$count;
            }
        }

        return $count;
    }

    public function findSpacesByIds(array $spaceIds): array
    {
        $spaceIds = WikiQueryIds::ids($spaceIds);
        $found    = [];
        foreach (WikiQueryIds::chunks($spaceIds) as $chunk) {
            /** @var list<array<string, mixed>> $rows */
            $rows = $this->runner->manager()->createQueryBuilder()
                ->select(...WikiQueryRows::SPACE_FIELDS)
                ->from(WikiSpace::class, 's')
                ->where('s.id IN (:ids)')
                ->setParameter('ids', $chunk)
                ->getQuery()
                ->getArrayResult();
            foreach ($rows as $row) {
                $space             = WikiQueryRows::space($row);
                $found[$space->id] = $space;
            }
        }

        $ordered = [];
        foreach ($spaceIds as $id) {
            if (isset($found[$id])) {
                $ordered[$id] = $found[$id];
            }
        }

        return $ordered;
    }

    public function usage(WikiSpaceOwnerScope $ownerScopeType, array $ownerScopeIds): array
    {
        $owners = WikiQueryIds::owners($ownerScopeIds);
        /** @var array<string, array{spaces: int, archivedSpaces: int, pages: int, archivedPages: int, bytes: int}> $totals */
        $totals = [];
        foreach ($owners as $owner) {
            $totals[$owner] = ['spaces' => 0, 'archivedSpaces' => 0, 'pages' => 0, 'archivedPages' => 0, 'bytes' => 0];
        }
        foreach (WikiQueryIds::chunks($owners) as $chunk) {
            $manager = $this->runner->manager();
            // Only this chunk's owners: a look-alike row of another chunk's owner is counted by that chunk alone.
            $asked = array_flip($chunk);
            /** @var list<array{owner: mixed, total: mixed, archived: mixed}> $spaces */
            $spaces = $this->owned($manager->createQueryBuilder()->from(WikiSpace::class, 's'), $ownerScopeType, $chunk)
                ->select('s.ownerScopeId AS owner', 'COUNT(s.id) AS total', 'SUM(CASE WHEN s.archivedAt IS NULL THEN 0 ELSE 1 END) AS archived')
                ->getQuery()
                ->getArrayResult();
            /** @var list<array{owner: mixed, total: mixed, archived: mixed}> $pages */
            $pages = $this->owned($manager->createQueryBuilder()->from(WikiPage::class, 'p')->innerJoin('p.space', 's'), $ownerScopeType, $chunk)
                ->select('s.ownerScopeId AS owner', 'COUNT(p.id) AS total', 'SUM(CASE WHEN p.archivedAt IS NULL THEN 0 ELSE 1 END) AS archived')
                ->getQuery()
                ->getArrayResult();
            /** @var list<array{owner: mixed, bytes: mixed}> $bytes */
            $bytes = $this->owned($manager->createQueryBuilder()->from(WikiAttachment::class, 'a')->innerJoin('a.space', 's'), $ownerScopeType, $chunk)
                ->select('s.ownerScopeId AS owner', 'SUM(a.size) AS bytes')
                ->getQuery()
                ->getArrayResult();
            // One group per space (never per owner: a folding collation would merge look-alike owners into one group),
            // summed here per owner read back byte for byte (house rule LQ).
            foreach ($spaces as $row) {
                $owner = WikiQueryRows::string($row['owner']);
                if (isset($asked[$owner])) {
                    $archived = WikiQueryRows::int($row['archived']);
                    $totals[$owner]['spaces'] += WikiQueryRows::int($row['total']) - $archived;
                    $totals[$owner]['archivedSpaces'] += $archived;
                }
            }
            foreach ($pages as $row) {
                $owner = WikiQueryRows::string($row['owner']);
                if (isset($asked[$owner])) {
                    $archived = WikiQueryRows::int($row['archived']);
                    $totals[$owner]['pages'] += WikiQueryRows::int($row['total']) - $archived;
                    $totals[$owner]['archivedPages'] += $archived;
                }
            }
            foreach ($bytes as $row) {
                $owner = WikiQueryRows::string($row['owner']);
                if (isset($asked[$owner])) {
                    $totals[$owner]['bytes'] += WikiQueryRows::int($row['bytes']);
                }
            }
        }

        $usage = [];
        foreach ($owners as $owner) {
            $total         = $totals[$owner];
            $usage[$owner] = new WikiOwnerUsage($owner, $total['spaces'], $total['archivedSpaces'], $total['pages'], $total['archivedPages'], $total['bytes']);
        }

        return $usage;
    }

    /**
     * The spaces query with the criteria's filters (alias `s`), no selection yet.
     */
    private function filtered(WikiSpaceCriteria $criteria): QueryBuilder
    {
        $builder = $this->runner->manager()->createQueryBuilder()->from(WikiSpace::class, 's');
        if ($criteria->ownerScopeType instanceof WikiSpaceOwnerScope) {
            $builder->andWhere('s.ownerScopeType = :type')->setParameter('type', $criteria->ownerScopeType);
        }
        if ($criteria->ownerScopeIds !== null) {
            $builder->andWhere('s.ownerScopeId IN (:owners)')->setParameter('owners', $criteria->ownerScopeIds);
        }
        if ($criteria->spaceIds !== null) {
            $builder->andWhere('s.id IN (:ids)')->setParameter('ids', $criteria->spaceIds);
        }
        match ($criteria->archived) {
            WikiArchiveFilter::Active   => $builder->andWhere('s.archivedAt IS NULL'),
            WikiArchiveFilter::Archived => $builder->andWhere('s.archivedAt IS NOT NULL'),
            WikiArchiveFilter::Any      => $builder,
        };

        return $builder;
    }

    /**
     * Orders the spaces query (alias `s`) by the sort asked.
     */
    private function sorted(QueryBuilder $builder, WikiSpaceSort $sort): QueryBuilder
    {
        [$field, $direction] = match ($sort) {
            WikiSpaceSort::NameAsc     => ['s.name', 'ASC'],
            WikiSpaceSort::NameDesc    => ['s.name', 'DESC'],
            WikiSpaceSort::SlugAsc     => ['s.slug', 'ASC'],
            WikiSpaceSort::CreatedAsc  => ['s.createdAt', 'ASC'],
            WikiSpaceSort::CreatedDesc => ['s.createdAt', 'DESC'],
            WikiSpaceSort::UpdatedDesc => ['s.updatedAt', 'DESC'],
        };

        return $builder->orderBy($field, $direction);
    }

    /**
     * Narrows a query joined to its spaces (alias `s`) to the owners, grouped by space (and its owner id, read back):
     * never by owner id alone, which a folding collation would merge with a look-alike owner's.
     *
     * @param list<string> $owners
     */
    private function owned(QueryBuilder $builder, WikiSpaceOwnerScope $type, array $owners): QueryBuilder
    {
        return $builder->andWhere('s.ownerScopeType = :type')
            ->andWhere('s.ownerScopeId IN (:owners)')
            ->setParameter('type', $type)
            ->setParameter('owners', $owners)
            ->groupBy('s.id')
            ->addGroupBy('s.ownerScopeId');
    }

    /** Whether a row read back is exactly one the criteria asked for. */
    private function matches(WikiSpaceCriteria $criteria, string $spaceId, string $ownerScopeId): bool
    {
        return ($criteria->ownerScopeIds === null || in_array($ownerScopeId, $criteria->ownerScopeIds, true))
            && ($criteria->spaceIds === null || in_array($spaceId, $criteria->spaceIds, true));
    }
}
