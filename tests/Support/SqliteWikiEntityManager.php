<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\WikiBundle\Doctrine\WikiMetadataListener;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;

use function dirname;
use function method_exists;
use function sys_get_temp_dir;

use const PHP_VERSION_ID;

/**
 * In-memory SQLite entity manager with every wiki entity and {@see TestUserEntity} as the user class.
 */
final class SqliteWikiEntityManager
{
    public static function create(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            [dirname(__DIR__, 2) . '/src/Entity', __DIR__ . '/../Stub'],
            true,
            sys_get_temp_dir() . '/wiki_sqlite_proxies',
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

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $em         = new EntityManager($connection, $config, $events);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

        return $em;
    }
}
