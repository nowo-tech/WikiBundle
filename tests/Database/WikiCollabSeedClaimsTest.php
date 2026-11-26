<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Nowo\WikiBundle\Collaboration\DoctrineWikiCollabStateStore;
use Nowo\WikiBundle\Collaboration\WikiCollabSeedClaimant;
use Nowo\WikiBundle\Collaboration\WikiCollabSeedClaims;
use Nowo\WikiBundle\Entity\WikiCollabDocument;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use PHPUnit\Framework\TestCase;
use stdClass;

use function array_column;
use function memory_get_usage;
use function sort;
use function sprintf;

use const DATE_ATOM;

/**
 * 2.0.1 (Beacon 207 T030, DATA-WIKI-COLLAB-SEEDEDBY): the seed claims of a person in the privacy export and erasure,
 * and the one-shot clean-up of the raw identifiers 2.0.0 stored, on SQLite and — through WIKI_TEST_DATABASE_URL —
 * MySQL 8.4, MariaDB 11.4 and PostgreSQL 16: hashed and raw claims of the person, look-alike identifiers (case, LIKE
 * wildcards, a longer identifier) left alone, a claim taken by someone else between the read and the write left alone,
 * keyset batches, and a long run in one instance (worker mode).
 */
final class WikiCollabSeedClaimsTest extends TestCase
{
    private const SECRET = 'kernel-secret-for-tests';

    private EntityManager $em;

    private DoctrineWikiCollabStateStore $store;

    private WikiCollabSeedClaims $claims;

    protected function setUp(): void
    {
        $this->em     = WikiTestDatabase::create();
        $this->store  = new DoctrineWikiCollabStateStore($this->em);
        $this->claims = new WikiCollabSeedClaims(new WikiTransactionRunner($this->em, backoffBaseMilliseconds: 0), self::SECRET);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
    }

    public function testAPersonsHashedAndRawClaimsAreExportedAndErased(): void
    {
        $ana = WikiCollabSeedClaimant::user('ana@corp.example', self::SECRET);
        $this->claim('p1', $ana . '#11');
        $this->claim('p2', $ana);
        $this->claim('p3', 'ana@corp.example#12');       // 2.0.0 raw claim, not cleaned up yet
        $this->claim('p4', 'ANA@corp.example');          // look-alike (case)
        $this->claim('p5', 'ana@corp.example.org');      // longer identifier
        $this->claim('p6', WikiCollabSeedClaimant::user('bob@corp.example', self::SECRET) . '#11');
        $this->claim('p7', 'ana@corp.example#x');        // not a tab
        $this->em->getConnection()->insert($this->table(), ['page_id' => 'p8', 'updated_at' => '2026-01-01 00:00:00']);

        $export = $this->claims->exportUser(new TestUser('u-ana', 'ana@corp.example'));
        self::assertSame(['p1', 'p2', 'p3'], array_column($export, 'page_id'));
        self::assertSame(['you', 'you', 'you'], array_column($export, 'seeded_by'), 'never the stored hash');
        self::assertNotNull($export[0]['seeded_at']);
        self::assertNotNull(DateTimeImmutable::createFromFormat(DATE_ATOM, (string) $export[0]['seeded_at']));

        self::assertSame(3, $this->claims->eraseUser(new TestUser('u-ana', 'ana@corp.example')));
        self::assertSame([], $this->claims->exportUser(new TestUser('u-ana', 'ana@corp.example')));
        self::assertSame(['p4', 'p5', 'p6', 'p7'], $this->claimed(), 'look-alikes and other people untouched');
        self::assertNull($this->store->info('p1')->seededAt, 'the next editor claims again');
        self::assertSame(0, $this->claims->eraseUser(new TestUser('u-ana', 'ana@corp.example')), 'idempotent');
    }

    public function testLikeWildcardsInAnIdentifierMatchNothingElse(): void
    {
        $this->claim('p1', 'a_b%c#1');
        $this->claim('p2', 'axbyc#1');
        $this->claim('p3', 'a!b#1');
        $this->claim('p4', 'a!!b#1');

        self::assertSame(1, $this->claims->eraseUser(new TestUser('u', 'a_b%c')));
        self::assertSame(1, $this->claims->eraseUser(new TestUser('u', 'a!b')));
        self::assertSame(['p2', 'p4'], $this->claimed());
    }

    public function testUsersWithoutAnIdentifierHaveNoClaims(): void
    {
        $this->claim('p1', 'x#1');

        self::assertSame([], $this->claims->exportUser(new stdClass()));
        self::assertSame(0, $this->claims->eraseUser(new stdClass()));
        self::assertSame(0, $this->claims->eraseUser(new TestUser('u', '')));
        self::assertSame(['p1'], $this->claimed());
    }

    public function testWithoutASecretOnlyRawClaimsAreFound(): void
    {
        $claims = new WikiCollabSeedClaims(new WikiTransactionRunner($this->em, backoffBaseMilliseconds: 0));
        $this->claim('p1', 'ana#1');
        $this->claim('p2', WikiCollabSeedClaimant::user('ana', self::SECRET));

        self::assertSame(['p1'], array_column($claims->exportUser(new TestUser('u', 'ana')), 'page_id'));
        self::assertSame(1, $claims->eraseUser(new TestUser('u', 'ana')));
        self::assertSame(['p2'], $this->claimed());
    }

    public function testTheCleanUpClearsTheRawClaimsInBatches(): void
    {
        $hashed = WikiCollabSeedClaimant::user('ana', self::SECRET);
        for ($i = 0; $i < 7; ++$i) {
            $this->claim(sprintf('p%02d', $i), $i % 2 === 0 ? sprintf('user%d@corp.example#%d', $i, $i) : $hashed . '#' . $i);
        }
        $this->claim('p99', 'h1:bob');

        self::assertSame(['found' => 5, 'cleared' => 0], $this->claims->clearLegacy(dryRun: true, batchSize: 2));
        self::assertCount(8, $this->claimed(), 'a dry run changes nothing');
        self::assertSame(['found' => 5, 'cleared' => 5], $this->claims->clearLegacy(batchSize: 2));
        self::assertSame(['p01', 'p03', 'p05'], $this->claimed());
        self::assertSame(['found' => 0, 'cleared' => 0], $this->claims->clearLegacy(), 'safe to run again');
        self::assertSame(['found' => 3, 'cleared' => 3], $this->claims->clearLegacy(all: true));
        self::assertSame([], $this->claimed());
    }

    public function testTheBatchSizeIsBounded(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->claims->clearLegacy(batchSize: 0);
    }

    public function testATableThatDoesNotExistHasNoClaims(): void
    {
        $this->em->getConnection()->createSchemaManager()->dropTable($this->table());

        self::assertSame([], $this->claims->exportUser(new TestUser('u', 'ana')));
        self::assertSame(0, $this->claims->eraseUser(new TestUser('u', 'ana')));
        self::assertSame(['found' => 0, 'cleared' => 0], $this->claims->clearLegacy());
    }

    public function testAClaimTakenBySomeoneElseMeanwhileIsLeftAlone(): void
    {
        $this->claim('p1', 'ana#1');
        $rows = [['page_id' => 'p1', 'seeded_by' => 'ana#1', 'seeded_at' => null]];
        // Between the read and the write the claim went stale and Bob took it.
        $this->em->getConnection()->update($this->table(), ['seeded_by' => 'bob#2'], ['page_id' => 'p1']);

        self::assertSame(0, (fn (array $rows): int => $this->clear($rows))->call($this->claims, $rows));
        self::assertSame('bob#2', $this->store->info('p1')->seededBy);
        self::assertSame(0, (fn (array $rows): int => $this->clear($rows))->call($this->claims, []));
    }

    /** REQ-RUNTIME-002: one instance serves many people in turn; each call sees only that person's rows, memory stays flat. */
    public function testOneInstanceServesManyPeopleWithoutLeaking(): void
    {
        $this->claim('pa', WikiCollabSeedClaimant::user('ana', self::SECRET) . '#1');
        $this->claim('pb', WikiCollabSeedClaimant::user('bob', self::SECRET) . '#2');
        $ana = new TestUser('u-ana', 'ana');
        $bob = new TestUser('u-bob', 'bob');
        $this->claims->exportUser($ana);
        $start = memory_get_usage();
        for ($i = 0; $i < 300; ++$i) {
            self::assertSame(['pa'], array_column($this->claims->exportUser($ana), 'page_id'));
            self::assertSame(['pb'], array_column($this->claims->exportUser($bob), 'page_id'));
        }
        self::assertLessThan(2 * 1024 * 1024, memory_get_usage() - $start);
    }

    private function claim(string $pageId, string $claimant): void
    {
        self::assertTrue($this->store->claimSeed($pageId, new DateTimeImmutable('+1 minute'), $claimant));
    }

    /** @return list<string> the pages with a claim */
    private function claimed(): array
    {
        /** @var list<string> $pages */
        $pages = $this->em->getConnection()->fetchFirstColumn('SELECT page_id FROM ' . $this->table() . ' WHERE seeded_by IS NOT NULL');
        sort($pages);

        return $pages;
    }

    private function table(): string
    {
        return $this->em->getClassMetadata(WikiCollabDocument::class)->getTableName();
    }
}
