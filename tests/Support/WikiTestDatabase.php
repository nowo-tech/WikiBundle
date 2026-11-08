<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\WikiBundle\Doctrine\WikiMetadataListener;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;

use function dirname;
use function getenv;
use function is_string;
use function method_exists;
use function sys_get_temp_dir;

use const PHP_VERSION_ID;

/**
 * Spec 218: the database of the `database` test suite. `WIKI_TEST_DATABASE_URL` names a MySQL or PostgreSQL database
 * (CI runs both); without it, SQLite in memory. The schema is dropped and created again for each entity manager built
 * by {@see self::create()}, so the database must be a throwaway one.
 */
final class WikiTestDatabase
{
    public const ENV = 'WIKI_TEST_DATABASE_URL';

    /** The URL of the external database, or null for SQLite in memory. */
    public static function url(): ?string
    {
        $url = getenv(self::ENV);

        return is_string($url) && $url !== '' ? $url : null;
    }

    /** Whether the tests run against a server (MySQL / PostgreSQL), where a second connection sees the first one's commits. */
    public static function isServer(): bool
    {
        return self::url() !== null;
    }

    /** A fresh schema and an entity manager on it. */
    public static function create(): EntityManager
    {
        $em   = self::connect();
        $tool = new SchemaTool($em);
        $all  = $em->getMetadataFactory()->getAllMetadata();
        if (self::isServer()) {
            $tool->dropDatabase();
        }
        $tool->createSchema($all);

        return $em;
    }

    /** Another entity manager (and connection) on the same database, schema untouched: for concurrency tests. */
    public static function connect(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            [dirname(__DIR__, 2) . '/src/Entity', __DIR__ . '/../Stub'],
            true,
            sys_get_temp_dir() . '/wiki_db_proxies',
        );
        if (PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }

        $events = new EventManager();
        $events->addEventListener(Events::loadClassMetadata, new WikiMetadataListener(
            'wiki_spaces',
            'wiki_pages',
            'wiki_page_revisions',
            TestUserEntity::class,
            'wiki_page_links',
            'wiki_attachments',
        ));

        return new EntityManager(self::connection($config), $config, $events);
    }

    private static function connection(Configuration $config): Connection
    {
        $url = self::url();
        if ($url === null) {
            return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        }

        $params = (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql']))->parse($url);

        return DriverManager::getConnection($params, $config);
    }
}
