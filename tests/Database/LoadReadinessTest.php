<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use Doctrine\DBAL\Driver\Exception as DriverExceptionInterface;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Nowo\WikiBundle\Collaboration\DoctrineWikiCollabStateStore;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageLinkRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\LoadReadinessFactory;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Throwable;

/**
 * 1.37 (Beacon spec 239): the races of the wiki's write paths, replayed deterministically: a second entity manager on
 * the same connection plays the other request, which commits between the moment this request loaded its rows and its
 * write. Each test fails on 1.36.0 (run it there with the same file: {@see LoadReadinessFactory} leaves the runner out)
 * and passes since 1.37. SQLite by default, MySQL / PostgreSQL with WIKI_TEST_DATABASE_URL.
 */
final class LoadReadinessTest extends TestCase
{
    private EntityManager $em;

    private TestUserEntity $author;

    private WikiSpace $space;

    protected function setUp(): void
    {
        $this->em     = WikiTestDatabase::create();
        $this->author = new TestUserEntity('u-load');
        $this->space  = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->em->persist($this->author);
        $this->em->persist($this->space);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
    }

    /** A page trashed or archived by another request after this one loaded it is not edited (1.36 wrote the revision). */
    public function testASaveOfAPageArchivedMeanwhileIsRefused(): void
    {
        $page = $this->page();
        $peer = LoadReadinessFactory::peer($this->em);
        LoadReadinessFactory::pages($peer)->archive($this->peerPage($peer, $page));

        try {
            LoadReadinessFactory::pages($this->em)->saveRevision($page, 'Runbook', 'late edit', $this->author, WikiPageRevision::FORMAT_MARKDOWN);
            self::fail('A save of an archived page must be refused.');
        } catch (InvalidArgumentException $refused) {
            self::assertSame('Archived pages cannot be edited.', $refused->getMessage());
        }
        self::assertSame(1, $this->revisionCount($page));
    }

    /** Two saves: the second one follows the first one's revision, and the page points at the newest one. */
    public function testASaveAfterAnotherOneFollowsItsRevision(): void
    {
        $page = $this->page();
        $peer = LoadReadinessFactory::peer($this->em);
        LoadReadinessFactory::pages($peer)->saveRevision($this->peerPage($peer, $page), 'Runbook', 'theirs', $this->peerAuthor($peer), WikiPageRevision::FORMAT_MARKDOWN);

        $mine = LoadReadinessFactory::pages($this->em)->saveRevision($page, 'Runbook', 'mine', $this->author, WikiPageRevision::FORMAT_MARKDOWN);

        self::assertSame(3, $mine->getRevisionNumber());
        self::assertSame('mine', $this->em->getConnection()->fetchOne('SELECT r.content_markdown FROM wiki_pages p JOIN wiki_page_revisions r ON r.id = p.current_revision_id WHERE p.id = ?', [$page->getId()]));
    }

    /** Two requests that both loaded the page active archive it once and announce it once (1.36 announced it twice). */
    public function testTwoArchivesOfOnePageAnnounceItOnce(): void
    {
        $page   = $this->page();
        $events = 0;
        $count  = static function () use (&$events): void {
            ++$events;
        };
        $peer           = LoadReadinessFactory::peer($this->em);
        $peerDispatcher = new EventDispatcher();
        $peerDispatcher->addListener(WikiEvents::PAGE_ARCHIVED, $count);
        LoadReadinessFactory::pages($peer, $peerDispatcher)->archive($this->peerPage($peer, $page));

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WikiEvents::PAGE_ARCHIVED, $count);
        LoadReadinessFactory::pages($this->em, $dispatcher)->archive($page);

        self::assertSame(1, $events);
        self::assertTrue($page->isArchived());
    }

    /** A storage failure between the page and its first revision leaves nothing behind (1.36 kept a page without one). */
    public function testAFailedCreateLeavesNoPageBehind(): void
    {
        $inner     = new DoctrineOrmWikiPageRevisionRepository($this->em);
        $revisions = new class($inner) implements WikiPageRevisionRepositoryInterface {
            public function __construct(private readonly WikiPageRevisionRepositoryInterface $inner)
            {
            }

            public function save(WikiPageRevision $revision): void
            {
                $driver = new class('Deadlock found when trying to get lock', 1213) extends RuntimeException implements DriverExceptionInterface {
                    public function getSQLState(): string
                    {
                        return '40001';
                    }
                };

                throw new DeadlockException($driver, null);
            }

            public function findById(string $id): ?WikiPageRevision
            {
                return $this->inner->findById($id);
            }

            public function findByPage(WikiPage $page, ?int $limit = null, int $offset = 0): array
            {
                return $this->inner->findByPage($page, $limit, $offset);
            }

            public function findByPageAndNumber(WikiPage $page, int $number): ?WikiPageRevision
            {
                return $this->inner->findByPageAndNumber($page, $number);
            }

            public function getNextRevisionNumber(WikiPage $page): int
            {
                return $this->inner->getNextRevisionNumber($page);
            }
        };

        try {
            LoadReadinessFactory::pages($this->em, new EventDispatcher(), $revisions)->create($this->space, 'Runbook', 'base', $this->author, null, null, WikiPageRevision::FORMAT_MARKDOWN);
            self::fail('The create must fail.');
        } catch (Throwable) {
        }

        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM wiki_pages'));
        self::assertSame(0, $this->em->getConnection()->getTransactionNestingLevel());
    }

    /** Two moderators resolve a thread at once: written and announced once (1.36 announced it twice). */
    public function testTwoResolvesOfOneThreadAnnounceItOnce(): void
    {
        $page   = $this->page();
        $thread = $this->thread($page);
        $events = 0;
        $count  = static function () use (&$events): void {
            ++$events;
        };

        $peer           = LoadReadinessFactory::peer($this->em);
        $peerDispatcher = new EventDispatcher();
        $peerDispatcher->addListener(WikiEvents::COMMENT_RESOLVED, $count);
        $peerPage = $this->peerPage($peer, $page);
        LoadReadinessFactory::comments($peer, $this->space, $peerDispatcher)->patchThread($this->peerAuthor($peer), $this->space, $peerPage, $thread->getId(), ['resolved' => true]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WikiEvents::COMMENT_RESOLVED, $count);
        LoadReadinessFactory::comments($this->em, $this->space, $dispatcher)->patchThread($this->author, $this->space, $page, $thread->getId(), ['resolved' => true]);

        self::assertSame(1, $events);
        self::assertNotNull($this->em->getConnection()->fetchOne('SELECT resolved_at FROM wiki_comment_threads WHERE id = ?', [$thread->getId()]));
    }

    /** A co-editing append never writes what the application left pending in a shared manager (1.36 flushed it). */
    public function testACollabAppendNeverWritesTheApplicationsPendingChanges(): void
    {
        $page  = $this->page();
        $store = new DoctrineWikiCollabStateStore($this->em);
        $store->reset($page->getId(), null);
        $this->em->persist(new TestUserEntity('u-pending'));

        $store->append($page->getId(), 'dQ==');
        $store->compact($page->getId(), 'cw==', 1);

        self::assertSame(0, (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM test_users WHERE id = 'u-pending'"));
        self::assertSame('cw==', $store->load($page->getId())->snapshot);
    }

    /** House rule LQ: a link is found by its exact host object id (MySQL's default collation folds case). */
    public function testALinkIsFoundByItsExactObjectIdOnly(): void
    {
        $page  = $this->page();
        $links = LoadReadinessFactory::links($this->em);
        $links->link($page, 'task', 'ABC-1', $this->author);
        $links->link($page, 'task', 'abc-1', $this->author);

        $rows = $this->em->getConnection()->fetchFirstColumn('SELECT object_id FROM wiki_page_links ORDER BY object_id');
        self::assertSame(['ABC-1', 'abc-1'], $rows);
        self::assertCount(1, (new DoctrineOrmWikiPageLinkRepository($this->em))->findByHostObject('task', 'abc-1'));
    }

    private function page(): WikiPage
    {
        return LoadReadinessFactory::pages($this->em)->create($this->space, 'Runbook', 'base', $this->author, null, null, WikiPageRevision::FORMAT_MARKDOWN);
    }

    private function thread(WikiPage $page): WikiCommentThread
    {
        $json   = LoadReadinessFactory::comments($this->em, $this->space)->create($this->author, $this->space, $page, null, 'first');
        $thread = $this->em->find(WikiCommentThread::class, (string) ($json['id'] ?? ''));
        self::assertInstanceOf(WikiCommentThread::class, $thread);

        return $thread;
    }

    private function peerPage(EntityManager $peer, WikiPage $page): WikiPage
    {
        $found = $peer->find(WikiPage::class, $page->getId());
        self::assertInstanceOf(WikiPage::class, $found);

        return $found;
    }

    private function peerAuthor(EntityManager $peer): TestUserEntity
    {
        $found = $peer->find(TestUserEntity::class, 'u-load');
        self::assertInstanceOf(TestUserEntity::class, $found);

        return $found;
    }

    private function revisionCount(WikiPage $page): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM wiki_page_revisions WHERE page_id = ?', [$page->getId()]);
    }
}
