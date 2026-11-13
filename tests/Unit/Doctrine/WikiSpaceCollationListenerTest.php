<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySQL84Platform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Doctrine\WikiSpaceCollationListener;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 1.31.1 (house rule LQ): `wiki_spaces.owner_scope_id` gets a binary NO PAD collation on MySQL / MariaDB.
 */
final class WikiSpaceCollationListenerTest extends TestCase
{
    /** @return iterable<string, array{AbstractPlatform, string|null}> */
    public static function platforms(): iterable
    {
        yield 'MariaDB' => [new MariaDBPlatform(), 'utf8mb4_nopad_bin'];
        yield 'MySQL 8.0' => [new MySQL80Platform(), 'utf8mb4_0900_bin'];
        yield 'MySQL 8.4' => [new MySQL84Platform(), 'utf8mb4_0900_bin'];
        yield 'MySQL 5.7' => [new MySQLPlatform(), 'utf8mb4_bin'];
        yield 'PostgreSQL' => [new PostgreSQLPlatform(), null];
        yield 'SQLite' => [new SQLitePlatform(), null];
    }

    #[DataProvider('platforms')]
    public function testCollationPerPlatform(AbstractPlatform $platform, ?string $expected): void
    {
        self::assertSame($expected, WikiSpaceCollationListener::collationFor($platform));
    }

    #[DataProvider('platforms')]
    public function testGeneratedSchemaCollatesTheOwnerColumnOnly(AbstractPlatform $platform, ?string $expected): void
    {
        $column = $this->generatedTable(new WikiSpaceCollationListener($this->entityManagerOn($platform)), WikiSpace::class)->getColumn('owner_scope_id');

        self::assertSame($expected, $column->getPlatformOptions()['collation'] ?? null);
        self::assertSame($expected === null ? null : 'utf8mb4', $column->getPlatformOptions()['charset'] ?? null);
    }

    public function testThroughTheEntityManagerProvider(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($this->entityManagerOn(new MariaDBPlatform()));
        $listener = new WikiSpaceCollationListener(new WikiEntityManagerProvider($registry, 'wiki'));

        $column = $this->generatedTable($listener, WikiSpace::class)->getColumn('owner_scope_id');

        self::assertSame('utf8mb4_nopad_bin', $column->getPlatformOptions()['collation'] ?? null);
    }

    public function testOtherTablesAreUntouched(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getConnection');

        $table = $this->generatedTable(new WikiSpaceCollationListener($entityManager), WikiPage::class);

        foreach ($table->getColumns() as $column) {
            self::assertArrayNotHasKey('collation', $column->getPlatformOptions());
        }
    }

    public function testCollatesInPlaceOnOlderDbal(): void
    {
        $table = new Table('wiki_spaces');
        $table->addColumn('owner_scope_id', Types::STRING, ['length' => 64]);

        self::assertNull(WikiSpaceCollationListener::collate($table, 'owner_scope_id', 'utf8mb4_0900_bin', false));
        self::assertSame('utf8mb4_0900_bin', $table->getColumn('owner_scope_id')->getPlatformOptions()['collation'] ?? null);
        self::assertSame('utf8mb4', $table->getColumn('owner_scope_id')->getPlatformOptions()['charset'] ?? null);
    }

    private function entityManagerOn(AbstractPlatform $platform): EntityManagerInterface
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('isOpen')->willReturn(true);

        return $entityManager;
    }

    /** @param class-string $class */
    private function generatedTable(WikiSpaceCollationListener $listener, string $class): Table
    {
        // The schema is built from the wiki mapping on SQLite in memory; the listener reads the platform of its own
        // entity manager, so a MySQL / MariaDB platform there decides the collation.
        $em = WikiTestDatabase::connect();
        self::assertInstanceOf(EntityManager::class, $em);
        $em->getEventManager()->addEventListener(ToolEvents::postGenerateSchemaTable, $listener);
        $metadata = $em->getMetadataFactory()->getMetadataFor($class);
        $schema   = (new SchemaTool($em))->getSchemaFromMetadata([$metadata]);
        $em->getConnection()->close();

        return $schema->getTable($metadata->getTableName());
    }
}
