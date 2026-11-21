<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Query;

use DateTimeImmutable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;

use function is_numeric;
use function is_scalar;

/**
 * The scalar selections of the 1.51 read ports and the snapshots built from their rows: the ports read columns, never
 * entities, so nothing they read enters an entity manager's identity map (the application's one included).
 *
 * @internal
 */
final class WikiQueryRows
{
    /** The space columns (alias `s`). */
    public const SPACE_FIELDS = ['s.id AS id', 's.slug AS slug', 's.name AS name', 's.ownerScopeType AS ownerScopeType', 's.ownerScopeId AS ownerScopeId', 's.createdAt AS createdAt', 's.updatedAt AS updatedAt', 's.icon AS icon', 's.archivedAt AS archivedAt', 's.version AS version'];

    /** The page columns (alias `p`). */
    public const PAGE_FIELDS = ['p.id AS id', 'IDENTITY(p.space) AS spaceId', 'p.slug AS slug', 'p.title AS title', 'IDENTITY(p.parent) AS parentId', 'p.position AS position', 'IDENTITY(p.currentRevision) AS currentRevisionId', 'p.archivedAt AS archivedAt', 'p.createdAt AS createdAt', 'p.updatedAt AS updatedAt', 'p.icon AS icon'];

    /**
     * @param array<string, mixed> $row a row of {@see self::SPACE_FIELDS}
     */
    public static function space(array $row): WikiSpaceSnapshot
    {
        $type = $row['ownerScopeType'];

        return new WikiSpaceSnapshot(
            self::string($row['id']),
            self::string($row['slug']),
            self::string($row['name']),
            $type instanceof WikiSpaceOwnerScope ? $type : WikiSpaceOwnerScope::from(self::string($type)),
            self::string($row['ownerScopeId']),
            self::date($row['createdAt']),
            self::date($row['updatedAt']),
            self::stringOrNull($row['icon']),
            self::dateOrNull($row['archivedAt']),
            self::int($row['version']),
        );
    }

    /**
     * @param array<string, mixed> $row a row of {@see self::PAGE_FIELDS}
     */
    public static function page(array $row): WikiPageSnapshot
    {
        return new WikiPageSnapshot(
            self::string($row['id']),
            self::string($row['spaceId']),
            self::string($row['slug']),
            self::string($row['title']),
            self::stringOrNull($row['parentId']),
            self::int($row['position']),
            self::stringOrNull($row['currentRevisionId']),
            self::dateOrNull($row['archivedAt']),
            self::date($row['createdAt']),
            self::date($row['updatedAt']),
            self::stringOrNull($row['icon']),
        );
    }

    /**
     * A date column read through an aggregate (`MAX()`), which the ORM hands back as the database's string.
     */
    public static function aggregateDate(mixed $value, AbstractPlatform $platform): ?DateTimeImmutable
    {
        if ($value === null || $value instanceof DateTimeImmutable) {
            return $value;
        }
        $date = Type::getType(Types::DATETIME_IMMUTABLE)->convertToPHPValue($value, $platform);

        return $date instanceof DateTimeImmutable ? $date : null;
    }

    public static function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    public static function stringOrNull(mixed $value): ?string
    {
        return $value === null ? null : self::string($value);
    }

    public static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function date(mixed $value): DateTimeImmutable
    {
        return $value instanceof DateTimeImmutable ? $value : new DateTimeImmutable(self::string($value));
    }

    private static function dateOrNull(mixed $value): ?DateTimeImmutable
    {
        return $value === null ? null : self::date($value);
    }
}
