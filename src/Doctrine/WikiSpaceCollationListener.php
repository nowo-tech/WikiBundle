<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use Nowo\WikiBundle\Doctrine\Type\WikiUserRefType;
use Nowo\WikiBundle\Entity\WikiSpace;
use Psr\Log\LoggerInterface;

use function method_exists;

/**
 * Exact-match collation for the owner of a space, `owner_scope_id`, on MySQL / MariaDB (1.31.1, house rule LQ).
 *
 * The default MySQL / MariaDB collations fold case and accents, and the PAD SPACE ones ignore trailing spaces, so
 * `Alice`, `álice` and `alice ` all equal `alice`. The repository already re-checks every row with `===`; the binary
 * NO PAD collation keeps the database itself exact (and the `(owner_scope_type, owner_scope_id, slug)` unique key with
 * it): MariaDB `utf8mb4_nopad_bin`, MySQL 8.0+ `utf8mb4_0900_bin`, MySQL 5.7 `utf8mb4_bin` (PAD SPACE, the only binary
 * one it has; the re-check covers it). PostgreSQL and SQLite compare exactly and are untouched (they reject MySQL
 * collation names, so it cannot live in the mapping). Applied to the generated schema (schema tool,
 * `doctrine:migrations:diff`); existing tables need the `ALTER TABLE` of UPGRADING.
 */
final readonly class WikiSpaceCollationListener
{
    /** Mapped fields that get the binary collation. */
    public const FIELDS = ['ownerScopeId'];

    /**
     * @param EntityManagerInterface|WikiEntityManagerProvider $entityManager the wiki's entity manager (its platform decides the collation)
     * @param LoggerInterface|null $logger 1.32 (REQ-SEC-006 item 8): warned when only the PAD SPACE `utf8mb4_bin` is available
     */
    public function __construct(
        private EntityManagerInterface|WikiEntityManagerProvider $entityManager,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * The exact-match collation on that platform, null when its default is already exact.
     *
     * @return non-empty-string|null
     */
    public static function collationFor(AbstractPlatform $platform): ?string
    {
        return match (true) {
            $platform instanceof MariaDBPlatform       => 'utf8mb4_nopad_bin',
            $platform instanceof MySQL80Platform       => 'utf8mb4_0900_bin',
            $platform instanceof AbstractMySQLPlatform => 'utf8mb4_bin',
            default                                    => null,
        };
    }

    /** Gives the owner column of the spaces table its binary collation. */
    public function postGenerateSchemaTable(GenerateSchemaTableEventArgs $args): void
    {
        $metadata = $args->getClassMetadata();
        $fields   = self::fieldsOf($metadata);
        if ($fields === []) {
            return;
        }
        $entityManager = $this->entityManager instanceof WikiEntityManagerProvider ? $this->entityManager->get() : $this->entityManager;
        $collation     = self::collationFor($entityManager->getConnection()->getDatabasePlatform());
        if ($collation === null) {
            return;
        }
        if ($collation === 'utf8mb4_bin') {
            $this->logger?->warning('nowo_wiki: the id columns of {table} get the PAD SPACE collation utf8mb4_bin (no binary NO PAD collation detected: MySQL before 8.0, or a doctrine server_version without the full version such as "8.0.36"); ids with trailing spaces are refused and every match is re-checked in PHP.', [
                'table' => $metadata->getTableName(),
            ]);
        }

        $table = $args->getClassTable();
        foreach ($fields as $field) {
            /** @var non-empty-string $column mapped column names are never empty */
            $column = $metadata->getColumnName($field);
            // @phpstan-ignore function.alreadyNarrowedType (Table::edit() exists only from DBAL 4.5)
            $edited = self::collate($table, $column, $collation, method_exists(Table::class, 'edit'));
            if ($edited instanceof Table) {
                $table = $edited;
            }
        }
        if ($table !== $args->getClassTable()) {
            $args->setClassTable($table);
        }
    }

    /**
     * The fields of a wiki class that hold an owner or user id: `owner_scope_id` of the spaces and, with
     * `database.user_reference: id` (1.32), every {@see WikiUserRefType} column (REQ-SEC-006 item 3).
     *
     * @param ClassMetadata<object> $metadata the class metadata
     *
     * @return list<string> the field names
     */
    public static function fieldsOf(ClassMetadata $metadata): array
    {
        $fields = $metadata->getName() === WikiSpace::class ? self::FIELDS : [];
        foreach ($metadata->fieldMappings as $field => $mapping) {
            if ($mapping->type === WikiUserRefType::NAME) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Gives `$column` the utf8mb4 charset and `$collation`: through a table editor (DBAL 4.5+; returns the new table) or
     * in place (older DBAL; returns null).
     *
     * @param non-empty-string $column
     * @param non-empty-string $collation
     */
    public static function collate(Table $table, string $column, string $collation, bool $editor): ?Table
    {
        if ($editor) {
            return $table->edit()->modifyColumnByUnquotedName($column, static function (ColumnEditor $columnEditor) use ($collation): void {
                $columnEditor->setCharset('utf8mb4')->setCollation($collation);
            })->create();
        }

        $table->getColumn($column)
            ->setPlatformOption('charset', 'utf8mb4')
            ->setPlatformOption('collation', $collation);

        return null;
    }
}
