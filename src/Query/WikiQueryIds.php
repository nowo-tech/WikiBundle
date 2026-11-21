<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Query;

use InvalidArgumentException;
use Nowo\WikiBundle\ValueObject\WikiOwnerScopeId;

use function array_slice;
use function array_values;
use function count;
use function is_string;
use function sprintf;

/**
 * Input rules of the 1.51 read ports: id lists are deduplicated (first occurrence kept), owner ids must be usable
 * ({@see WikiOwnerScopeId}), paging stays within bounds.
 *
 * @internal
 */
final class WikiQueryIds
{
    /** Most rows one call of a list method returns. */
    public const MAX_LIMIT = 500;

    /** Ids per SQL `IN (...)` list (larger lists are read in chunks). */
    public const CHUNK = 500;

    /** Most ids one filter of a criteria takes (it is a single `IN (...)` list, paged in SQL). */
    public const MAX_FILTER_IDS = 1000;

    /**
     * @param array<mixed> $ids ids (non-strings are dropped: they match no row)
     *
     * @return list<string> the distinct ids, first occurrence order
     */
    public static function ids(array $ids): array
    {
        $kept = [];
        foreach ($ids as $id) {
            if (is_string($id) && $id !== '') {
                $kept[$id] = $id; // the value, not the key: PHP turns a numeric-string key into an integer
            }
        }

        return array_values($kept);
    }

    /**
     * @param array<mixed> $ids owner ids
     *
     * @throws InvalidArgumentException when one is not a usable owner id
     *
     * @return list<string> the distinct owner ids
     */
    public static function owners(array $ids): array
    {
        $kept = [];
        foreach ($ids as $id) {
            if (!is_string($id) || !WikiOwnerScopeId::isUsable($id)) {
                throw new InvalidArgumentException('An owner id is 1 to 64 characters of valid UTF-8, without whitespace at its edges and without control or format characters.');
            }
            $kept[$id] = $id;
        }

        return array_values($kept);
    }

    /**
     * The ids of one criteria filter.
     *
     * @param list<string> $ids ids
     *
     * @throws InvalidArgumentException when there are more than {@see self::MAX_FILTER_IDS}
     *
     * @return list<string> the distinct ids
     */
    public static function filter(array $ids): array
    {
        return self::capped(self::ids($ids));
    }

    /**
     * The owner ids of one criteria filter.
     *
     * @param list<string> $ids owner ids
     *
     * @throws InvalidArgumentException when one is unusable or there are more than {@see self::MAX_FILTER_IDS}
     *
     * @return list<string> the distinct owner ids
     */
    public static function ownerFilter(array $ids): array
    {
        return self::capped(self::owners($ids));
    }

    /**
     * @throws InvalidArgumentException when the offset is negative or the limit outside 1 to {@see self::MAX_LIMIT}
     */
    public static function paging(int $offset, int $limit): void
    {
        if ($offset < 0 || $limit < 1 || $limit > self::MAX_LIMIT) {
            throw new InvalidArgumentException(sprintf('Paging needs an offset of 0 or more and a limit of 1 to %d.', self::MAX_LIMIT));
        }
    }

    /**
     * @param list<string> $ids ids
     *
     * @return list<list<string>> the ids in chunks of {@see self::CHUNK}
     */
    public static function chunks(array $ids): array
    {
        $chunks = [];
        for ($i = 0, $n = count($ids); $i < $n; $i += self::CHUNK) {
            $chunks[] = array_slice($ids, $i, self::CHUNK);
        }

        return $chunks;
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private static function capped(array $ids): array
    {
        if (count($ids) > self::MAX_FILTER_IDS) {
            throw new InvalidArgumentException(sprintf('A criteria filter takes at most %d ids; read larger sets by id in chunks.', self::MAX_FILTER_IDS));
        }

        return $ids;
    }
}
