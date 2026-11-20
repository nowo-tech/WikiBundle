<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use Nowo\WikiBundle\Doctrine\Type\WikiUserRefType;
use Nowo\WikiBundle\Entity\WikiPageFavourite;
use Nowo\WikiBundle\Entity\WikiPageLink;
use Nowo\WikiBundle\Entity\WikiPageReference;
use Nowo\WikiBundle\Entity\WikiPageVisit;
use Nowo\WikiBundle\Entity\WikiSpace;
use Psr\Log\LoggerInterface;

use function array_map;
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
 *
 * 1.49.1 (audit XI-2): applied on `postGenerateSchema`, once the schema tool has added every foreign key, by table name.
 * 1.31.1–1.49.0 replaced each table on `postGenerateSchemaTable` (`setClassTable()`, DBAL 4.5+), and the ORM schema tool
 * (3.7.0 to 3.8.0) then added the table's foreign keys to the table it had replaced: on MySQL / MariaDB the generated
 * schema lost every foreign key starting on a collated table, and its index. Now:
 *
 * - DBAL 4.5+ with `GenerateSchemaEventArgs::setSchema()`: each table is rebuilt with its table editor (foreign keys
 *   and indexes included) and swapped into a new schema given back with `setSchema()`;
 * - otherwise: the columns' platform options are set in place (the DBAL 4.5+ deprecation silenced).
 */
final readonly class WikiSpaceCollationListener
{
    /** Mapped fields that get the binary collation. */
    public const FIELDS = ['ownerScopeId'];

    /** 1.37: the fields of a page link that hold a host object's type and id. */
    public const LINK_FIELDS = ['objectType', 'objectId'];

    /** 1.45: the person of a favourite or a visit (`Alice` is not `alice`). */
    public const QUICK_ACCESS_FIELDS = ['userId'];

    /** 1.45: the linked page of a backlink index row. */
    public const REFERENCE_FIELDS = ['targetPageId'];

    /**
     * @param EntityManagerInterface|WikiEntityManagerProvider|null $entityManager unused since 1.49.1 (the platform comes
     *                                                                             from the schema tool's entity manager); kept for BC
     * @param LoggerInterface|null $logger 1.36 (REQ-SEC-006 item 8): warned when only the PAD SPACE `utf8mb4_bin` is available
     */
    public function __construct(
        EntityManagerInterface|WikiEntityManagerProvider|null $entityManager = null,
        private ?LoggerInterface $logger = null,
    ) {
        unset($entityManager);
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

    /**
     * Gives the owner and user id columns of the wiki tables their binary collation in the generated schema, keeping
     * every foreign key and index of those tables.
     */
    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        $entityManager = $args->getEntityManager();
        // Only the schema of a manager that maps the wiki (with a private manager, the host's is left alone).
        if ($entityManager->getMetadataFactory()->isTransient(WikiSpace::class)) {
            return;
        }
        $collation = self::collationFor($entityManager->getConnection()->getDatabasePlatform());
        if ($collation === null) {
            return;
        }

        $schema = $args->getSchema();
        /** @var array<non-empty-string, list<non-empty-string>> $tables table name => the columns to collate */
        $tables = [];
        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $fields    = self::fieldsOf($metadata);
            $tableName = $metadata->getTableName();
            if ($fields === [] || $tableName === '' || !$schema->hasTable($tableName)) {
                continue;
            }
            /** @var list<non-empty-string> $columns mapped column names are never empty */
            $columns            = array_map($metadata->getColumnName(...), $fields);
            $tables[$tableName] = $columns;
            if ($collation === 'utf8mb4_bin') {
                $this->logger?->warning('nowo_wiki: the id columns of {table} get the PAD SPACE collation utf8mb4_bin (no binary NO PAD collation detected: MySQL before 8.0, or a doctrine server_version without the full version such as "8.0.36"); ids with trailing spaces are refused and every match is re-checked in PHP.', [
                    'table' => $tableName,
                ]);
            }
        }
        if ($tables === []) {
            return;
        }

        // Table::edit() and Schema::edit() exist from DBAL 4.5, GenerateSchemaEventArgs::setSchema() from ORM 3.7.
        // @phpstan-ignore function.alreadyNarrowedType, function.alreadyNarrowedType, function.alreadyNarrowedType
        $editor = method_exists(Table::class, 'edit') && method_exists(Schema::class, 'edit') && method_exists($args, 'setSchema');
        $edited = [];
        foreach ($tables as $tableName => $columns) {
            $table = $schema->getTable($tableName);
            foreach ($columns as $column) {
                $table = self::collate($table, $column, $collation, $editor) ?? $table;
            }
            $edited[$tableName] = $table;
        }
        if ($editor) {
            $schemaEditor = $schema->edit();
            foreach ($edited as $tableName => $table) {
                $schemaEditor->dropTableByUnquotedName($tableName)->addTable($table);
            }
            $args->setSchema($schemaEditor->create());
        }
    }

    /**
     * No longer subscribed (1.49.1, audit XI-2): replacing the table there lost its foreign keys. Kept for BC; does
     * nothing.
     *
     * @deprecated since 1.49.1, the collation is applied on postGenerateSchema ({@see self::postGenerateSchema()})
     */
    public function postGenerateSchemaTable(GenerateSchemaTableEventArgs $args): void
    {
        unset($args);
    }

    /**
     * The fields of a wiki class that hold an owner or user id: `owner_scope_id` of the spaces, 1.37 the linked host
     * object of a page link (`object_type`, `object_id`), 1.45 the person of a favourite or a visit (`user_id`) and the
     * target of a backlink index row (`target_page_id`) and, with
     * `database.user_reference: id` (1.36), every {@see WikiUserRefType} column (REQ-SEC-006 item 3).
     *
     * @param ClassMetadata<object> $metadata the class metadata
     *
     * @return list<string> the field names
     */
    public static function fieldsOf(ClassMetadata $metadata): array
    {
        $fields = match ($metadata->getName()) {
            WikiSpace::class => self::FIELDS,
            // 1.37: the host object a page is linked to (a task `ABC` is not the task `abc`).
            WikiPageLink::class => self::LINK_FIELDS,
            // 1.45: favourites and visits of one person; the index row's target page.
            WikiPageFavourite::class, WikiPageVisit::class => self::QUICK_ACCESS_FIELDS,
            WikiPageReference::class                       => self::REFERENCE_FIELDS,
            default                                        => [],
        };
        foreach ($metadata->fieldMappings as $field => $mapping) {
            if ($mapping->type === WikiUserRefType::NAME) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Gives `$column` the utf8mb4 charset and `$collation`: through a table editor (DBAL 4.5+; returns the new table) or
     * in place (returns null; the deprecation DBAL 4.5+ raises for it is silenced, for ORM versions without
     * `GenerateSchemaEventArgs::setSchema()`).
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

        $target = $table->getColumn($column);
        @$target->setPlatformOption('charset', 'utf8mb4');
        @$target->setPlatformOption('collation', $collation);

        return null;
    }
}
