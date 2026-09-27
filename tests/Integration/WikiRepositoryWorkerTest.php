<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiSpaceRepository;
use PHPUnit\Framework\TestCase;

use function dirname;
use function extension_loaded;
use function is_file;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const PHP_VERSION_ID;

/**
 * Two entity managers on one SQLite file stand in for two FrankenPHP worker threads that are
 * never reset between requests.
 */
final class WikiRepositoryWorkerTest extends TestCase
{
    private string $dbFile = '';

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }

        $file = tempnam(sys_get_temp_dir(), 'wiki_worker_');
        self::assertIsString($file);
        $this->dbFile = $file;
    }

    protected function tearDown(): void
    {
        if ($this->dbFile !== '' && is_file($this->dbFile)) {
            unlink($this->dbFile);
        }
    }

    public function testPageArchivedOrRenamedByAnotherWorkerIsNotServedStale(): void
    {
        $workerA = $this->entityManager(true);
        $workerB = $this->entityManager();
        $spacesA = new DoctrineOrmWikiSpaceRepository($workerA);
        $pagesA  = new DoctrineOrmWikiPageRepository($workerA);
        $spacesB = new DoctrineOrmWikiSpaceRepository($workerB);
        $pagesB  = new DoctrineOrmWikiPageRepository($workerB);

        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $spacesA->save($space);
        $pagesA->save(new WikiPage($space, 'intro', 'Intro'));
        $pagesA->save(new WikiPage($space, 'setup', 'Setup'));

        // Request 1 on worker B loads everything into its identity map.
        $spaceB = $spacesB->findBySlug(WikiSpaceOwnerScope::Team, 'team-1', 'eng');
        self::assertNotNull($spaceB);
        $introB = $pagesB->findBySlug($spaceB, 'intro');
        self::assertNotNull($introB);
        self::assertFalse($introB->isArchived());
        self::assertCount(2, $pagesB->findActiveBySpace($spaceB));

        // Worker A archives "intro", renames "setup" and the space.
        $workerA->getConnection()->executeStatement("UPDATE wiki_pages SET archived_at = '2026-09-23 10:00:00' WHERE slug = 'intro'");
        $workerA->getConnection()->executeStatement("UPDATE wiki_pages SET title = 'Setup guide' WHERE slug = 'setup'");
        $workerA->getConnection()->executeStatement("UPDATE wiki_spaces SET name = 'Platform' WHERE slug = 'eng'");

        // Request 2 on worker B, nothing reset or cleared in between.
        $introAgain = $pagesB->findBySlug($spaceB, 'intro');
        self::assertNotNull($introAgain);
        self::assertTrue($introAgain->isArchived());

        $active = $pagesB->findActiveBySpace($spaceB);
        self::assertCount(1, $active);
        self::assertSame('Setup guide', $active[0]->getTitle());

        self::assertSame('Platform', $spacesB->findAccessible(WikiSpaceOwnerScope::Team->value, ['team-1'])[0]->getName());
        self::assertSame('Platform', $spacesB->findFirstBySlug('eng')?->getName());

        $workerA->getConnection()->executeStatement("UPDATE wiki_spaces SET name = 'Docs' WHERE slug = 'eng'");
        self::assertSame('Docs', $spacesB->findById($space->getId())?->getName());

        $workerA->getConnection()->executeStatement("UPDATE wiki_pages SET title = 'Intro v2' WHERE slug = 'intro'");
        self::assertSame('Intro v2', $pagesB->findById($introB->getId())?->getTitle());
        self::assertNull($pagesB->findById('missing'));
        self::assertNull($spacesB->findById('missing'));
    }

    public function testRepositoriesRecoverFromManagerClosedByPreviousRequest(): void
    {
        $fresh  = $this->entityManager(true);
        $closed = $this->createMock(EntityManagerInterface::class);
        $closed->method('isOpen')->willReturn(false);

        $registry = $this->createMock(ManagerRegistry::class);
        $calls    = 0;
        $registry->method('getManager')->with('wiki')->willReturnCallback(static function () use (&$calls, $closed, $fresh): EntityManagerInterface {
            return ++$calls === 1 ? $closed : $fresh;
        });
        $registry->expects(self::once())->method('resetManager')->with('wiki')->willReturn($fresh);

        $provider = new WikiEntityManagerProvider($registry, 'wiki');
        $spaces   = new DoctrineOrmWikiSpaceRepository($provider);
        $pages    = new DoctrineOrmWikiPageRepository($provider);
        $space    = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');

        $spaces->save($space);
        $pages->save(new WikiPage($space, 'intro', 'Intro'));

        self::assertSame(1, $pages->countBySpaceAndSlug($space, 'intro'));
        self::assertNotNull($pages->findBySlug($space, 'intro'));
    }

    private function entityManager(bool $createSchema = false): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            [dirname(__DIR__, 2) . '/src/Entity'],
            true,
            sys_get_temp_dir() . '/wiki_worker_proxies',
        );
        if (PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $this->dbFile], $config);
        $em         = new EntityManager($connection, $config);

        if ($createSchema) {
            (new SchemaTool($em))->createSchema([
                $em->getClassMetadata(WikiSpace::class),
                $em->getClassMetadata(WikiPage::class),
            ]);
        }

        return $em;
    }
}
