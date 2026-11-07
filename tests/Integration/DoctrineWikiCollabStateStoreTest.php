<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\DBAL\Driver\Exception as DriverExceptionInterface;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\RetryableException;
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
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Throwable;

use function array_filter;
use function array_map;
use function array_values;
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

    public function testAResetLocksTheDocumentFirstAndRollsBackOnFailure(): void
    {
        $logger = new RecordingLogger();
        $em     = SqliteWikiEntityManager::create($logger);
        $store  = new DoctrineWikiCollabStateStore($em);
        (new ReflectionMethod($store, 'ensureDocument'))->invoke($store, 'p1');
        $store->appendWithin('p1', 'AQ==', 10, 1000);
        $logger->records = [];

        $store->reset('p1', 'rev-1');

        // The locked read of the document comes before the delete of the updates (the order of appendWithin()).
        $sql = array_values(array_filter(array_map(static fn (array $r): string => (string) ($r['context']['sql'] ?? ''), $logger->records), static fn (string $q): bool => $q !== '' && preg_match('/SAVEPOINT|^INSERT OR IGNORE/', $q) !== 1));
        self::assertStringStartsWith('SELECT', $sql[0]);
        self::assertStringStartsWith('DELETE FROM wiki_page_collab_updates', $sql[1]);
        self::assertSame([0, 0, 'rev-1'], [$store->info('p1')->updateCount, $store->info('p1')->bytes, $store->info('p1')->baseRevisionId]);

        $em->getConnection()->executeStatement('DROP TABLE wiki_page_collab_updates');
        try {
            $store->reset('p1', null);
            self::fail('The reset did not fail.');
        } catch (Throwable) {
            self::assertFalse($em->getConnection()->isTransactionActive());
        }
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
        self::assertFalse($this->store->info('p1')->hasUnsavedUpdates(), 'A session save takes in what the log holds.');
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
    /** Audit HO-L1: the work is one transaction, with the reset inside it; a failure undoes everything. */
    public function testWhileLockedCommitsAndRollsBack(): void
    {
        $this->store->reset('p8', 'rev-1');
        $this->store->append('p8', 'dQ==');

        try {
            $this->store->whileLocked('p8', function (): void {
                $this->store->reset('p8', 'rev-2');

                throw new RuntimeException('save failed');
            });
            self::fail('Expected the exception.');
        } catch (RuntimeException $e) {
            self::assertSame('save failed', $e->getMessage());
        }
        self::assertFalse($this->store->info('p8')->isEmpty(), 'Rolled back: the log is still there.');

        self::assertSame('done', $this->store->whileLocked('p8', function (): string {
            $this->store->reset('p8', 'rev-3');

            return 'done';
        }));
        self::assertTrue($this->store->info('p8')->isEmpty());
    }

    /**
     * Audit HO-L4: a MySQL deadlock ends the whole transaction, so an inner rollback fails with 1305 and hides it. The
     * caller gets a deadlock, and the connection, which a worker reuses, is left with no open transaction. The server
     * side is forced here: a real ROLLBACK under an open savepoint.
     */
    public function testALostTransactionIsADeadlockAndLeavesTheConnectionClean(): void
    {
        $this->store->reset('p7', 'rev-1');
        $connection = $this->em->getConnection();
        $driver     = new class('SAVEPOINT DOCTRINE_2 does not exist', 1305) extends RuntimeException implements DriverExceptionInterface {
            public function getSQLState(): string
            {
                return '42000';
            }
        };

        try {
            $this->store->whileLocked('p7', static function () use ($connection, $driver): void {
                $connection->beginTransaction();
                $connection->executeStatement('ROLLBACK');

                throw new DriverException($driver, null);
            });
            self::fail('Expected the exception.');
        } catch (RetryableException $e) {
            self::assertInstanceOf(DeadlockException::class, $e);
        }

        self::assertFalse($connection->isTransactionActive());
        self::assertSame(0, $connection->getTransactionNestingLevel());
    }

    /**
     * Audit HO-L1 window 2: each update names the one before it, and a compaction takes no number of its own: a client
     * following along must not lose the thread when another client compacts.
     */
    public function testNumbersFormAChainThroughACompaction(): void
    {
        $this->store->reset('p6', 'rev-1');
        $first = $this->store->appendNumbered('p6', 'dQ==', 100, 100000);
        self::assertNull($first['refused']);
        self::assertSame(0, $first['p']);
        $second = $this->store->appendNumbered('p6', 'dg==', 100, 100000);
        self::assertSame($first['s'], $second['p']);
        self::assertGreaterThan($second['p'], $second['s']);

        self::assertTrue($this->store->compact('p6', 'c25hcA==', $first['s']));
        self::assertSame($second['s'], $this->store->info('p6')->seq, 'The snapshot row is not a number.');
        self::assertSame($second['s'], $this->store->load('p6')->seq, 'A new joiner starts from the same number.');
        $third = $this->store->appendNumbered('p6', 'dw==', 100, 100000);
        self::assertSame($second['s'], $third['p']);

        self::assertTrue($this->store->compact('p6', 'c25hcDI=', $third['s']));
        self::assertSame($third['s'], $this->store->info('p6')->seq);
        self::assertSame($third['s'], $this->store->appendNumbered('p6', 'eA==', 100, 100000)['p'], 'All updates compacted: the cover is the number.');
    }

    public function testARefusedAppendKeepsItsNumber(): void
    {
        $this->store->reset('p5', 'rev-1');
        $s = $this->store->appendNumbered('p5', 'dQ==', 1, 100000)['s'];

        self::assertSame(['refused' => 'log_full', 's' => $s, 'p' => $s], $this->store->appendNumbered('p5', 'dg==', 1, 100000));
        self::assertSame(['refused' => 'log_reset', 's' => $s, 'p' => $s], $this->store->appendNumbered('p5', 'dg==', 100, 1));
    }

    public function testAnAppendToAPurgedLogIsRefused(): void
    {
        $this->expectException(WikiCollabException::class);

        $this->store->appendNumbered('never-opened', 'dQ==', 100, 100000);
    }

    /** Audit HO-L1 window 2: `max(savedSeq, min(seq, lastSeq))`. */
    public function testMarkSavedUpToCountsWhatTheTextIncludes(): void
    {
        $this->store->reset('p4', 'rev-1');
        $first  = $this->store->appendNumbered('p4', 'dQ==', 100, 100000)['s'];
        $second = $this->store->appendNumbered('p4', 'dg==', 100, 100000)['s'];

        $this->store->markSavedUpTo('p4', 'rev-2', $first);
        self::assertSame($first, $this->store->info('p4')->savedSeq);
        self::assertSame('rev-2', $this->store->info('p4')->baseRevisionId);
        self::assertTrue($this->store->info('p4')->hasUnsavedUpdates(), 'The second update is in no revision.');

        $this->store->markSavedUpTo('p4', 'rev-3', 0);
        self::assertSame($first, $this->store->info('p4')->savedSeq, 'A slower save never lowers it.');
        self::assertSame('rev-3', $this->store->info('p4')->baseRevisionId);

        $this->store->markSavedUpTo('p4', 'rev-4', $second + 1000);
        self::assertSame($second, $this->store->info('p4')->savedSeq, 'Never past the end of the log.');
        self::assertFalse($this->store->info('p4')->hasUnsavedUpdates());

        $this->store->moveBase('p4', 'rev-5');
        self::assertSame('rev-5', $this->store->info('p4')->baseRevisionId);
        self::assertSame($second, $this->store->info('p4')->savedSeq, 'moveBase() leaves what is saved.');

        $this->store->markSavedUpTo('never-opened', 'rev-1', 5);
        self::assertSame(0, $this->store->info('never-opened')->savedSeq);
    }

    /** Audit HV-M1: named once, stable until a reset, new after every reset. */
    public function testALogKeepsItsNameUntilAReset(): void
    {
        self::assertNull($this->store->currentEpoch('e1'));
        $name = $this->store->epoch('e1');
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $name);
        self::assertSame($name, $this->store->epoch('e1'));
        self::assertSame($name, $this->store->currentEpoch('e1'));

        $this->store->append('e1', 'dQ==');
        $this->store->markSaved('e1', 'rev-2');
        self::assertTrue($this->store->compact('e1', 'c25hcA==', $this->store->info('e1')->seq));
        self::assertSame($name, $this->store->currentEpoch('e1'), 'Only a reset changes it.');

        $this->store->reset('e1', 'rev-3');
        self::assertNotSame($name, $this->store->currentEpoch('e1'));
        self::assertNotNull($this->store->currentEpoch('e1'));
        $document = $this->em->find(WikiCollabDocument::class, 'e1');
        self::assertInstanceOf(WikiCollabDocument::class, $document);
        $this->em->refresh($document);
        self::assertSame($this->store->currentEpoch('e1'), $document->getLogEpoch());
    }

    public function testAnAppendComparesTheNameUnderItsLock(): void
    {
        $this->store->reset('e2', 'rev-1');
        $name = (string) $this->store->currentEpoch('e2');

        $first = $this->store->appendInEpoch('e2', 'dQ==', 100, 100000, $name);
        self::assertNull($first['refused']);
        self::assertSame($name, $first['e']);

        $refused = $this->store->appendInEpoch('e2', 'dg==', 100, 100000, 'old');
        self::assertSame('log_epoch', $refused['refused']);
        self::assertSame(1, $this->store->info('e2')->updateCount);

        self::assertNull($this->store->appendInEpoch('e2', 'dw==', 100, 100000, null)['refused'], 'A client that names none is not compared.');
    }

    /** A log row deleted (purge) and recreated: the old name is no good, and the new row gets its own. */
    public function testADeletedAndRecreatedRowIsAnotherLog(): void
    {
        $this->store->reset('e3', 'rev-1');
        $name = (string) $this->store->currentEpoch('e3');
        $this->em->createQueryBuilder()->delete(WikiCollabDocument::class, 'd')->where('d.pageId = :p')->setParameter('p', 'e3')->getQuery()->execute();

        try {
            $this->store->appendInEpoch('e3', 'dQ==', 100, 100000, $name);
            self::fail('Expected log_epoch.');
        } catch (WikiCollabException $e) {
            self::assertSame('log_epoch', $e->reason);
        }

        self::assertNotSame($name, $this->store->epoch('e3'));
        self::assertSame('log_epoch', $this->store->appendInEpoch('e3', 'dQ==', 100, 100000, $name)['refused']);
    }

    /** Audit HV-I4: two saves can record in the reverse order of their commits: the base is what is current. */
    public function testTheBaseIsTheCurrentRevisionReadUnderTheLock(): void
    {
        $user  = new TestUserEntity('e4-user');
        $space = new WikiSpace('e4', 'E4', WikiSpaceOwnerScope::Team, 't');
        $page  = new WikiPage($space, 'p', 'P');
        $older = new WikiPageRevision($page, 1, '<p>1</p>', $user, '1');
        $newer = new WikiPageRevision($page, 2, '<p>2</p>', $user, '2');
        foreach ([$user, $space, $page, $older, $newer] as $entity) {
            $this->em->persist($entity);
        }
        $page->setCurrentRevision($newer);
        $this->em->flush();
        $this->store->reset($page->getId(), $older->getId());

        $this->store->markSavedUpTo($page->getId(), $older->getId(), 0);
        self::assertSame($newer->getId(), $this->store->info($page->getId())->baseRevisionId);

        $this->store->reset($page->getId(), $older->getId());
        $this->store->moveBase($page->getId(), $older->getId());
        self::assertSame($newer->getId(), $this->store->info($page->getId())->baseRevisionId);
    }

    /** Audit HF-M1: what no revision holds yet, through every step of a log's life. */
    public function testUnsavedUpdatesFollowSavesCompactionAndReset(): void
    {
        $this->store->reset('p9', 'rev-1');
        self::assertFalse($this->store->info('p9')->hasUnsavedUpdates(), 'An empty log.');

        $seq = $this->store->append('p9', 'dQ==');
        self::assertTrue($this->store->info('p9')->hasUnsavedUpdates());

        $this->store->markSaved('p9', 'rev-2');
        self::assertFalse($this->store->info('p9')->hasUnsavedUpdates());

        $this->store->compact('p9', 'c25hcA==', $seq);
        self::assertFalse($this->store->info('p9')->hasUnsavedUpdates(), 'A compaction adds no text.');
        self::assertSame($this->store->info('p9')->seq, $this->store->load('p9')->savedSeq, 'load() carries it too.');

        $this->store->append('p9', 'dg==');
        self::assertTrue($this->store->info('p9')->hasUnsavedUpdates(), 'Typed after the save.');

        $this->store->reset('p9', 'rev-3');
        self::assertFalse($this->store->info('p9')->hasUnsavedUpdates());
        self::assertSame(0, $this->store->info('p9')->savedSeq);
    }

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
