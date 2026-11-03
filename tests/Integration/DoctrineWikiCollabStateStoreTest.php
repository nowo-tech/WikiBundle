<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\WikiBundle\Collaboration\DoctrineWikiCollabStateStore;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function extension_loaded;

/**
 * 1.13: the Doctrine Yjs log store on a real schema.
 */
final class DoctrineWikiCollabStateStoreTest extends TestCase
{
    private DoctrineWikiCollabStateStore $store;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }

        $this->store = new DoctrineWikiCollabStateStore(SqliteWikiEntityManager::create());
    }

    public function testAnEmptyPageHasAnEmptyLog(): void
    {
        $state = $this->store->load('p1');

        self::assertTrue($state->isEmpty());
        self::assertSame(0, $state->seq);
        self::assertNull($state->baseRevisionId);
        self::assertNull($state->seededAt);
        self::assertNull($state->updatedAt);
        self::assertSame(0, $this->store->info('p1')->bytes);
    }

    public function testAppendsCompactsToOneSnapshotAndCountsWithoutReadingData(): void
    {
        $one   = $this->store->append('p1', 'AQ==');
        $two   = $this->store->append('p1', 'Ag==');
        $other = $this->store->append('p2', 'Aw==');
        self::assertGreaterThan($one, $two);

        $info = $this->store->info('p1');
        self::assertNull($info->snapshot);
        self::assertSame([], $info->updates);
        self::assertSame(2, $info->updateCount);
        self::assertSame(8, $info->bytes);
        self::assertSame($two, $info->seq);
        self::assertInstanceOf(DateTimeImmutable::class, $info->updatedAt);

        $state = $this->store->load('p1');
        self::assertSame(['AQ==', 'Ag=='], $state->updates);

        $this->store->compact('p1', 'U1==', $one);
        $three = $this->store->append('p1', 'Aw==');
        $state = $this->store->load('p1');
        self::assertSame('U1==', $state->snapshot);
        self::assertSame(['Ag==', 'Aw=='], $state->updates);
        self::assertSame($one, $state->snapshotSeq);
        self::assertGreaterThanOrEqual($three, $state->seq);

        $this->store->compact('p1', 'U2==', $three);
        $state = $this->store->load('p1');
        self::assertSame('U2==', $state->snapshot);
        self::assertSame([], $state->updates);
        self::assertSame(4, $state->bytes, 'CW-M1: only the newest snapshot is kept');
        self::assertFalse($state->isEmpty());
        self::assertSame(['Aw=='], $this->store->load('p2')->updates);
        self::assertSame($other, $this->store->info('p2')->seq);
    }

    public function testOnlyOneClientSeedsUntilTheClaimIsStaleOrTheLogIsReset(): void
    {
        $past = new DateTimeImmutable('-1 hour');
        self::assertTrue($this->store->claimSeed('p1', $past, 'ana'));
        self::assertFalse($this->store->claimSeed('p1', $past, 'bob'));
        self::assertSame('ana', $this->store->info('p1')->seededBy);
        self::assertTrue($this->store->claimSeed('p1', new DateTimeImmutable('+1 minute'), 'bob'));
        self::assertSame('bob', $this->store->info('p1')->seededBy);

        $this->store->append('p1', 'AQ==');
        $this->store->reset('p1', 'rev-2');
        $state = $this->store->load('p1');
        self::assertTrue($state->isEmpty());
        self::assertSame('rev-2', $state->baseRevisionId);
        self::assertNull($state->seededAt);
        self::assertNull($state->seededBy);
        self::assertTrue($this->store->claimSeed('p1', $past, 'ana'));

        $this->store->reset('p9', 'rev-1');
        self::assertSame('rev-1', $this->store->info('p9')->baseRevisionId);
    }

    public function testSavesMoveTheBaseAndCorruptResetsAreCapped(): void
    {
        $this->store->markSaved('p1', 'rev-7');
        self::assertSame('rev-7', $this->store->info('p1')->baseRevisionId);

        $window = new DateTimeImmutable('-10 minutes');
        self::assertTrue($this->store->noteReset('p1', $window, 2));
        self::assertTrue($this->store->noteReset('p1', $window, 2));
        self::assertFalse($this->store->noteReset('p1', $window, 2));
        self::assertTrue($this->store->noteReset('p1', new DateTimeImmutable('+1 minute'), 2), 'a new window');
    }

    public function testInterleavedCompactionsKeepTheWidestSnapshot(): void
    {
        $seqs = [];
        for ($i = 0; $i < 4; ++$i) {
            $seqs[] = $this->store->append('p1', 'AQ==');
        }

        // CW-L4: the compaction covering more lands first; the one covering less must not delete it.
        self::assertTrue($this->store->compact('p1', 'V2lkZQ==', $seqs[3]));
        self::assertFalse($this->store->compact('p1', 'TmFycm93', $seqs[1]));

        $state = $this->store->load('p1');
        self::assertSame('V2lkZQ==', $state->snapshot);
        self::assertSame($seqs[3], $state->snapshotSeq);
        self::assertFalse($state->isEmpty());
        self::assertSame([], $state->updates);
    }

    public function testStaleLogsArePurged(): void
    {
        $this->store->append('old', 'AQ==');
        $this->store->append('new', 'AQ==');

        self::assertSame(0, $this->store->purge(new DateTimeImmutable('-1 day')));
        self::assertSame(2, $this->store->purge(new DateTimeImmutable('+1 minute')));
        self::assertTrue($this->store->info('old')->isEmpty());
        self::assertNull($this->store->info('new')->updatedAt);
    }

    public function testThePurgeRollsBackOnFailure(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('beginTransaction');
        $em->method('createQueryBuilder')->willThrowException(new RuntimeException('db down'));
        $em->expects(self::once())->method('rollback');
        $em->expects(self::never())->method('commit');

        $this->expectException(RuntimeException::class);
        (new DoctrineWikiCollabStateStore($em))->purgePages(new DateTimeImmutable());
    }

    public function testWorksThroughTheEntityManagerProvider(): void
    {
        $em       = SqliteWikiEntityManager::create();
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($em);
        $store = new DoctrineWikiCollabStateStore(new WikiEntityManagerProvider($registry, 'default'));

        $store->append('p1', 'AQ==');
        self::assertSame(['AQ=='], $store->load('p1')->updates);
    }
}
