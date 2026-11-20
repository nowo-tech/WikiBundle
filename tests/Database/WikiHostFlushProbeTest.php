<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\TransactionDoctrine\HostFlushProbe;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Doctrine\WikiHostFlushProbe;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiSpaceRepository;
use Nowo\WikiBundle\Service\WikiPageOperationsInterface;
use Nowo\WikiBundle\Service\WikiSpaceOperationsInterface;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_filter;
use function array_values;
use function restore_error_handler;
use function set_error_handler;
use function str_contains;

use const E_USER_DEPRECATED;

/**
 * 1.49 (REQ-ARCH-001 rule 6 deprecation layer): the host-flush probe of the shared manager, on a real database (SQLite
 * by default; MySQL, MariaDB and PostgreSQL through WIKI_TEST_DATABASE_URL). It reports the application's pending
 * changes a wiki flush writes (one notice per class per unit of work), and wiki rows the application writes with its
 * own manager (naming the id operation to use instead), and it changes nothing.
 */
final class WikiHostFlushProbeTest extends TestCase
{
    private EntityManager $em;

    private WikiHostFlushProbe $probe;

    private WikiEntityManagerProvider $provider;

    /** @var list<string> */
    private array $deprecations = [];

    protected function setUp(): void
    {
        $this->em = WikiTestDatabase::create();
        // Without transaction-doctrine's bundle: the wiki's own probe, reset with it.
        $this->probe = new WikiHostFlushProbe();
        $registry    = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($this->em);
        $this->provider = new WikiEntityManagerProvider($registry, 'default', null, $this->probe);
        // As the bundle registers it (shared manager only): the application's own flushes.
        $this->em->getEventManager()->addEventListener([Events::onFlush], $this->probe);
        set_error_handler(function (int $level, string $message): bool {
            $this->deprecations[] = $message;

            return true;
        }, E_USER_DEPRECATED);
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        $this->em->getEventManager()->removeEventListener([Events::onFlush], $this->probe);
        $this->em->getConnection()->close();
    }

    public function testAWikiFlushReportsTheApplicationsPendingChangesOncePerClassAndUnitOfWork(): void
    {
        $spaces = new DoctrineOrmWikiSpaceRepository($this->provider);
        $this->em->persist(new TestUserEntity('pending-1'));
        $spaces->save(new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1'));

        self::assertSame(['Your pending changes to ' . TestUserEntity::class . ' were flushed by NowoWikiBundle; 2.0 no longer does.'], $this->probeNotices());
        self::assertSame(1, $this->rows('test_users'), 'host mode is unchanged: the wiki write still flushed the pending row');

        $this->em->persist(new TestUserEntity('pending-2'));
        $spaces->save(new WikiSpace('ops', 'Ops', WikiSpaceOwnerScope::Team, 'team-1'));
        self::assertCount(1, $this->probeNotices(), 'once per class per unit of work');

        // The next request, message or command.
        $this->probe->reset();
        $this->em->persist(new TestUserEntity('pending-3'));
        $spaces->save(new WikiSpace('dev', 'Dev', WikiSpaceOwnerScope::Team, 'team-1'));
        self::assertCount(2, $this->probeNotices(), 'reported again after the boundary');
        self::assertSame([], $this->ownManagerNotices(), 'the wiki\'s own writes are never "your rows"');
    }

    public function testAWikiFlushWithNothingOfTheApplicationPendingReportsNothing(): void
    {
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        (new DoctrineOrmWikiSpaceRepository($this->provider))->save($space);
        (new DoctrineOrmWikiPageRepository($this->provider))->save(new WikiPage($space, 'home', 'Home'));

        self::assertSame([], $this->deprecations);
    }

    public function testTheRunnersFlushOfTheSharedManagerIsProbedToo(): void
    {
        $runner = new WikiTransactionRunner($this->provider, backoffBaseMilliseconds: 0);
        $this->em->persist(new TestUserEntity('pending-runner'));
        $runner->run(function (): void {
            $this->em->persist(new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1'));
        });

        self::assertCount(1, $this->probeNotices());
        self::assertStringContainsString(TestUserEntity::class, $this->probeNotices()[0]);
        self::assertSame([], $this->ownManagerNotices());
        self::assertSame(1, $this->rows('wiki_spaces'));
    }

    public function testWikiRowsTheApplicationWritesWithItsOwnManagerNameTheOperationToUse(): void
    {
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->em->persist($space);
        $this->em->persist(new WikiSpace('ops', 'Ops', WikiSpaceOwnerScope::Team, 'team-1'));
        $page = new WikiPage($space, 'home', 'Home');
        $this->em->persist($page);
        $this->em->flush();

        $notices = $this->ownManagerNotices();
        self::assertCount(2, $notices, 'one per class and kind of write, not one per row');
        self::assertStringContainsString('Your application inserted ' . WikiSpace::class . ' rows', $notices[0]);
        self::assertStringContainsString(WikiSpaceOperationsInterface::class . '::createSpace()', $notices[0]);
        self::assertStringContainsString('Your application inserted ' . WikiPage::class . ' rows', $notices[1]);
        self::assertStringContainsString(WikiPageOperationsInterface::class . '::createPage()', $notices[1]);

        $page->setTitle('Home page');
        $space->setName('Engineering team');
        $this->em->flush();
        $notices = $this->ownManagerNotices();
        self::assertCount(4, $notices);
        self::assertStringContainsString('updated ' . WikiSpace::class . ' rows', $notices[2] . $notices[3]);
        self::assertStringContainsString('no id operation exists for this write yet', $notices[2] . $notices[3]);
        self::assertStringContainsString(WikiPageOperationsInterface::class . ' (saveRevision()', $notices[2] . $notices[3]);
        self::assertSame([], $this->probeNotices(), 'the application\'s own flush is not a wiki flush');
        self::assertSame(2, $this->rows('wiki_spaces'), 'nothing is held back: the probe only reports');
    }

    public function testTheProbeNeverClearsDetachesOrResetsTheApplicationsManager(): void
    {
        $user = new TestUserEntity('pending-kept');
        $this->em->persist($user);
        $this->probe->around($this->em, fn () => $this->em->flush());

        self::assertTrue($this->em->isOpen());
        self::assertTrue($this->em->contains($user), 'still managed');
        self::assertCount(1, $this->probeNotices());
    }

    public function testASharedProbeIsResetByTransactionDoctrineNotByTheWiki(): void
    {
        $shared   = new HostFlushProbe();
        $probe    = new WikiHostFlushProbe($shared);
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($this->em);
        $spaces = new DoctrineOrmWikiSpaceRepository(new WikiEntityManagerProvider($registry, 'default', null, $probe));

        $this->em->persist(new TestUserEntity('shared-1'));
        $spaces->save(new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1'));
        $probe->reset();
        $this->em->persist(new TestUserEntity('shared-2'));
        $spaces->save(new WikiSpace('ops', 'Ops', WikiSpaceOwnerScope::Team, 'team-1'));
        self::assertCount(1, $this->probeNotices(), 'the shared probe keeps its unit of work until its own boundary');

        $shared->reset();
        $this->em->persist(new TestUserEntity('shared-3'));
        $spaces->save(new WikiSpace('dev', 'Dev', WikiSpaceOwnerScope::Team, 'team-1'));
        self::assertCount(2, $this->probeNotices());
    }

    public function testAProxiedWikiRowIsNamedByItsEntityClass(): void
    {
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        (new DoctrineOrmWikiSpaceRepository($this->provider))->save($space);
        $id = $space->getId();
        $this->em->clear();

        $reference = $this->em->getReference(WikiSpace::class, $id);
        self::assertInstanceOf(WikiSpace::class, $reference);
        $reference->setName('Renamed');
        $this->em->flush();

        $notices = $this->ownManagerNotices();
        self::assertCount(1, $notices);
        self::assertStringContainsString('Your application updated ' . WikiSpace::class . ' rows', $notices[0]);
    }

    public function testADeprecationHandlerThatThrowsNeverFailsAWrite(): void
    {
        set_error_handler(static function (): bool {
            throw new RuntimeException('deprecations are errors here');
        }, E_USER_DEPRECATED);
        try {
            $this->em->persist(new TestUserEntity('pending-strict'));
            (new DoctrineOrmWikiSpaceRepository($this->provider))->save(new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1'));
            $this->em->persist(new WikiSpace('ops', 'Ops', WikiSpaceOwnerScope::Team, 'team-1'));
            $this->em->flush();
        } finally {
            restore_error_handler();
        }

        self::assertSame(1, $this->rows('test_users'));
        self::assertSame(2, $this->rows('wiki_spaces'));
    }

    /** @return list<string> the probe's "flushed by" notices */
    private function probeNotices(): array
    {
        return array_values(array_filter($this->deprecations, static fn (string $message): bool => str_contains($message, 'flushed by NowoWikiBundle')));
    }

    /** @return list<string> the notices of wiki rows written with the application's manager */
    private function ownManagerNotices(): array
    {
        return array_values(array_filter($this->deprecations, static fn (string $message): bool => str_contains($message, 'with its own entity manager')));
    }

    private function rows(string $table): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . $table);
    }
}
