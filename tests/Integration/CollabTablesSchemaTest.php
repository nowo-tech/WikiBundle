<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use Nowo\WikiBundle\Doctrine\WikiMetadataListener;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_map;
use function dirname;
use function method_exists;
use function sys_get_temp_dir;

use const PHP_VERSION_ID;

/**
 * 1.13.1 (Beacon request): without co-editing the generated schema has no collaboration tables, so upgrading hosts get
 * no schema diff until they enable it.
 */
final class CollabTablesSchemaTest extends TestCase
{
    /**
     * @return iterable<string, array{0: bool, 1: bool, 2?: string}>
     */
    public static function modes(): iterable
    {
        yield 'collaboration off' => [false, false];
        yield 'collaboration on' => [true, true];
        // 1.14: the trash table follows the trash switch the same way.
        yield 'trash off' => [false, false, 'trash'];
        yield 'trash on' => [true, true, 'trash'];
    }

    #[DataProvider('modes')]
    public function testTheCollaborationTablesFollowTheSwitch(bool $enabled, bool $expected, string $feature = 'collaboration'): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2) . '/src/Entity', __DIR__ . '/../Stub'], true, sys_get_temp_dir() . '/wiki_sqlite_proxies');
        if (PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }
        $trash    = $feature === 'trash';
        $listener = new WikiMetadataListener('w_spaces', 'w_pages', 'w_page_revisions', TestUserEntity::class, 'w_page_links', 'w_attachments', 'w_page_collab_documents', 'w_page_collab_updates', $trash || $enabled, 'w_page_trash', !$trash || $enabled);
        $events   = new EventManager();
        $events->addEventListener([Events::loadClassMetadata, ToolEvents::postGenerateSchema], $listener);
        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config, $events);

        $schema = (new SchemaTool($em))->getSchemaFromMetadata($em->getMetadataFactory()->getAllMetadata());
        $tables = array_map(static fn (Table $t): string => $t->getName(), $schema->getTables());

        self::assertContains('w_pages', $tables);
        if ($trash) {
            self::assertSame($expected, $schema->hasTable('w_page_trash'));
            self::assertTrue($schema->hasTable('w_page_collab_documents'));

            return;
        }
        self::assertSame($expected, $schema->hasTable('w_page_collab_documents'));
        self::assertSame($expected, $schema->hasTable('w_page_collab_updates'));
        self::assertTrue($schema->hasTable('w_page_trash'));
    }

    public function testOtherEntityManagersAreLeftAlone(): void
    {
        // A host's second entity manager without the wiki entities keeps its own tables, even with the same names.
        $factory = $this->createMock(ClassMetadataFactory::class);
        $factory->method('isTransient')->willReturn(true);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($factory);
        $schema = new Schema();
        $schema->createTable('w_page_collab_documents');

        (new WikiMetadataListener('s', 'p', 'r', TestUserEntity::class, 'l', 'a', 'w_page_collab_documents', 'w_page_collab_updates', false))
            ->postGenerateSchema(new GenerateSchemaEventArgs($em, $schema));
        self::assertTrue($schema->hasTable('w_page_collab_documents'));
    }
}
