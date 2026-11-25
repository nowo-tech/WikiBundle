<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Transaction;

use Doctrine\DBAL\Driver\Exception as DriverExceptionInterface;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use LogicException;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\WikiBundle\Doctrine\WikiEntityManager;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Transaction\WikiFlushObserver;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function restore_error_handler;
use function set_error_handler;

use const E_USER_DEPRECATED;

/**
 * 1.37 (Beacon spec 239): the wiki's transaction runner.
 */
final class WikiTransactionRunnerTest extends TestCase
{
    private EntityManager $em;

    protected function setUp(): void
    {
        $this->em = WikiTestDatabase::create();
    }

    public function testAUnitIsFlushedAndCommittedAtTheRunnersLevel(): void
    {
        $runner = new WikiTransactionRunner($this->em);
        $space  = $runner->run(function (): WikiSpace {
            $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
            $this->em->persist($space);

            return $space;
        });

        self::assertTrue($this->em->contains($space));
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM wiki_spaces'));
        self::assertSame(0, $this->em->getConnection()->getTransactionNestingLevel());
        self::assertSame($this->em, $runner->manager());
    }

    /** 2.0: a manager given by hand: the unit rolls back, and nothing of that manager is cleared or detached. */
    public function testAFailedUnitOnAHandGivenManagerRollsBackAndClearsNothing(): void
    {
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->em->persist($space);
        $this->em->flush();

        try {
            (new WikiTransactionRunner($this->em))->run(function () use ($space): never {
                $space->setName('Changed');
                $this->em->persist(new WikiSpace('new', 'New', WikiSpaceOwnerScope::Team, 'team-1'));

                throw new RuntimeException('refused');
            });
            self::fail('Expected the refusal.');
        } catch (RuntimeException $refused) {
            self::assertSame('refused', $refused->getMessage());
        }

        self::assertSame(0, (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM wiki_spaces WHERE slug = 'new'"));
        self::assertSame('Engineering', $this->em->getConnection()->fetchOne("SELECT name FROM wiki_spaces WHERE slug = 'eng'"));
        self::assertTrue($this->em->contains($space), 'the hand-given manager is never cleared');
        self::assertSame(0, $this->em->getConnection()->getTransactionNestingLevel());
    }

    /** 2.0: the 1.x boundary reset of a closed shared manager is gone: its arguments are ignored, reset() does nothing. */
    public function testTheDeprecatedRegistryArgumentsAndResetDoNothing(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('resetManager');
        $registry->expects(self::never())->method('getManager');
        $notices = [];
        set_error_handler(static function (int $level, string $message) use (&$notices): bool {
            $notices[] = $message;

            return true;
        }, E_USER_DEPRECATED);
        try {
            $runner = new WikiTransactionRunner($this->em, $registry, 'default', logger: new RecordingLogger());
            new WikiTransactionRunner($this->em);
        } finally {
            restore_error_handler();
        }
        self::assertCount(1, $notices);
        self::assertStringContainsString('are ignored since 2.0', $notices[0]);
        $runner->reset();
        self::assertSame($this->em, $runner->manager());
        self::assertSame($this, $runner->attach($this), 'a manager given by hand: rows are returned as they are');
    }

    public function testThePrivateManagerRunsOnTransactionDoctrinesOrmRunner(): void
    {
        $private = new WikiEntityManager($this->registryOf($this->em));
        $runner  = new WikiTransactionRunner(new WikiEntityManagerProvider($this->registryOf($this->em), 'default', $private));

        $runner->run(static function () use ($private): void {
            $private->persist(new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1'));
        });

        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM wiki_spaces'));
    }

    public function testAConnectionUnitRunsOnEitherManager(): void
    {
        $private = new WikiEntityManager($this->registryOf($this->em));
        $runner  = new WikiTransactionRunner(new WikiEntityManagerProvider($this->registryOf($this->em), 'default', $private));
        self::assertSame('private', $runner->runOnConnection(static fn (): string => 'private'));
        self::assertSame('shared', (new WikiTransactionRunner($this->em))->runOnConnection(static fn (): string => 'shared'));
        self::assertSame(0, $this->em->getConnection()->getTransactionNestingLevel());
    }

    public function testTheWikiEntityClassesAreTold(): void
    {
        self::assertTrue(WikiFlushObserver::isWikiEntity('Proxies\\__CG__\\Nowo\\WikiBundle\\Entity\\WikiPage'));
        self::assertTrue(WikiFlushObserver::isWikiEntity(WikiPage::class));
        self::assertFalse(WikiFlushObserver::isWikiEntity(TestUserEntity::class));
    }

    public function testRunRetryingReplaysAnOutermostTransientFailure(): void
    {
        $runner   = new WikiTransactionRunner($this->em, backoffBaseMilliseconds: 1);
        $attempts = 0;

        self::assertSame('done', $runner->runRetrying(static function () use (&$attempts): string {
            if (++$attempts < 3) {
                throw new DeadlockException(self::driverException(), null);
            }

            return 'done';
        }));
        self::assertSame(3, $attempts);

        $attempts = 0;
        try {
            (new WikiTransactionRunner($this->em, backoffBaseMilliseconds: 0))->runRetrying(static function () use (&$attempts): never {
                ++$attempts;

                throw new DeadlockException(self::driverException(), null);
            }, 2);
            self::fail('Expected the transient failure.');
        } catch (TransientStorageFailure $failure) {
            self::assertSame(1, $failure->retryAfterSeconds());
        }
        self::assertSame(2, $attempts);
    }

    public function testRunRetryingNeverReplaysInsideTheCallersTransaction(): void
    {
        $attempts = 0;
        $this->em->getConnection()->beginTransaction();
        try {
            (new WikiTransactionRunner($this->em))->runRetrying(static function () use (&$attempts): never {
                ++$attempts;

                throw new DeadlockException(self::driverException(), null);
            });
            self::fail('Expected the transient failure.');
        } catch (TransientStorageFailure) {
        } finally {
            $this->em->getConnection()->rollBack();
        }
        self::assertSame(1, $attempts);
    }

    public function testTheLockHelpersLockAndReadAgain(): void
    {
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->em->persist($space);
        $this->em->flush();
        $this->em->getConnection()->executeStatement("UPDATE wiki_spaces SET name = 'Renamed' WHERE id = ?", [$space->getId()]);
        $runner = new WikiTransactionRunner($this->em);

        $runner->run(static function () use ($runner, $space): void {
            $runner->lockSpace($space);
            self::assertTrue($runner->lockEntity($space));
            self::assertFalse($runner->lockRow(WikiSpace::class, 'missing'));
        });
        self::assertSame('Renamed', $space->getName());

        $gone = new WikiSpace('gone', 'Gone', WikiSpaceOwnerScope::Team, 'team-1');
        $this->em->persist($gone);
        $this->em->flush();
        $this->em->getConnection()->executeStatement('DELETE FROM wiki_spaces WHERE id = ?', [$gone->getId()]);
        self::assertFalse($runner->run(static fn (): bool => $runner->lockEntity($gone)));
    }

    public function testTheProviderMustResolveAnOrmManager(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($this->createMock(ObjectManager::class));

        $this->expectException(LogicException::class);
        (new WikiTransactionRunner(new WikiEntityManagerProvider($registry)))->run(static fn (): null => null);
    }

    private function registryOf(EntityManagerInterface $em): ManagerRegistry
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($em);
        $registry->method('getManagerForClass')->willReturn($em);
        $registry->method('getManagerNames')->willReturn(['default' => 'doctrine.orm.default_entity_manager']);

        return $registry;
    }

    private static function driverException(): DriverExceptionInterface
    {
        return new class('Deadlock found when trying to get lock', 1213) extends RuntimeException implements DriverExceptionInterface {
            public function getSQLState(): string
            {
                return '40001';
            }
        };
    }
}
