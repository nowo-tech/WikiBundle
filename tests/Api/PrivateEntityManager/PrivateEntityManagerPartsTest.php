<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\PrivateEntityManager;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use LogicException;
use Nowo\WikiBundle\DependencyInjection\Compiler\PrivateEntityManagerPass;
use Nowo\WikiBundle\Doctrine\PrivateEntityManagerConnection;
use Nowo\WikiBundle\Doctrine\PrivateEntityManagerMiddleware;
use Nowo\WikiBundle\Doctrine\WikiEntityManager;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Doctrine\WikiSavepoint;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Tests\Api\App\ApiKernel;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\AbstractLogger;
use ReflectionProperty;
use RuntimeException;
use Stringable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\KernelInterface;
use Throwable;

use function array_filter;
use function array_values;
use function assert;
use function bin2hex;
use function json_encode;
use function random_bytes;

/**
 * 1.33.0 (REQ-ARCH-001 rule 6): the parts of the opt-in private entity manager, adapted from TaskBoardBundle 2.15.0 /
 * AuthKitBundle 1.46.0 (audits NJ-2, NJV-1, NF-2, NF2-2, NF3-2, NF-7, NZ): the rollback middleware, the replacement
 * rules, the boundary drop that cannot be poisoned, the adopted host users, the repositories, the savepoint helper, the
 * savepoint rollback that never hides the original failure, and the build pass.
 */
final class PrivateEntityManagerPartsTest extends KernelTestCase
{
    /**
     * @param array<string, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new ApiKernel([], ['database' => ['private_entity_manager' => true]]);
    }

    #[Test]
    public function theProviderServesThePrivateManagerAndTheBoundaryDropsIt(): void
    {
        $this->doctrine();
        $provider = self::getContainer()->get(WikiEntityManagerProvider::class);
        assert($provider instanceof WikiEntityManagerProvider);
        $private = $provider->get();
        self::assertInstanceOf(WikiEntityManager::class, $private);
        self::assertNotSame(self::getContainer()->get('doctrine')->getManager(), $private->live());

        $space = $this->space('boundary');
        $private->persist($space);
        $private->flush();
        self::assertTrue($private->isCreated());
        self::getContainer()->get('event_dispatcher')->dispatch(new ConsoleTerminateEvent(
            new Command('x'),
            new ArrayInput([]),
            new NullOutput(),
            0,
        ), 'console.terminate');
        self::assertFalse($private->isCreated(), 'dropped at the console boundary');
    }

    #[Test]
    public function aWikiFlushNeverWritesTheApplicationsPendingChanges(): void
    {
        $doctrine = $this->doctrine();
        $host     = $doctrine->getManager();
        assert($host instanceof EntityManagerInterface);
        $user = new TestUserEntity('host-user');
        $host->persist($user);
        $host->flush();
        $pending = new TestUserEntity('pending-user');
        $host->persist($pending);

        $private = new WikiEntityManager($doctrine);
        $space   = $this->space('adopt');
        $page    = new WikiPage($space, 'p', 'P');
        // The host's managed user on a wiki row: adopted as the private manager's reference to the same id.
        $revision = new WikiPageRevision($page, 1, '<p>x</p>', $user);
        $private->persist($space);
        $private->persist($page);
        $private->persist($revision);
        $private->flush();

        self::assertTrue($host->getUnitOfWork()->isScheduledForInsert($pending), 'the application change is still pending');
        self::assertSame(0, (int) $host->getConnection()->fetchOne('SELECT COUNT(*) FROM test_users WHERE id = ?', ['pending-user']));
        self::assertSame(1, (int) $host->getConnection()->fetchOne('SELECT COUNT(*) FROM wiki_page_revisions WHERE author_id = ?', ['host-user']));
        self::assertNotSame($user, $revision->getAuthor(), 'the application instance is never written');
        self::assertTrue($host->contains($user));
        $host->clear();
    }

    #[Test]
    public function onlyARollbackOfRowsThePrivateManagerWroteMakesItStale(): void
    {
        $private = $this->privateManager();
        $real    = $private->getConnection();
        $inner   = $this->createMock(DriverConnection::class);
        $inner->method('exec')->willReturn(0);
        $inner->method('commit')->willReturnOnConsecutiveCalls(null, self::throwException(new RuntimeException('commit failed')));
        $connection = new PrivateEntityManagerConnection($inner, $private);
        $real->beginTransaction();

        $this->write($private);
        $connection->exec('RELEASE SAVEPOINT DOCTRINE_2');
        $connection->exec('ROLLBACK TO SAVEPOINT nowo_wiki_0a1b2c');
        $connection->exec('ROLLBACK TO SAVEPOINT DOCTRINE_2');
        self::assertFalse($private->isStale(), 'NJ-2: a wiki savepoint or a deeper level the manager did not write in');
        $connection->exec('ROLLBACK TO SAVEPOINT host_point');
        self::assertTrue($private->isStale(), 'a savepoint of unknown level: conservative');

        $private->reset();
        $this->write($private);
        $connection->rollBack();
        self::assertTrue($private->isStale());

        $private->reset();
        $this->write($private);
        $connection->commit();
        $connection->rollBack();
        self::assertFalse($private->isStale(), 'committed rows are not undone by a later rollback');

        $private->reset();
        $this->write($private);
        try {
            $connection->commit();
            self::fail('the commit failure is rethrown');
        } catch (RuntimeException) {
        }
        self::assertTrue($private->isStale());

        $private->reset();
        $real->beginTransaction();
        $this->write($private);
        $connection->exec('RELEASE SAVEPOINT DOCTRINE_2');
        $connection->exec('ROLLBACK TO SAVEPOINT DOCTRINE_2');
        self::assertFalse($private->isStale());
        $connection->rollBack();
        self::assertTrue($private->isStale());
        $real->rollBack();
        $real->rollBack();

        $driver = $this->createMock(Driver::class);
        $driver->method('connect')->willReturn($this->createMock(DriverConnection::class));
        self::assertInstanceOf(PrivateEntityManagerConnection::class, (new PrivateEntityManagerMiddleware($private))->wrap($driver)->connect([]));
    }

    #[Test]
    public function aStaleManagerWithPendingWorkIsKeptUntilFlushed(): void
    {
        $private = $this->privateManager();
        $real    = $private->getConnection();
        $real->beginTransaction();
        $this->write($private);
        $live = $private->live();
        $private->persist($this->space('pending'));
        $private->connectionRolledBack(1);

        self::assertSame($live, $private->live(), 'pending inserts survive a rollback signal');
        $private->flush();
        $private->connectionRolledBack(1);
        self::assertNotSame($live, $private->live(), 'no pending work: replaced');
        $private->connectionRolledBack(null);
        self::assertFalse($private->isStale(), 'a fresh manager wrote nothing');
        $private->wrapInTransaction(static fn (): bool => true);
        $real->rollBack();
        $private->clear();
        (new WikiEntityManager($this->doctrine()))->clear();
    }

    #[Test]
    public function aClosedManagerIsReplacedAndLoadsAndRefreshesAreTracked(): void
    {
        $this->doctrine();
        $provider = self::getContainer()->get(WikiEntityManagerProvider::class);
        assert($provider instanceof WikiEntityManagerProvider);
        $private = $provider->get();
        self::assertInstanceOf(WikiEntityManager::class, $private);
        $live = $private->live();
        $live->close();
        self::assertNotSame($live, $private->live(), 'a closed manager is created again');

        $space = $this->space('loaded');
        $private->persist($space);
        $private->flush();
        $private->clear();
        $real = $private->getConnection();
        $real->beginTransaction();
        $loaded = $private->find(WikiSpace::class, $space->getId());
        self::assertInstanceOf(WikiSpace::class, $loaded);
        $private->refresh($loaded);
        $private->postLoad(new PostLoadEventArgs($loaded, $private->live()));
        $private->getConnection()->rollBack();
        self::assertTrue($private->isStale(), 'NJV-1: a load inside a rolled-back transaction makes the identity map stale');
    }

    #[Test]
    public function aSavepointRollbackThatFailsNeverHidesTheOriginalFailure(): void
    {
        $logger  = new RecordingLogger();
        $private = new WikiEntityManager($this->doctrine(), 'default', $logger);
        $real    = $private->getConnection();
        $real->beginTransaction();
        $private->beginTransaction();
        $this->write($private);
        // The server ended the transaction (MySQL deadlock): the wiki's savepoint is gone.
        $real->executeStatement('ROLLBACK');
        $private->rollback();
        self::assertTrue($private->isStale());
        self::assertCount(1, $this->warnings($logger), 'logged, never rethrown inside the application\'s transaction');
        $real->close();

        $quiet = new WikiEntityManager($this->doctrine(), 'default', new class extends AbstractLogger {
            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                throw new RuntimeException('logger down');
            }
        });
        $quietConnection = $quiet->getConnection();
        $quietConnection->beginTransaction();
        $quiet->beginTransaction();
        $this->write($quiet);
        $quietConnection->executeStatement('ROLLBACK');
        $quiet->rollback();
        self::assertTrue($quiet->isStale(), 'a failing logger never hides the original failure either');
        $quietConnection->close();

        $outer = new WikiEntityManager($this->doctrine(), 'default', $logger);
        $this->expectException(Throwable::class);
        $outer->rollback(); // no transaction: an outermost rollback that fails is rethrown
    }

    #[Test]
    public function aBoundaryThatCannotInspectOrClearStillDropsTheManager(): void
    {
        $logger  = new RecordingLogger();
        $private = new WikiEntityManager($this->doctrine(), 'default', $logger);
        $failing = $this->createMock(EntityManagerInterface::class);
        $failing->method('isOpen')->willReturn(true);
        $failing->method('getUnitOfWork')->willThrowException(new RuntimeException('change sets'));
        $failing->method('clear')->willThrowException(new RuntimeException('onClear listener'));
        (new ReflectionProperty(WikiEntityManager::class, 'current'))->setValue($private, $failing);

        $private->reset();

        self::assertFalse($private->isCreated());
        self::assertCount(2, $this->warnings($logger), 'inspection and clear failures are logged, never rethrown');

        $quiet = new WikiEntityManager($this->doctrine(), 'default', new class extends AbstractLogger {
            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                throw new RuntimeException('logger down');
            }
        });
        (new ReflectionProperty(WikiEntityManager::class, 'current'))->setValue($quiet, $failing);
        $quiet->reset();
        self::assertFalse($quiet->isCreated());

        $closed = new WikiEntityManager($this->doctrine());
        $closed->live()->close();
        $closed->reset();
        self::assertFalse($closed->isCreated());
    }

    #[Test]
    public function aBoundaryLogsTheDroppedChangesByClassOnly(): void
    {
        $logger  = new RecordingLogger();
        $private = new WikiEntityManager($this->doctrine(), 'default', $logger);
        $private->persist($this->space('dropped-secret-slug'));
        $private->reset();

        $records = $this->warnings($logger);
        self::assertCount(1, $records);
        self::assertSame([WikiSpace::class => 1], $records[0]['context']['changes']);
        self::assertStringNotContainsString('dropped-secret-slug', (string) json_encode($records));

        $silent = new WikiEntityManager($this->doctrine());
        $silent->persist($this->space('silent'));
        $silent->reset();
        self::assertFalse($silent->isCreated(), 'without a logger the changes are dropped silently');
        $nothing = new WikiEntityManager($this->doctrine(), 'default', $logger);
        $nothing->live();
        $nothing->reset();
        self::assertCount(1, $logger->records, 'nothing pending: nothing logged');
    }

    #[Test]
    public function theSourceMustBeAnOrmManagerThatMapsTheWikiEntities(): void
    {
        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->method('getManager')->willReturn($this->createMock(ObjectManager::class));
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('does not map');
        (new WikiEntityManager($doctrine, 'other'))->source();
    }

    #[Test]
    public function theSourceSqlFiltersAreMirroredWithTheirParameters(): void
    {
        $doctrine = $this->doctrine();
        $source   = $doctrine->getManager();
        assert($source instanceof EntityManagerInterface);
        $private = new WikiEntityManager($doctrine);
        $private->persist(new WikiSpace('one', 'One', WikiSpaceOwnerScope::Team, 'acme'));
        $private->persist(new WikiSpace('two', 'Two', WikiSpaceOwnerScope::Team, 'other'));
        $private->flush();
        $source->getConfiguration()->addFilter('test_space_owner', SpaceOwnerFilter::class);

        $source->getFilters()->enable('test_space_owner')->setParameter('owner', 'acme');
        self::assertSame(['one'], $this->slugs($private));
        $copy = $private->live()->getFilters()->getFilter('test_space_owner');
        self::assertSame($copy, $private->live()->getFilters()->getFilter('test_space_owner'), 'an unchanged filter is kept');
        $source->getFilters()->getFilter('test_space_owner')->setParameter('owner', 'other');
        self::assertSame(['two'], $this->slugs($private), 'a changed parameter is copied');
        $source->getFilters()->getFilter('test_space_owner')->setParameterList('owners', ['acme', 'other'], 'string');
        self::assertSame(['one', 'two'], $this->slugs($private), 'a list parameter keeps its type');
        $source->getFilters()->disable('test_space_owner');
        self::assertSame(['one', 'two'], $this->slugs($private), 'a filter the source disabled is disabled');
    }

    #[Test]
    public function repositoriesAreBoundToTheDecoratorUntilTheNextBoundary(): void
    {
        $private    = $this->privateManager();
        $repository = $private->getRepository(WikiSpace::class);
        self::assertSame($repository, $private->getRepository(WikiSpace::class));
        $private->connectionRolledBack(null);
        self::assertSame($repository, $private->getRepository(WikiSpace::class), 'kept while the manager lives');
        $private->reset();
        self::assertNotSame($repository, $private->getRepository(WikiSpace::class), 'emptied with the manager at a boundary');
        self::assertInstanceOf(EntityRepository::class, $private->getRepository(WikiPage::class));
        // Asked of the live manager itself (Doctrine internals, a validator): its own factory keeps one per class.
        self::assertSame($private->live()->getRepository(WikiSpace::class), $private->live()->getRepository(WikiSpace::class));
    }

    #[Test]
    public function aServiceRepositoryIsAskedOfTheSourceFactory(): void
    {
        $private                             = $this->privateManager();
        $metadata                            = $private->live()->getClassMetadata(TestUserEntity::class);
        $before                              = $metadata->customRepositoryClassName;
        $metadata->customRepositoryClassName = HostServiceRepository::class;
        try {
            $private->getRepository(TestUserEntity::class);
            self::fail('the container factory is asked for the service');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString(HostServiceRepository::class, $exception->getMessage());
        } finally {
            $metadata->customRepositoryClassName = $before;
        }
    }

    #[Test]
    public function undefinedMagicPropertiesFailAndBundleClassesAreKnown(): void
    {
        self::assertTrue(WikiEntityManager::isBundleEntity('Proxies\\__CG__\\' . WikiPage::class));
        self::assertFalse(WikiEntityManager::isBundleEntity(TestUserEntity::class));
        self::assertSame(WikiPage::class, WikiEntityManager::realClass('Proxies\\__CG__\\' . WikiPage::class));
        $this->expectException(LogicException::class);
        $this->privateManager()->__get('other');
    }

    #[Test]
    public function aWikiWriteInsideATransactionRunsInASavepointWithThePrivateManagerOnly(): void
    {
        $private    = $this->privateManager();
        $host       = $private->source();
        $connection = $private->getConnection();
        self::assertSame(1, WikiSavepoint::write($private, static fn (): int => 1), 'outside a transaction: as is');

        $connection->beginTransaction();
        try {
            WikiSavepoint::write($private, static function (): never {
                throw new RuntimeException('failing statement');
            });
            self::fail('the statement failure is rethrown');
        } catch (RuntimeException $exception) {
            self::assertSame('failing statement', $exception->getMessage());
        }
        self::assertTrue($connection->isTransactionActive(), 'the host transaction is untouched');
        self::assertSame(2, WikiSavepoint::write($private, static fn (): int => 2));
        self::assertSame(1, $connection->getTransactionNestingLevel());

        try {
            WikiSavepoint::write($private, static function () use ($connection): never {
                $connection->rollBack();
                $connection->beginTransaction();

                throw new RuntimeException('original failure');
            });
            self::fail('the statement failure is rethrown');
        } catch (RuntimeException $exception) {
            self::assertSame('original failure', $exception->getMessage());
        }

        $levels = [];
        WikiSavepoint::write($host, static function () use ($connection, &$levels): int {
            $levels[] = $connection->getTransactionNestingLevel();

            return 3;
        });
        self::assertSame([1], $levels);
        $connection->rollBack();
    }

    #[Test]
    public function theBuildPassWrapsOnlyTheSourceManagersConnection(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_wiki.database.entity_manager', 'main');
        $container->setDefinition(PrivateEntityManagerMiddleware::class, (new Definition(PrivateEntityManagerMiddleware::class))->addTag('doctrine.middleware'));
        $container->setDefinition('doctrine.orm.main_entity_manager', new Definition(EntityManagerInterface::class, [new Reference('doctrine.dbal.wiki_connection')]));
        (new PrivateEntityManagerPass())->process($container);
        self::assertSame([['connection' => 'wiki']], $container->getDefinition(PrivateEntityManagerMiddleware::class)->getTag('doctrine.middleware'));

        $container->removeDefinition('doctrine.orm.main_entity_manager');
        $container->setParameter('doctrine.default_connection', 'primary');
        (new PrivateEntityManagerPass())->process($container);
        self::assertSame([['connection' => 'primary']], $container->getDefinition(PrivateEntityManagerMiddleware::class)->getTag('doctrine.middleware'));

        $bare = new ContainerBuilder();
        $bare->setParameter('nowo_wiki.database.entity_manager', 'default');
        $bare->setDefinition(PrivateEntityManagerMiddleware::class, (new Definition(PrivateEntityManagerMiddleware::class))->addTag('doctrine.middleware'));
        (new PrivateEntityManagerPass())->process($bare);
        self::assertSame([['connection' => 'default']], $bare->getDefinition(PrivateEntityManagerMiddleware::class)->getTag('doctrine.middleware'));

        $off = new ContainerBuilder();
        (new PrivateEntityManagerPass())->process($off);
        self::assertFalse($off->hasDefinition(PrivateEntityManagerMiddleware::class), 'option off: no middleware to attach');
        self::assertTrue(PrivateEntityManagerPass::supportsRollbackMiddleware());
    }

    private function write(WikiEntityManager $private): void
    {
        $private->persist($this->space(bin2hex(random_bytes(4))));
        $private->flush();
    }

    private function space(string $slug): WikiSpace
    {
        return new WikiSpace($slug, 'Space ' . $slug, WikiSpaceOwnerScope::Team, 'team-1');
    }

    /**
     * @return list<string>
     */
    private function slugs(WikiEntityManager $private): array
    {
        $private->clear();
        $slugs = [];
        /** @var list<WikiSpace> $spaces */
        $spaces = $private->createQueryBuilder()->select('s')->from(WikiSpace::class, 's')->orderBy('s.slug')->getQuery()->getResult();
        foreach ($spaces as $space) {
            $slugs[] = $space->getSlug();
        }

        return $slugs;
    }

    /**
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    private function warnings(RecordingLogger $logger): array
    {
        return array_values(array_filter($logger->records, static fn (array $record): bool => $record['level'] === 'warning'));
    }

    private function doctrine(): ManagerRegistry
    {
        if (!self::$booted) {
            self::bootKernel();
        }
        $doctrine = self::getContainer()->get('doctrine');
        assert($doctrine instanceof ManagerRegistry);
        $em = $doctrine->getManager();
        assert($em instanceof EntityManagerInterface);
        $schema   = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);

        return $doctrine;
    }

    private function privateManager(): WikiEntityManager
    {
        $private = new WikiEntityManager($this->doctrine());
        $private->live();

        return $private;
    }
}
