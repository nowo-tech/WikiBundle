<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySQL84Platform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Doctrine\WikiMetadataListener;
use Nowo\WikiBundle\Doctrine\WikiSpaceCollationListener;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageFavourite;
use Nowo\WikiBundle\Entity\WikiPageReference;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiPageVisit;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function count;

/**
 * 1.31.1 (house rule LQ): `wiki_spaces.owner_scope_id` gets a binary NO PAD collation on MySQL / MariaDB; 1.49.1 (audit
 * XI-2) on `postGenerateSchema`, from the schema tool's own platform.
 */
final class WikiSpaceCollationListenerTest extends TestCase
{
    private const MARIADB = 'mysql://u@127.0.0.1/wiki?serverVersion=11.4.0-MariaDB';

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

    /** @return iterable<string, array{string|null, string|null}> the schema tool's database (null: SQLite in memory; the PostgreSQL schema manager would connect), the collation */
    public static function databases(): iterable
    {
        yield 'MariaDB' => ['mysql://u@127.0.0.1/wiki?serverVersion=11.4.0-MariaDB', 'utf8mb4_nopad_bin'];
        yield 'MySQL 8.0' => ['mysql://u@127.0.0.1/wiki?serverVersion=8.0.36', 'utf8mb4_0900_bin'];
        yield 'MySQL 8.4' => ['mysql://u@127.0.0.1/wiki?serverVersion=8.4.0', 'utf8mb4_0900_bin'];
        yield 'MySQL 5.7' => ['mysql://u@127.0.0.1/wiki?serverVersion=5.7.44', 'utf8mb4_bin'];
        yield 'SQLite' => [null, null];
    }

    /** 1.49.1: the schema tool's own connection decides (the servers are never connected to). */
    #[DataProvider('databases')]
    public function testGeneratedSchemaCollatesTheOwnerColumnOnly(?string $url, ?string $expected): void
    {
        $table  = $this->generatedTable(new WikiSpaceCollationListener(), WikiSpace::class, $url);
        $column = $table->getColumn('owner_scope_id');

        self::assertSame($expected, $column->getPlatformOptions()['collation'] ?? null);
        self::assertSame($expected === null ? null : 'utf8mb4', $column->getPlatformOptions()['charset'] ?? null);
        self::assertArrayNotHasKey('collation', $table->getColumn('slug')->getPlatformOptions());
    }

    /** The constructor arguments of 1.31.1–1.49.0 are still accepted (unused). */
    public function testTheFormerConstructorArgumentsAreAccepted(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        foreach ([new WikiEntityManagerProvider($registry, 'wiki'), $this->createStub(EntityManagerInterface::class)] as $entityManager) {
            $column = $this->generatedTable(new WikiSpaceCollationListener($entityManager), WikiSpace::class, self::MARIADB)->getColumn('owner_scope_id');

            self::assertSame('utf8mb4_nopad_bin', $column->getPlatformOptions()['collation'] ?? null);
        }
    }

    public function testOtherTablesAreUntouched(): void
    {
        $table = $this->generatedTable(new WikiSpaceCollationListener(), WikiPage::class, self::MARIADB);

        foreach ($table->getColumns() as $column) {
            self::assertArrayNotHasKey('collation', $column->getPlatformOptions());
        }
    }

    /** A manager that does not map the wiki (the host's, with a private wiki manager) is left alone. */
    public function testAManagerWithoutTheWikiIsLeftAlone(): void
    {
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('isTransient')->willReturn(true);
        $metadataFactory->expects(self::never())->method('getAllMetadata');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getMetadataFactory')->willReturn($metadataFactory);
        $entityManager->expects(self::never())->method('getConnection');
        $schema = new Schema();
        $args   = new GenerateSchemaEventArgs($entityManager, $schema);

        (new WikiSpaceCollationListener())->postGenerateSchema($args);

        self::assertSame($schema, $args->getSchema());
    }

    /** A schema without the wiki tables (a subset of the classes) is left as it is. */
    public function testASchemaWithoutTheWikiTablesIsLeftAsItIs(): void
    {
        $em     = WikiTestDatabase::connect(null, WikiMetadataListener::USER_REFERENCE_ENTITY, self::MARIADB);
        $schema = new Schema();
        $args   = new GenerateSchemaEventArgs($em, $schema);

        (new WikiSpaceCollationListener())->postGenerateSchema($args);

        self::assertSame($schema, $args->getSchema());
    }

    public function testCollatesInPlaceOrThroughTheEditor(): void
    {
        $table = new Table('wiki_spaces');
        $table->addColumn('owner_scope_id', Types::STRING, ['length' => 64]);

        self::assertNull(WikiSpaceCollationListener::collate($table, 'owner_scope_id', 'utf8mb4_0900_bin', false));
        self::assertSame('utf8mb4_0900_bin', $table->getColumn('owner_scope_id')->getPlatformOptions()['collation'] ?? null);
        self::assertSame('utf8mb4', $table->getColumn('owner_scope_id')->getPlatformOptions()['charset'] ?? null);

        $edited = WikiSpaceCollationListener::collate($table, 'owner_scope_id', 'utf8mb4_nopad_bin', true);
        self::assertInstanceOf(Table::class, $edited);
        self::assertNotSame($table, $edited);
        self::assertSame('utf8mb4_nopad_bin', $edited->getColumn('owner_scope_id')->getPlatformOptions()['collation'] ?? null);
    }

    /** 1.36 (`database.user_reference: id`, REQ-SEC-006 item 3): every stored user id column is collated too. */
    #[DataProvider('databases')]
    public function testIdModeCollatesTheUserColumns(?string $url, ?string $expected): void
    {
        $logger = new RecordingLogger();
        $table  = $this->generatedTable(new WikiSpaceCollationListener(null, $logger), WikiCommentThread::class, $url, WikiMetadataListener::USER_REFERENCE_ID);

        foreach (['created_by_id', 'resolved_by_id'] as $column) {
            self::assertSame($expected, $table->getColumn($column)->getPlatformOptions()['collation'] ?? null, $column);
        }
        self::assertArrayNotHasKey('collation', $table->getColumn('quote')->getPlatformOptions());
        // REQ-SEC-006 item 8: the PAD SPACE fallback is reported, once per collated table.
        self::assertSame($expected === 'utf8mb4_bin' ? 1 : 0, count($logger->records));
    }

    public function testFieldsOf(): void
    {
        $em = WikiTestDatabase::connect(null, WikiMetadataListener::USER_REFERENCE_ID);
        self::assertSame(['ownerScopeId'], WikiSpaceCollationListener::fieldsOf($em->getClassMetadata(WikiSpace::class)));
        self::assertSame(['author'], WikiSpaceCollationListener::fieldsOf($em->getClassMetadata(WikiPageRevision::class)));
        self::assertSame([], WikiSpaceCollationListener::fieldsOf(WikiTestDatabase::connect()->getClassMetadata(WikiPageRevision::class)));
        // 1.45: the person of a favourite or a visit, the target of a backlink index row.
        self::assertSame(['userId'], WikiSpaceCollationListener::fieldsOf($em->getClassMetadata(WikiPageFavourite::class)));
        self::assertSame(['userId'], WikiSpaceCollationListener::fieldsOf($em->getClassMetadata(WikiPageVisit::class)));
        self::assertSame(['targetPageId'], WikiSpaceCollationListener::fieldsOf($em->getClassMetadata(WikiPageReference::class)));
        $em->getConnection()->close();
    }

    /** @param class-string $class */
    private function generatedTable(WikiSpaceCollationListener $listener, string $class, ?string $url, string $userReference = WikiMetadataListener::USER_REFERENCE_ENTITY): Table
    {
        // The schema of one wiki class, generated for the platform of `$url` (never connected to; null: SQLite).
        $em = WikiTestDatabase::connect(null, $userReference, $url);
        self::assertInstanceOf(EntityManager::class, $em);
        $em->getEventManager()->addEventListener(ToolEvents::postGenerateSchema, $listener);
        $metadata = $em->getMetadataFactory()->getMetadataFor($class);
        $schema   = (new SchemaTool($em))->getSchemaFromMetadata([$metadata]);
        $em->getConnection()->close();

        return $schema->getTable($metadata->getTableName());
    }
}
