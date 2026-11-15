<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Transaction;

use Doctrine\DBAL\Driver\Exception as DriverExceptionInterface;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use LogicException;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\WikiBundle\Doctrine\WikiEntityManager;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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

    /** Audit QD-M1: after a refusal only the wiki rows the unit touched are forgotten, never the application's. */
    public function testAFailedUnitForgetsOnlyTheWikiRowsItTouched(): void
    {
        $host  = new TestUserEntity('host');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $kept  = new WikiSpace('ops', 'Operations', WikiSpaceOwnerScope::Team, 'team-1');
        $page  = new WikiPage($space, 'p', 'P');
        $rev   = new WikiPageRevision($page, 1, '<p>x</p>', $host);
        foreach ([$host, $space, $kept, $page, $rev] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        $pending = new TestUserEntity('pending');
        $this->em->persist($pending);

        try {
            (new WikiTransactionRunner($this->em))->run(function () use ($page): void {
                $page->setTitle('Changed');
                $this->em->persist(new WikiSpace('new', 'New', WikiSpaceOwnerScope::Team, 'team-1'));

                throw new RuntimeException('refused');
            });
            self::fail('Expected the refusal.');
        } catch (RuntimeException $refused) {
            self::assertSame('refused', $refused->getMessage());
        }

        self::assertFalse($this->em->contains($page), 'The changed page is forgotten.');
        self::assertFalse($this->em->contains($rev), 'A kept row that points at a forgotten one goes too.');
        self::assertTrue($this->em->contains($kept), 'An untouched wiki row stays.');
        self::assertTrue($this->em->contains($host));
        self::assertTrue($this->em->contains($pending), 'The application\'s pending entity stays pending.');
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM wiki_spaces WHERE slug = 'new'"));
    }

    public function testAClosedSharedManagerStaysClosedAndIsReplacedAtTheBoundary(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->with('default')->willReturn($this->em);
        $registry->expects(self::once())->method('resetManager')->with('default');
        $logger = new RecordingLogger();
        $runner = new WikiTransactionRunner(new WikiEntityManagerProvider($registry), $registry, 'default', logger: $logger);

        try {
            $runner->run(function (): void {
                $this->em->persist(new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1'));
                $this->em->persist(new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1'));
            });
            self::fail('Expected the unique violation.');
        } catch (UniqueConstraintViolationException) {
        }
        self::assertFalse($this->em->isOpen());

        $runner->reset();
        self::assertNotSame([], $logger->records);
    }

    public function testTheBoundaryLeavesAnOpenOrPrivateManagerAlone(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('resetManager');
        $registry->method('getManager')->willReturn($this->em);
        (new WikiTransactionRunner($this->em))->reset();
        (new WikiTransactionRunner($this->em, $registry))->reset();
        (new WikiTransactionRunner(new WikiEntityManagerProvider($registry), $registry))->reset();

        $closed = $this->createMock(EntityManagerInterface::class);
        $closed->method('isOpen')->willReturn(false);
        $private = $this->createMock(ManagerRegistry::class);
        $private->method('getManager')->willReturn($closed);
        $private->expects(self::never())->method('resetManager');
        (new WikiTransactionRunner(new WikiEntityManagerProvider($private, 'default', new WikiEntityManager($this->registryOf($this->em))), $private))->reset();
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
            (new WikiTransactionRunner($this->em, backoffBaseMilliseconds: 0))->runRetrying(static function () use (&$attempts): void {
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
            (new WikiTransactionRunner($this->em))->runRetrying(static function () use (&$attempts): void {
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
