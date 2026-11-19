<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\ConflictCode;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\WriteUnit\CommitLedger;
use Nowo\TransactionDoctrine\WriteUnit\CommitMark;
use Nowo\WikiBundle\Backlink\WikiPageReferenceIndex;
use Nowo\WikiBundle\Backlink\WikiPageReferenceListener;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Markdown\WikiMarkdownReferenceExtractor;
use Nowo\WikiBundle\QuickAccess\WikiFavouriteResult;
use Nowo\WikiBundle\QuickAccess\WikiQuickAccessStore;
use Nowo\WikiBundle\Service\WikiPageSavedEvent;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Transaction\WikiConflictAnswer;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function fclose;
use function fgets;
use function is_resource;
use function proc_close;
use function proc_open;
use function sprintf;
use function str_starts_with;
use function stream_get_contents;
use function trim;

use const PHP_BINARY;

/**
 * 1.45, REQ-RUNTIME-003: real two-connection races on MySQL 8.4, MariaDB 11.4 and PostgreSQL 16 (skipped on SQLite):
 *
 * - a lock conflict on the favourite write answers 409 + `X-Conflict-Code: CONFLICT_001` + `Retry-After`, and
 *   `CONFLICT_002` without `Retry-After` when the request had already committed something;
 * - the "only one row" race of the unique key (another connection commits the same favourite or first visit while
 *   this one waits on it) is answered as done: one row, no conflict;
 * - the backlink index write waits on the page row lock; a timeout is logged and the index is left for the next save.
 *
 * The test sessions set `innodb_lock_wait_timeout` / `lock_timeout`, so a hang fails fast.
 */
final class WikiQuickAccessRaceTest extends TestCase
{
    private EntityManager $em;

    private WikiTransactionRunner $runner;

    private WikiPage $page;

    protected function setUp(): void
    {
        if (!WikiTestDatabase::isServer()) {
            self::markTestSkipped('Needs MySQL, MariaDB or PostgreSQL in ' . WikiTestDatabase::ENV . '.');
        }
        $this->em     = WikiTestDatabase::create();
        $this->runner = new WikiTransactionRunner($this->em, backoffBaseMilliseconds: 0);
        $space        = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $author       = new TestUserEntity('u-1');
        $this->page   = new WikiPage($space, 'target', 'Target');
        foreach ([$space, $author, $this->page] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        if (isset($this->em)) {
            $this->em->getConnection()->close();
        }
    }

    public function testALockConflictOnAFavouriteAnswersConflict001Or002(): void
    {
        $other = $this->other();
        $other->beginTransaction();
        $other->insert('wiki_page_favourites', ['id' => '00000000-0000-4000-8000-0000000000f1', 'page_id' => $this->page->getId(), 'user_id' => 'u-1', 'created_at' => '2026-11-19 09:00:00']);
        $this->shortLockTimeout($this->em->getConnection());

        try {
            (new WikiQuickAccessStore($this->runner))->addFavourite($this->page, 'u-1');
            self::fail('Expected the lock conflict.');
        } catch (TransientStorageFailure $failure) {
        } finally {
            $other->rollBack();
            $other->close();
        }

        $answer = (new WikiConflictAnswer(new ConflictResponsePolicy()))->answer(Request::create('/favourite', 'POST'), $failure);
        self::assertInstanceOf(Response::class, $answer);
        self::assertSame(409, $answer->getStatusCode());
        self::assertSame(ConflictCode::RETRY_SAFE, $answer->headers->get('X-Conflict-Code'));
        self::assertNotNull($answer->headers->get('Retry-After'));

        // An earlier unit of the same request committed: CONFLICT_002, no Retry-After.
        $ledger  = new CommitLedger();
        $request = Request::create('/favourite', 'POST');
        $request->attributes->set(CommitMark::REQUEST_ATTRIBUTE, new CommitMark($ledger));
        $ledger->record();
        $answer = (new WikiConflictAnswer())->answer($request, $failure);
        self::assertInstanceOf(Response::class, $answer);
        self::assertSame(ConflictCode::COMMITTED, $answer->headers->get('X-Conflict-Code'));
        self::assertNull($answer->headers->get('Retry-After'));
        self::assertSame(0, $this->rowCount('wiki_page_favourites'));
    }

    public function testAConcurrentFirstFavouriteIsAnsweredAsDone(): void
    {
        $worker = $this->worker('wiki_page_favourites', 1.5);

        $result = (new WikiQuickAccessStore($this->runner))->addFavourite($this->page, 'u-1');

        self::assertSame('committed', $this->finish($worker));
        self::assertSame(WikiFavouriteResult::Unchanged, $result);
        self::assertSame(1, $this->rowCount('wiki_page_favourites'));
        self::assertTrue($this->em->isOpen());
    }

    public function testAConcurrentFirstVisitLeavesOneRowWithTheLatestTime(): void
    {
        $worker = $this->worker('wiki_page_visits', 1.5);

        (new WikiQuickAccessStore($this->runner))->recordVisit($this->page, 'u-1', 10, 3650, new DateTimeImmutable('2026-11-19 12:00:00'));

        self::assertSame('committed', $this->finish($worker));
        self::assertSame(1, $this->rowCount('wiki_page_visits'));
        self::assertStringStartsWith('2026-11-19 12:00:00', (string) $this->em->getConnection()->fetchOne('SELECT visited_at FROM wiki_page_visits'));
    }

    public function testAnIndexWriteWaitingOnThePageLockIsLoggedAndLeftForTheNextSave(): void
    {
        $author   = $this->em->find(TestUserEntity::class, 'u-1');
        $revision = new WikiPageRevision($this->page, 1, '', $author, sprintf('[[wiki:%s]]', '11111111-1111-4111-8111-111111111111'));
        $this->em->persist($revision);
        $this->page->setCurrentRevision($revision);
        $this->em->flush();
        $other = $this->other();
        $other->beginTransaction();
        $other->fetchOne('SELECT id FROM wiki_pages WHERE id = ? FOR UPDATE', [$this->page->getId()]);
        $this->shortLockTimeout($this->em->getConnection());
        $logger = new RecordingLogger();

        try {
            (new WikiPageReferenceListener(new WikiPageReferenceIndex($this->runner, new WikiMarkdownReferenceExtractor()), $logger))->onPageSaved(new WikiPageSavedEvent($this->page, $revision));
        } finally {
            $other->rollBack();
            $other->close();
        }

        self::assertStringContainsString('backlink index of a saved page was not updated', $logger->lines()[0] ?? '');
        self::assertSame(0, $this->rowCount('wiki_page_references'));
        self::assertTrue((new WikiPageReferenceIndex($this->runner, new WikiMarkdownReferenceExtractor()))->reindex($this->page->getId()));
        self::assertSame(1, $this->rowCount('wiki_page_references'));
    }

    private function other(): Connection
    {
        $connection = WikiTestDatabase::connect()->getConnection();
        $this->shortLockTimeout($connection);

        return $connection;
    }

    private function shortLockTimeout(Connection $connection): void
    {
        $connection->executeStatement(str_starts_with((string) WikiTestDatabase::url(), 'mysql')
            ? 'SET SESSION innodb_lock_wait_timeout = 1'
            : "SET lock_timeout = '1s'");
    }

    /**
     * Starts the worker and waits until its row is written (uncommitted).
     *
     * @return array{0: resource, 1: array<int, resource>}
     */
    private function worker(string $table, float $hold): array
    {
        $process = proc_open([PHP_BINARY, __DIR__ . '/Race/favourite-worker.php', $table, $this->page->getId(), 'u-1', (string) $hold], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertTrue(is_resource($process));
        self::assertSame('ready', trim((string) fgets($pipes[1])), (string) stream_get_contents($pipes[2]));
        // This session waits up to 5 s for the worker's commit (it holds its row 1.5 s).
        $this->em->getConnection()->executeStatement(str_starts_with((string) WikiTestDatabase::url(), 'mysql')
            ? 'SET SESSION innodb_lock_wait_timeout = 5'
            : "SET lock_timeout = '5s'");

        return [$process, $pipes];
    }

    /**
     * @param array{0: resource, 1: array<int, resource>} $worker
     */
    private function finish(array $worker): string
    {
        [$process, $pipes] = $worker;
        $line              = trim((string) fgets($pipes[1]));
        $errors            = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        self::assertSame('', $errors);

        return $line;
    }

    private function rowCount(string $table): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . $table);
    }
}
