<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use Doctrine\DBAL\Driver\Exception as DriverExceptionInterface;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Exception\EntityManagerClosed;
use InvalidArgumentException;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\WikiBundle\Attachment\LocalWikiAttachmentStorage;
use Nowo\WikiBundle\Attachment\WikiAttachmentRejectedException;
use Nowo\WikiBundle\Collaboration\DoctrineWikiCollabStateStore;
use Nowo\WikiBundle\Collaboration\WikiCollabUnitLostException;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiAttachmentRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiTrashRepository;
use Nowo\WikiBundle\Service\WikiRevisionCollisionException;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\LoadReadinessFactory;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Trash\WikiTrashService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Throwable;

use function file_put_contents;
use function str_repeat;
use function str_starts_with;
use function sys_get_temp_dir;
use function tempnam;
use function uniqid;

/**
 * 1.37, audit QN: the proofs of concept of the audit, inverted (they pass only with the fixes). Shared entity manager
 * (the default). SQLite by default; the server-only ones with WIKI_TEST_DATABASE_URL (MySQL).
 */
final class LoadReadinessQnTest extends TestCase
{
    private EntityManager $em;

    private TestUserEntity $author;

    private WikiSpace $space;

    protected function setUp(): void
    {
        $this->em     = WikiTestDatabase::create();
        $this->author = new TestUserEntity('u-qn');
        $this->space  = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->em->persist($this->author);
        $this->em->persist($this->space);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
    }

    /** QN-M1: a unit that flushed the application's pending row and then failed closes the manager: no silent loss. */
    public function testAFailedUnitThatFlushedTheApplicationsChangesClosesTheManager(): void
    {
        $page = $this->page();
        $this->em->persist(new TestUserEntity('host-pending'));
        $this->em->getEventManager()->addEventListener(Events::postFlush, new class {
            public bool $armed = true;

            public function postFlush(): void
            {
                if ($this->armed) {
                    $this->armed = false;

                    throw new RuntimeException('host postFlush listener failed');
                }
            }
        });

        try {
            LoadReadinessFactory::links($this->em)->link($page, 'task', 'T-1', $this->author);
            self::fail('Expected the failure.');
        } catch (RuntimeException $failure) {
            self::assertSame('host postFlush listener failed', $failure->getMessage());
        }

        self::assertFalse($this->em->isOpen(), 'The manager is closed: the application\'s next flush fails loudly.');
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM test_users WHERE id = 'host-pending'"));
        $this->expectException(EntityManagerClosed::class);
        $this->em->flush();
    }

    /** QN-M1 (MySQL): a trash purge whose later statement times out on a lock closes the manager it flushed. */
    public function testAPurgeThatTimesOutAfterItsFlushClosesTheManager(): void
    {
        if (!WikiTestDatabase::isServer() || !str_starts_with((string) WikiTestDatabase::url(), 'mysql')) {
            self::markTestSkipped('Needs MySQL in ' . WikiTestDatabase::ENV . '.');
        }
        $page      = $this->page();
        $directory = sys_get_temp_dir() . '/wiki-qn-' . uniqid();
        LoadReadinessFactory::attachments($this->em, $directory, 1000)->upload($page, $this->file(10), 'g.txt');
        $pages = new DoctrineOrmWikiPageRepository($this->em);
        $trash = LoadReadinessFactory::make(WikiTrashService::class, [
            'pages'                => $pages,
            'trash'                => new DoctrineOrmWikiTrashRepository($this->em),
            'writer'               => $pages,
            'dispatcher'           => new EventDispatcher(),
            'storage'              => new LocalWikiAttachmentStorage($directory),
            'attachmentRepository' => new DoctrineOrmWikiAttachmentRepository($this->em),
            'runner'               => LoadReadinessFactory::runner($this->em),
        ]);
        $batch = $trash->delete($page);
        $this->em->persist(new TestUserEntity('host-pending-2'));
        $other = WikiTestDatabase::connect()->getConnection();
        $other->beginTransaction();
        $other->fetchOne('SELECT id FROM wiki_pages WHERE id = ? FOR UPDATE', [$page->getId()]);
        $this->em->getConnection()->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');

        try {
            $trash->purge($this->space, $batch);
            self::fail('Expected the lock wait timeout.');
        } catch (TransientStorageFailure) {
        } finally {
            $other->rollBack();
            $other->close();
        }

        self::assertFalse($this->em->isOpen());
    }

    /** QN-L1: a wiki deletion scheduled by a failed unit is not carried out by the application's next flush. */
    public function testAScheduledWikiDeletionOfAFailedUnitIsForgotten(): void
    {
        $page       = $this->page();
        $events     = 0;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WikiEvents::PAGE_UNLINKED, static function () use (&$events): void {
            ++$events;
        });
        $links = LoadReadinessFactory::links($this->em, $dispatcher);
        $links->link($page, 'task', 'T-1', $this->author);
        $this->em->getEventManager()->addEventListener(Events::preFlush, new class {
            public bool $armed = true;

            public function preFlush(): void
            {
                if ($this->armed) {
                    $this->armed = false;

                    throw new RuntimeException('host preFlush listener failed');
                }
            }
        });

        try {
            $links->unlink($page, 'task', 'T-1', $this->author);
            self::fail('Expected the failure.');
        } catch (RuntimeException) {
        }
        self::assertTrue($this->em->isOpen());
        self::assertSame([], $this->em->getUnitOfWork()->getScheduledEntityDeletions());
        $this->em->flush();

        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM wiki_page_links'));
        self::assertSame(0, $events);
    }

    /** QN-L2: with a closed shared manager every wiki write answers a transient failure (503), never a 500. */
    public function testAClosedSharedManagerAnswersATransientFailure(): void
    {
        $page = $this->page();
        $this->em->close();

        foreach ([
            'archive' => fn () => LoadReadinessFactory::pages($this->em)->archive($page),
            'unlink'  => fn (): bool => LoadReadinessFactory::links($this->em)->unlink($page, 'task', 'none', $this->author),
        ] as $name => $write) {
            try {
                $write();
                self::fail($name . ': expected the transient failure.');
            } catch (TransientStorageFailure $failure) {
                self::assertSame(1, $failure->retryAfterSeconds());
            }
        }
        try {
            LoadReadinessFactory::pages($this->em)->saveRevision($page, 'Runbook', 'x', $this->author, WikiPageRevision::FORMAT_MARKDOWN);
            self::fail('save: expected the collision.');
        } catch (WikiRevisionCollisionException $collision) {
            self::assertInstanceOf(TransientStorageFailure::class, $collision->getPrevious());
        }
    }

    /** QN-L3 (MySQL): inside the application's REPEATABLE READ transaction the quota still holds. */
    public function testTheQuotaHoldsInsideTheApplicationsTransaction(): void
    {
        if (!WikiTestDatabase::isServer() || !str_starts_with((string) WikiTestDatabase::url(), 'mysql')) {
            self::markTestSkipped('Needs MySQL in ' . WikiTestDatabase::ENV . '.');
        }
        $page      = $this->page();
        $directory = sys_get_temp_dir() . '/wiki-qn-' . uniqid();
        $other     = WikiTestDatabase::connect();
        $otherPage = $other->find(WikiPage::class, $page->getId());
        self::assertInstanceOf(WikiPage::class, $otherPage);
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        $connection->fetchOne('SELECT COUNT(*) FROM wiki_attachments'); // the snapshot is taken here
        LoadReadinessFactory::attachments($other, $directory, 100)->upload($otherPage, $this->file(60), 'a.txt');

        try {
            LoadReadinessFactory::attachments($this->em, $directory, 100)->upload($page, $this->file(60), 'b.txt');
            self::fail('The quota must hold.');
        } catch (WikiAttachmentRejectedException $refused) {
            self::assertSame(WikiAttachmentRejectedException::QUOTA_EXCEEDED, $refused->reason);
        } finally {
            $connection->commit();
            $other->getConnection()->close();
        }
        self::assertSame(60, (int) $connection->fetchOne('SELECT COALESCE(SUM(size), 0) FROM wiki_attachments'));
    }

    /** QN-L4: a transient failure of the co-editing log inside a transaction it did not open is never swallowed. */
    public function testATransientFailureOfTheLogInsideATransactionIsTheWholeUnits(): void
    {
        $store = new DoctrineWikiCollabStateStore($this->em);
        $store->reset('p1', null);
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            $store->whileLocked('p1', static function (): never {
                throw new DeadlockException(new class('Deadlock found', 1213) extends RuntimeException implements DriverExceptionInterface {
                    public function getSQLState(): string
                    {
                        return '40001';
                    }
                }, null);
            });
            self::fail('Expected the lost unit.');
        } catch (Throwable $failure) {
            self::assertInstanceOf(WikiCollabUnitLostException::class, $failure);
            self::assertInstanceOf(TransientStorageFailure::class, $failure->getPrevious());
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

    /** The log's own outermost transaction: a transient failure stays a TransientStorageFailure (retried as before). */
    public function testATransientFailureOfTheLogsOwnTransactionStaysTransient(): void
    {
        $store = new DoctrineWikiCollabStateStore($this->em);
        $store->reset('p1', null);

        $this->expectException(TransientStorageFailure::class);
        $store->whileLocked('p1', static function (): never {
            throw new DeadlockException(new class('Deadlock found', 1213) extends RuntimeException implements DriverExceptionInterface {
                public function getSQLState(): string
                {
                    return '40001';
                }
            }, null);
        });
    }

    /** QN-I2: a page purged meanwhile is not linked. */
    public function testALinkToAPagePurgedMeanwhileIsRefused(): void
    {
        $page = $this->page();
        $this->em->getConnection()->executeStatement('UPDATE wiki_pages SET current_revision_id = NULL WHERE id = ?', [$page->getId()]);
        $this->em->getConnection()->executeStatement('DELETE FROM wiki_page_revisions WHERE page_id = ?', [$page->getId()]);
        $this->em->getConnection()->executeStatement('DELETE FROM wiki_pages WHERE id = ?', [$page->getId()]);

        $this->expectException(InvalidArgumentException::class);
        LoadReadinessFactory::links($this->em)->link($page, 'task', 'T-9', $this->author);
    }

    private function page(): WikiPage
    {
        return LoadReadinessFactory::pages($this->em)->create($this->space, 'Runbook', 'base', $this->author, null, null, WikiPageRevision::FORMAT_MARKDOWN);
    }

    private function file(int $bytes): string
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'wiki-qn');
        file_put_contents($file, str_repeat('a', $bytes));

        return $file;
    }
}
