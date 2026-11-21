<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Query;

use InvalidArgumentException;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;

/**
 * 1.51: the spaces an application lists and counts, without writing queries over the wiki's tables (public service,
 * SemVer; REQ-ARCH-001 rule 1, DDD-host checklist B7). Framework-free: ids and criteria in, snapshots and plain values
 * out, never an entity.
 *
 * - **No access decision.** These reads answer what is stored: list for a person only what your own rules (or
 *   {@see \Nowo\WikiBundle\Security\WikiPageAccessInterface}, the space checker) let them see.
 * - **Exact ids.** Owner ids, space ids and the rows read back are compared byte for byte in PHP after the SQL (house
 *   rule LQ), whatever the column collation.
 * - **Stateless and read-only.** Nothing is kept between calls (FrankenPHP worker mode with the kernel kept alive), no
 *   row is locked or written, and rows are read as columns: no entity enters an entity manager.
 */
interface WikiSpaceQueryInterface
{
    /**
     * The spaces matching the criteria, in its order, one page of results.
     *
     * @param int $offset rows skipped
     * @param int $limit rows returned at most (1 to 500)
     *
     * @return list<WikiSpaceSnapshot>
     *
     * @throws InvalidArgumentException when the offset or limit is out of bounds
     */
    public function findSpaces(WikiSpaceCriteria $criteria, int $offset = 0, int $limit = 100): array;

    /**
     * How many spaces match the criteria.
     */
    public function countSpaces(WikiSpaceCriteria $criteria): int;

    /**
     * The spaces with these ids (unknown ids are left out), any size of list.
     *
     * @param list<string> $spaceIds
     *
     * @return array<string, WikiSpaceSnapshot> by space id, in the order asked
     */
    public function findSpacesByIds(array $spaceIds): array;

    /**
     * What each owner holds: spaces, pages and attachment bytes (plan limits, billing). Every owner asked has an entry,
     * zero when it has nothing; add owners up with {@see WikiOwnerUsage::total()}. Three grouped queries per 500 owners.
     *
     * @param list<string> $ownerScopeIds
     *
     * @return array<string, WikiOwnerUsage> by owner id, in the order asked
     *
     * @throws InvalidArgumentException when an owner id is unusable
     */
    public function usage(WikiSpaceOwnerScope $ownerScopeType, array $ownerScopeIds): array;
}
