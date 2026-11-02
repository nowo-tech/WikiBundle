<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\WikiBundle\Collaboration\DoctrineWikiCollabStateStore;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use PHPUnit\Framework\TestCase;

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
