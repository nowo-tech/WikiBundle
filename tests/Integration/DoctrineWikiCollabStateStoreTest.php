<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\WikiBundle\Collaboration\DoctrineWikiCollabStateStore;
use Nowo\WikiBundle\Collaboration\WikiCollabException;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiCollabDocument;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

use function extension_loaded;

/**
 * 1.13: the Doctrine Yjs log store on a real schema.
 */
final class DoctrineWikiCollabStateStoreTest extends TestCase
{
    private DoctrineWikiCollabStateStore $store;

    private EntityManager $em;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }

        $this->em    = SqliteWikiEntityManager::create();
        $this->store = new DoctrineWikiCollabStateStore($this->em);
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
        $one   = $this->append('p1', 'AQ==');
        $two   = $this->append('p1', 'Ag==');
        $other = $this->append('p2', 'Aw==');
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
        $three = $this->append('p1', 'Aw==');
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

    public function testTheCountersFollowAppendsCompactionsResetsAndCapsWithoutReadingTheLog(): void
    {
        (new ReflectionMethod($this->store, 'ensureDocument'))->invoke($this->store, 'p1');
        self::assertNull($this->store->appendWithin('p1', 'AQ==', 2, 100));
        self::assertNull($this->store->appendWithin('p1', 'Ag==', 2, 100));
        self::assertSame('log_full', $this->store->appendWithin('p1', 'Aw==', 2, 100));
        self::assertSame('log_reset', $this->store->appendWithin('p1', 'Aw==', 5, 9));
        $info = $this->store->info('p1');
        self::assertSame([2, 8], [$info->updateCount, $info->bytes]);

        // A compaction recounts: the snapshot and what it does not cover.
        $last = $info->seq;
        $this->store->compact('p1', 'U1U1', $last - 1);
        $info = $this->store->info('p1');
        self::assertSame([1, 8, $last - 1], [$info->updateCount, $info->bytes, $info->snapshotSeq]);

        // A reset (log_reset) empties the counters; a purge removes the document.
        $this->store->reset('p1', null);
        $info = $this->store->info('p1');
        self::assertSame([0, 0, 0, 0], [$info->updateCount, $info->bytes, $info->seq, $info->snapshotSeq]);
        $this->store->purgePages(new DateTimeImmutable('+1 minute'));
        self::assertSame(0, $this->store->info('p1')->bytes);
        self::assertNull($this->store->info('p1')->updatedAt);

        $this->expectException(WikiCollabException::class);
        $this->store->appendWithin('p1', 'AQ==', 5, 100);
    }

    public function testAnUpdateRunsThreeStatementsInsideItsTransaction(): void
    {
        $logger = new RecordingLogger();
        $em     = SqliteWikiEntityManager::create($logger);
        $store  = new DoctrineWikiCollabStateStore($em);
        (new ReflectionMethod($store, 'ensureDocument'))->invoke($store, 'p1');
        $logger->records = [];

        $store->appendWithin('p1', 'AQ==', 10, 1000);

        // Savepoints of the ORM flush inside the transaction are not statements on the data.
        $statements = array_values(array_filter($logger->records, static fn (array $r): bool => str_starts_with($r['message'], 'Executing')
            && preg_match('/^(RELEASE )?SAVEPOINT/', (string) ($r['context']['sql'] ?? '')) !== 1));
        // The locked counters, the insert, the counter update: no SUM over the log.
        self::assertCount(3, $statements);
        foreach ($statements as $statement) {
            self::assertStringNotContainsString('SUM(', (string) ($statement['context']['sql'] ?? ''));
        }
    }

    public function testOnlyOneClientSeedsUntilTheClaimIsStaleOrTheLogIsReset(): void
    {
        $past = new DateTimeImmutable('-1 hour');
        self::assertTrue($this->store->claimSeed('p1', $past, 'ana'));
        self::assertFalse($this->store->claimSeed('p1', $past, 'bob'));
        self::assertSame('ana', $this->store->info('p1')->seededBy);
        self::assertTrue($this->store->claimSeed('p1', new DateTimeImmutable('+1 minute'), 'bob'));
        self::assertSame('bob', $this->store->info('p1')->seededBy);

        $this->append('p1', 'AQ==');
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
            $seqs[] = $this->append('p1', 'AQ==');
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
        $this->append('old', 'AQ==');
        $this->append('new', 'AQ==');

        self::assertSame(0, $this->store->purge(new DateTimeImmutable('-1 day')));
        self::assertSame(2, $this->store->purge(new DateTimeImmutable('+1 minute')));
        self::assertTrue($this->store->info('old')->isEmpty());
        self::assertNull($this->store->info('new')->updatedAt);
    }

    public function testThePurgeAlsoDropsUpdatesLeftWithoutADocument(): void
    {
        $this->append('orphan', 'AQ==');
        $this->append('kept', 'AQ==');
        $this->em->createQueryBuilder()->delete(WikiCollabDocument::class, 'd')->where('d.pageId = :p')->setParameter('p', 'orphan')->getQuery()->execute();

        self::assertSame([], $this->store->purgePages(new DateTimeImmutable('-1 day')));
        self::assertSame(0, $this->store->info('orphan')->updateCount, 'DA-L1: the orphan update is gone');
        self::assertSame(1, $this->store->info('kept')->updateCount);
    }

    public function testAnAppendToAPurgedLogIsALogReset(): void
    {
        $this->append('kept', 'AQ==');
        $this->store->purgePages(new DateTimeImmutable('+1 minute'));
        try {
            $this->store->append('kept', 'Ag==');
            self::fail('DA-L1: a purged log is never replayed');
        } catch (WikiCollabException $exception) {
            self::assertSame([409, 'log_reset'], [$exception->status, $exception->reason]);
        }
        self::assertSame(0, $this->store->info('kept')->updateCount, 'nothing stored, the transaction rolled back');
        self::assertFalse($this->em->getConnection()->isTransactionActive());
    }

    public function testACompactionOfAPurgedLogIsALogReset(): void
    {
        $this->append('kept', 'AQ==');
        $this->store->purgePages(new DateTimeImmutable('+1 minute'));
        try {
            $this->store->compact('kept', 'AQ==', 1);
            self::fail('DA-I9: a purged log is not compacted (nor revived)');
        } catch (WikiCollabException $exception) {
            self::assertSame([409, 'log_reset'], [$exception->status, $exception->reason]);
        }
        self::assertTrue($this->store->info('kept')->isEmpty());
        self::assertFalse($this->em->getConnection()->isTransactionActive());
    }

    public function testDocumentsAreInsertedRaceSafelyInsideATransaction(): void
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        $this->store->reset('p9', null);
        $this->store->reset('p9', 'r1'); // the second insert is ignored: the transaction is not aborted
        $connection->commit();
        self::assertSame('r1', $this->store->info('p9')->baseRevisionId);
    }

    public function testInsertIgnoreStatementsPerPlatform(): void
    {
        self::assertSame('INSERT INTO t (page_id, updated_at) VALUES (?, ?) ON CONFLICT (page_id) DO NOTHING', DoctrineWikiCollabStateStore::insertIgnoreSql(new PostgreSQLPlatform(), 't'));
        self::assertSame('INSERT IGNORE INTO t (page_id, updated_at) VALUES (?, ?)', DoctrineWikiCollabStateStore::insertIgnoreSql(new MySQLPlatform(), 't'));
        self::assertSame('INSERT IGNORE INTO t (page_id, updated_at) VALUES (?, ?)', DoctrineWikiCollabStateStore::insertIgnoreSql(new MariaDBPlatform(), 't'));
        self::assertSame('INSERT OR IGNORE INTO t (page_id, updated_at) VALUES (?, ?)', DoctrineWikiCollabStateStore::insertIgnoreSql(new SQLitePlatform(), 't'));
        self::assertNull(DoctrineWikiCollabStateStore::insertIgnoreSql(new OraclePlatform(), 't'));
    }

    /** Appends like the service does after the token request created the page's document. */
    private function append(string $pageId, string $update): int
    {
        (new ReflectionMethod($this->store, 'ensureDocument'))->invoke($this->store, $pageId);

        return $this->store->append($pageId, $update);
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

        $store->reset('p1', null);
        $store->append('p1', 'AQ==');
        self::assertSame(['AQ=='], $store->load('p1')->updates);
    }
}
