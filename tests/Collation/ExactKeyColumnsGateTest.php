<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Collation;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use Nowo\MigrationsKitBundle\Test\ExactKeyColumnsTestCase;
use Nowo\WikiBundle\Doctrine\Type\WikiUserRefType;
use Nowo\WikiBundle\Doctrine\WikiExactKeyColumns;
use Nowo\WikiBundle\Doctrine\WikiMetadataListener;
use Nowo\WikiBundle\Doctrine\WikiSpaceCollationListener;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;

use function dirname;
use function getmypid;
use function method_exists;
use function sys_get_temp_dir;

use const PHP_VERSION_ID;

/**
 * REQ-SEC-006 on a real server ({@see WikiTestDatabase::ENV}: MySQL 8.4 and MariaDB 11.4 in CI): the bundle's own
 * gate over its own declaration, with `database.user_reference: id` so the stored user id columns are checked too.
 *
 * The tables are created by the schema tool with the bundle's own metadata and collation listeners, under a table
 * prefix that is not the default, and every declared column is then read back from `information_schema`. A key
 * column that lost its binary NO PAD collation — or a new one nobody collated — fails *this bundle's* pipeline, long
 * before a host installs it, and the prefix proves the declaration resolves the configured table names.
 *
 * Skips cleanly on PostgreSQL and SQLite, which compare exactly by default, and without the variable.
 */
final class ExactKeyColumnsGateTest extends ExactKeyColumnsTestCase
{
    /** Not the default prefix: the declaration must follow the configured names. */
    private const PREFIX = 'exactkey_wiki';

    /**
     * {@inheritdoc}
     */
    protected function databaseUrlVariable(): string
    {
        return WikiTestDatabase::ENV;
    }

    /**
     * {@inheritdoc}
     */
    protected function exactKeyColumnProviders(): array
    {
        return [new WikiExactKeyColumns(
            spacesTableName: self::PREFIX . '_spaces',
            revisionsTableName: self::PREFIX . '_page_revisions',
            linksTableName: self::PREFIX . '_page_links',
            attachmentsTableName: self::PREFIX . '_attachments',
            commentTablePrefix: self::PREFIX,
            quickAccessTablePrefix: self::PREFIX,
            userIds: true,
            comments: true,
            favourites: true,
            recents: true,
            backlinks: true,
        )];
    }

    /**
     * {@inheritdoc}
     */
    protected function createSchema(Connection $connection): void
    {
        if (!Type::hasType(WikiUserRefType::NAME)) {
            Type::addType(WikiUserRefType::NAME, WikiUserRefType::class);
        }

        $config = ORMSetup::createAttributeMetadataConfiguration(
            [dirname(__DIR__, 2) . '/src/Entity', __DIR__ . '/../Stub'],
            true,
            sys_get_temp_dir() . '/wiki_exactkey_proxies_' . getmypid(),
        );
        if (PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }

        $events = new EventManager();
        $events->addEventListener(Events::loadClassMetadata, new WikiMetadataListener(
            self::PREFIX . '_spaces',
            self::PREFIX . '_pages',
            self::PREFIX . '_page_revisions',
            TestUserEntity::class,
            self::PREFIX . '_page_links',
            self::PREFIX . '_attachments',
            collabDocumentsTableName: self::PREFIX . '_page_collab_documents',
            collabUpdatesTableName: self::PREFIX . '_page_collab_updates',
            trashTableName: self::PREFIX . '_page_trash',
            revisionLabelsTableName: self::PREFIX . '_revision_labels',
            commentTablePrefix: self::PREFIX,
            apiIdempotencyTableName: self::PREFIX . '_api_idempotency',
            userReference: WikiMetadataListener::USER_REFERENCE_ID,
            quickAccessTablePrefix: self::PREFIX,
        ));

        $manager = new EntityManager($connection, $config, $events);
        $manager->getEventManager()->addEventListener(ToolEvents::postGenerateSchema, new WikiSpaceCollationListener());

        /** @var list<ClassMetadata<object>> $classes */
        $classes = $manager->getMetadataFactory()->getAllMetadata();

        // The earlier steps of the same CI job leave their own tables behind (the suites share one database), and a
        // table of a mapped class that is still there fails createSchema. Drop exactly the tables of this metadata,
        // foreign keys first: this test only ever runs on MySQL / MariaDB, so the pragma is safe.
        $platform = $connection->getDatabasePlatform();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            foreach ($classes as $metadata) {
                $table = $metadata->getTableName();
                if ($table !== '') {
                    $connection->executeStatement('DROP TABLE IF EXISTS ' . $platform->quoteIdentifier($table));
                }
            }

            (new SchemaTool($manager))->createSchema($classes);
        } finally {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}
