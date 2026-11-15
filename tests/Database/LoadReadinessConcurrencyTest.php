<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use Doctrine\ORM\EntityManager;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\LoadReadinessFactory;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use PHPUnit\Framework\TestCase;

use function array_count_values;
use function array_merge;
use function explode;
use function microtime;
use function proc_close;
use function proc_open;
use function range;
use function sort;
use function stream_get_contents;
use function sys_get_temp_dir;
use function trim;
use function uniqid;

use const PHP_BINARY;

/**
 * 1.37 (Beacon spec 239): two processes write the same rows at the same moment on a real server (MySQL, PostgreSQL),
 * each holding its write back a little after its read ({@see Race/load-worker.php}). On 1.36.0 every one of these lost
 * an update, broke a unique key with a 500 or let a cap be passed twice; since 1.37 the rows are locked in one order and
 * read again under the lock. Skipped on SQLite (no row locks: a writing transaction locks the whole database).
 */
final class LoadReadinessConcurrencyTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    private WikiPage $page;

    protected function setUp(): void
    {
        if (!WikiTestDatabase::isServer()) {
            self::markTestSkipped('Needs ' . WikiTestDatabase::ENV . ' (MySQL or PostgreSQL).');
        }
        $this->em    = WikiTestDatabase::create();
        $author      = new TestUserEntity('u-race');
        $this->space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->em->persist($author);
        $this->em->persist($this->space);
        $this->em->flush();
        $this->page = LoadReadinessFactory::pages($this->em)->create($this->space, 'Runbook', 'base', $author, null, null, WikiPageRevision::FORMAT_MARKDOWN);
    }

    protected function tearDown(): void
    {
        if (isset($this->em)) {
            $this->em->getConnection()->close();
        }
    }

    /** Last-write-wins saves of one page: numbers in sequence, none refused, the page on the newest revision. */
    public function testConcurrentSavesOfOnePageKeepTheRevisionsInOrder(): void
    {
        $answers = $this->race('save', 15, [$this->page->getId(), 'u-race']);

        self::assertSame(['ok' => 30], array_count_values($answers));
        $numbers = $this->em->getConnection()->fetchFirstColumn('SELECT revision_number FROM wiki_page_revisions WHERE page_id = ? ORDER BY revision_number', [$this->page->getId()]);
        self::assertSame(range(1, 31), array_map(intval(...), $numbers));
        self::assertSame(31, (int) $this->em->getConnection()->fetchOne('SELECT r.revision_number FROM wiki_pages p JOIN wiki_page_revisions r ON r.id = p.current_revision_id WHERE p.id = ?', [$this->page->getId()]));
    }

    /** The same links sent by two processes: each stored once, no unique-key failure. */
    public function testConcurrentLinksOfOneObjectAreStoredOnce(): void
    {
        $answers = $this->race('link', 10, [$this->page->getId(), 'u-race']);

        self::assertSame(['ok' => 20], array_count_values($answers));
        self::assertSame(10, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM wiki_page_links WHERE page_id = ?', [$this->page->getId()]));
    }

    /** Replies to one thread from two processes: each gets its own position. */
    public function testConcurrentRepliesGetTheirOwnPositions(): void
    {
        $thread  = $this->thread();
        $answers = $this->race('reply', 10, [$this->space->getId(), $this->page->getId(), $thread->getId(), 'u-race']);

        self::assertSame(['ok' => 20], array_count_values($answers));
        $positions = array_map(intval(...), $this->em->getConnection()->fetchFirstColumn('SELECT position FROM wiki_comments WHERE thread_id = ? ORDER BY position', [$thread->getId()]));
        self::assertSame(range(0, 20), $positions);
    }

    /** The same reaction sent twice at once (a double click): stored once, no unique-key failure. */
    public function testTheSameReactionSentTwiceAtOnceIsStoredOnce(): void
    {
        $thread  = $this->thread();
        $comment = (string) $this->em->getConnection()->fetchOne('SELECT id FROM wiki_comments WHERE thread_id = ?', [$thread->getId()]);
        $answers = $this->race('react', 1, [$this->space->getId(), $this->page->getId(), $comment, 'u-race']);

        self::assertSame(['ok' => 2], array_count_values($answers));
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM wiki_comment_reactions WHERE comment_id = ?', [$comment]));
    }

    /** Two uploads into the room left for one: exactly one is accepted. */
    public function testTwoUploadsIntoTheRoomForOneAcceptOne(): void
    {
        $directory = sys_get_temp_dir() . '/wiki-load-' . uniqid();
        $answers   = $this->race('upload', 1, [$this->space->getId(), $this->page->getId(), $directory, '15']);

        sort($answers);
        self::assertSame(['ok', 'refused Nowo\WikiBundle\Attachment\WikiAttachmentRejectedException'], $answers);
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM wiki_attachments'));
    }

    private function thread(): WikiCommentThread
    {
        $author = $this->em->find(TestUserEntity::class, 'u-race');
        self::assertInstanceOf(TestUserEntity::class, $author);
        $json   = LoadReadinessFactory::comments($this->em, $this->space)->create($author, $this->space, $this->page, null, 'first');
        $thread = $this->em->find(WikiCommentThread::class, (string) ($json['id'] ?? ''));
        self::assertInstanceOf(WikiCommentThread::class, $thread);

        return $thread;
    }

    /**
     * Runs the scenario in two processes at once.
     *
     * @param list<string> $args
     *
     * @return list<string> the answers of both, one per write
     */
    private function race(string $scenario, int $writes, array $args): array
    {
        $startAt = (string) (microtime(true) + 1.5);
        $running = [];
        foreach ([1, 2] as $worker) {
            $process = proc_open([PHP_BINARY, __DIR__ . '/Race/load-worker.php', $scenario, $startAt, (string) $writes, ...$args], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            $running[$worker] = [$process, $pipes];
        }

        $answers = [];
        foreach ($running as [$process, $pipes]) {
            $output = trim((string) stream_get_contents($pipes[1]));
            $errors = (string) stream_get_contents($pipes[2]);
            self::assertSame(0, proc_close($process), $errors . $output);
            $answers = array_merge($answers, $output === '' ? [] : explode("\n", $output));
        }

        return $answers;
    }
}
