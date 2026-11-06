<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use Closure;
use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Nowo\WikiBundle\Attachment\WikiAttachmentStorageInterface;
use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentReaction;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageLink;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Entity\WikiTrashEntry;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiPageFilesPurgedEvent;
use Nowo\WikiBundle\Event\WikiTrashEvent;
use Nowo\WikiBundle\PageFile\WikiPageFile;
use Nowo\WikiBundle\PageFile\WikiPageFileExtractor;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiAttachmentRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiCommentRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiTrashRepository;
use Nowo\WikiBundle\Repository\WikiPageTreeWriterInterface;
use Nowo\WikiBundle\Repository\WikiTrashRepositoryInterface;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use Nowo\WikiBundle\Trash\WikiTrashService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Throwable;

use function array_column;
use function array_map;
use function sort;

/**
 * 1.14 (T065): the trash — a deletion carries the sub-tree, restores together, purges for good, expires.
 */
final class WikiTrashServiceTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    private TestUserEntity $user;

    private DoctrineOrmWikiPageRepository $pages;

    /** @var list<array{0: string, 1: WikiTrashEvent}> */
    private array $events = [];

    /** @var list<string> */
    private array $deletedFiles = [];

    protected function setUp(): void
    {
        $this->em    = SqliteWikiEntityManager::create();
        $this->space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $this->user  = new TestUserEntity('u-1');
        $this->em->persist($this->space);
        $this->em->persist($this->user);
        $this->em->flush();
        $this->pages = new DoctrineOrmWikiPageRepository($this->em);
    }

    public function testDeleteMovesTheSubTreeAndRestoreBringsItBackTogether(): void
    {
        $ops   = $this->page('ops');
        $run   = $this->page('run', $ops);
        $old   = $this->page('old', $run);
        $deep  = $this->page('deep', $run);
        $other = $this->page('other');
        $old->archive();
        $this->em->flush();

        $service = $this->service();
        $batch   = $service->delete($ops, $this->user);

        self::assertTrue($ops->isArchived() && $run->isArchived() && $deep->isArchived());
        self::assertFalse($other->isArchived());
        self::assertSame(['deep', 'old', 'ops', 'run'], $this->slugs(array_keys($service->trashedIds($this->space))));
        self::assertCount(1, $service->roots($this->space));
        self::assertSame($ops, $service->roots($this->space)[0]->getPage());
        self::assertCount(4, $service->batch($this->space, $batch));
        self::assertSame(WikiEvents::PAGE_TRASHED, $this->events[0][0]);
        self::assertSame('ops', $this->events[0][1]->rootTitle);
        self::assertSame($this->user, $this->events[0][1]->actor);

        $root = $service->restore($this->space, $batch, $this->user);
        self::assertSame($ops, $root);
        self::assertFalse($ops->isArchived() || $run->isArchived() || $deep->isArchived());
        // A page archived before the deletion stays archived.
        self::assertTrue($old->isArchived());
        self::assertSame([], $service->trashedIds($this->space));
        self::assertSame(WikiEvents::TRASH_RESTORED, $this->events[1][0]);
        self::assertNull($service->restore($this->space, $batch));

        // A later deletion of the parent leaves the sub-pages already in the trash out of its batch.
        $sub   = $service->delete($run);
        $outer = $service->delete($ops);
        self::assertSame(['ops'], $this->slugs(array_map(static fn (WikiTrashEntry $e): string => $e->getPage()->getId(), $service->batch($this->space, $outer))));
        $service->restore($this->space, $outer);
        $service->restore($this->space, $sub);
        self::assertSame($ops, $run->getParent());
        self::assertFalse($deep->isArchived());
        self::assertTrue($old->isArchived());
    }

    public function testACorruptParentCycleIsDeletedOnceAndTheEntryTellsItsRoot(): void
    {
        $a = $this->page('a');
        $b = $this->page('b', $a);
        $a->moveTo($b, 0);
        $this->em->flush();

        $batch   = $this->service()->delete($a);
        $entries = $this->service()->batch($this->space, $batch);

        self::assertCount(2, $entries);
        self::assertTrue($entries[0]->isRoot());
        self::assertFalse($entries[1]->isRoot());
        self::assertNotSame($entries[0]->getId(), $entries[1]->getId());
        self::assertSame($batch, $entries[1]->getBatchId());
    }

    public function testARestoredPageWhoseParentIsGoneGoesToTheTopLevel(): void
    {
        $ops = $this->page('ops');
        $run = $this->page('run', $ops);

        $service = $this->service();
        $first   = $service->delete($run);
        $service->delete($ops);

        $service->restore($this->space, $first);

        self::assertNull($run->getParent());
        self::assertFalse($run->isArchived());
        self::assertTrue($ops->isArchived());
    }

    public function testPurgeDeletesThePagesTheirHistoryLinksAndAttachments(): void
    {
        $ops = $this->page('ops');
        $run = $this->page('run', $ops);
        $this->em->persist($revision = new WikiPageRevision($run, 1, '<p>x</p>', $this->user));
        $run->setCurrentRevision($revision);
        $this->em->persist(new WikiPageLink($run, 'issue', '1'));
        $this->em->persist(new WikiAttachment($run, $this->space, 'a.txt', 'k/1', 'text/plain', 5, str_repeat('a', 64)));
        $keep = $this->page('keep');
        $this->em->flush();

        $service = $this->service();
        $batch   = $service->delete($ops);
        // A page moved under the deleted branch after the deletion is not purged with it.
        $late = $this->page('late', $run);

        self::assertSame(2, $service->purge($this->space, $batch, $this->user));
        $this->em->clear();

        self::assertNull($this->em->find(WikiPage::class, $ops->getId()));
        self::assertNull($this->em->find(WikiPage::class, $run->getId()));
        self::assertSame([], $this->em->getRepository(WikiPageRevision::class)->findAll());
        self::assertSame([], $this->em->getRepository(WikiPageLink::class)->findAll());
        self::assertSame([], $this->em->getRepository(WikiAttachment::class)->findAll());
        self::assertSame([], $this->em->getRepository(WikiTrashEntry::class)->findAll());
        self::assertSame(['k/1'], $this->deletedFiles);
        self::assertNotNull($this->em->find(WikiPage::class, $keep->getId()));
        self::assertNull($this->em->find(WikiPage::class, $late->getId())?->getParent());
        self::assertSame(WikiEvents::TRASH_PURGED, $this->events[1][0]);
        self::assertSame(0, $service->purge($this->space, $batch));
    }

    /** 1.20 (`page_files.purge_event`): the purge reports the files every revision of the purged pages referred to. */
    public function testPurgeReportsTheFilesOfEveryRevisionToTheHost(): void
    {
        $ops = $this->page('ops');
        $run = $this->page('run', $ops);
        $this->em->persist(new WikiPageRevision($ops, 1, '<p>x</p>', $this->user, "::file[a.pdf]{href=\"/files/a\" size=\"5\" type=\"application/pdf\"}\n\n![shot](/files/b.png)"));
        $this->em->persist($current = new WikiPageRevision($ops, 2, '<p>x</p>', $this->user, "![shot](/files/b.png)\n\n::file[c.csv]{href=\"/files/c\"}"));
        $ops->setCurrentRevision($current);
        $this->em->persist(new WikiPageRevision($run, 1, '<p>html only</p>', $this->user));
        $keep = $this->page('keep');
        $this->em->persist(new WikiPageRevision($keep, 1, '<p>x</p>', $this->user, '![kept](/files/kept.png)'));
        $this->em->flush();

        $files   = [];
        $service = $this->service(pageFiles: true, onFiles: static function (WikiPageFilesPurgedEvent $event) use (&$files): void {
            $files[] = $event;
        });
        $batch = $service->delete($ops, $this->user);
        self::assertSame([], $files, 'Delete keeps the files (a restore needs them).');

        self::assertSame(2, $service->purge($this->space, $batch, $this->user));
        self::assertCount(1, $files);
        self::assertSame($batch, $files[0]->batchId);
        self::assertSame($this->user, $files[0]->actor);
        self::assertCount(2, $files[0]->pageIds);
        // Every revision, newest first, each URL once.
        self::assertSame(['/files/b.png', '/files/c', '/files/a'], array_map(static fn (WikiPageFile $f): string => $f->url, $files[0]->files));
        self::assertSame(5, $files[0]->files[2]->size);

    }

    /** Audit GR-I8: a cap per event (truncated + warning), and the files event survives a throwing TRASH_PURGED listener. */
    public function testThePurgeFilesEventIsCappedAndSurvivesAFailingTrashListener(): void
    {
        $page = $this->page('many');
        $this->em->persist(new WikiPageRevision($page, 1, '<p>x</p>', $this->user, "![a](/files/a)\n\n![b](/files/b)\n\n![c](/files/c)"));
        $this->em->flush();
        $events  = [];
        $logger  = new RecordingLogger();
        $service = $this->service(logger: $logger, pageFiles: true, onFiles: static function (WikiPageFilesPurgedEvent $event) use (&$events): void {
            $events[] = $event;
        }, maxPurgeFiles: 2, failPurgedListener: true);
        $batch = $service->delete($page);

        try {
            $service->purge($this->space, $batch);
            self::fail('The TRASH_PURGED listener exception is rethrown.');
        } catch (RuntimeException $exception) {
            self::assertSame('listener', $exception->getMessage());
        }
        self::assertCount(1, $events);
        self::assertTrue($events[0]->truncated);
        self::assertSame(['/files/a', '/files/b'], array_map(static fn (WikiPageFile $f): string => $f->url, $events[0]->files));
        self::assertNotSame([], $logger->records);
    }

    /** 1.20: no event without files, nor without `page_files.purge_event`. */
    public function testPurgeReportsNothingWithoutFilesOrWithoutTheOption(): void
    {
        $plain = $this->page('plain');
        $other = $this->page('other');
        $this->em->persist(new WikiPageRevision($other, 1, '<p>x</p>', $this->user, '![x](/files/x.png)'));
        $this->em->flush();
        $events = [];
        $record = static function () use (&$events): void {
            $events[] = 'files';
        };

        $quiet  = $this->service(pageFiles: true, onFiles: $record);
        $first  = $quiet->delete($plain);
        $off    = $this->service(onFiles: $record);
        $second = $off->delete($other);
        $quiet->purge($this->space, $first);
        $off->purge($this->space, $second);

        self::assertSame([], $events);
    }

    /** 1.16 (audit EI-L5): the comments of purged pages go too, even without enforced foreign keys (SQLite). */
    public function testPurgeDeletesTheCommentsWithoutForeignKeys(): void
    {
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('PRAGMA foreign_keys'), 'This test needs FKs off.');
        $ops     = $this->page('ops');
        $keep    = $this->page('keep');
        $thread  = new WikiCommentThread($ops, $this->user);
        $comment = new WikiComment($thread, $this->user, 'Body with the quoted text');
        $kept    = new WikiCommentThread($keep, $this->user);
        foreach ([$thread, $comment, new WikiCommentReaction($comment, $this->user, '👍'), $kept, new WikiComment($kept, $this->user, 'Stays')] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();

        $comments = new DoctrineOrmWikiCommentRepository($this->em);
        $service  = $this->service(repo: new DoctrineOrmWikiTrashRepository($this->em, $comments));
        $batch    = $service->delete($ops);
        self::assertSame(1, $service->purge($this->space, $batch, $this->user));
        $this->em->clear();

        $count = fn (string $table): int => (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . $table);
        self::assertSame([1, 1, 0], [$count('wiki_comment_threads'), $count('wiki_comments'), $count('wiki_comment_reactions')]);
        $comments->deleteForPages([]);
        $comments->deleteForPages(['no-such-page']);
        $comments->deleteForPages([$keep->getId()]);
        self::assertSame([0, 0], [$count('wiki_comment_threads'), $count('wiki_comments')]);
    }

    /** Audit DO-M1 (PoC): a restore that commits while a purge of the same batch runs keeps its pages. */
    public function testARestoreThatWinsTheRaceKeepsItsPages(): void
    {
        $ops      = $this->page('ops');
        $run      = $this->page('run', $ops);
        $batch    = $this->service()->delete($ops);
        $restorer = $this->service();
        $purger   = $this->service(repo: $this->hooked(function () use ($restorer, $batch): void {
            $restorer->restore($this->space, $batch);
        }));

        self::assertSame(0, $purger->purge($this->space, $batch));
        $this->em->clear();
        self::assertNotNull($this->em->find(WikiPage::class, $ops->getId()));
        self::assertNotNull($this->em->find(WikiPage::class, $run->getId()));
        self::assertSame([], $this->em->getRepository(WikiTrashEntry::class)->findAll());
    }

    /** Audit DO-L1 (PoC): a failed purge rolls back the attachment rows and unlinks no file. */
    public function testAFailedPurgeKeepsTheAttachmentsAndTheirFiles(): void
    {
        $run = $this->page('run');
        $this->em->persist(new WikiAttachment($run, $this->space, 'a.txt', 'k/1', 'text/plain', 5, str_repeat('a', 64)));
        $this->em->flush();
        $batch = $this->service()->delete($run);

        try {
            $this->service(repo: $this->hooked(null, true))->purge($this->space, $batch);
            self::fail('The purge did not fail.');
        } catch (RuntimeException $e) {
            self::assertSame('database down', $e->getMessage());
        }
        $this->em->clear();
        self::assertSame([], $this->deletedFiles);
        self::assertCount(1, $this->em->getRepository(WikiAttachment::class)->findAll());
        self::assertTrue($this->em->find(WikiPage::class, $run->getId())?->isArchived());
        self::assertCount(1, $this->em->getRepository(WikiTrashEntry::class)->findAll());
    }

    public function testAFileThatCannotBeUnlinkedIsLoggedAfterTheCommit(): void
    {
        $run = $this->page('run');
        $this->em->persist(new WikiAttachment($run, $this->space, 'a.txt', 'k/2', 'text/plain', 5, str_repeat('b', 64)));
        $this->em->flush();
        $logger = new RecordingLogger();
        $batch  = $this->service(failUnlink: true, logger: $logger)->delete($run);

        self::assertSame(1, $this->service(failUnlink: true, logger: $logger)->purge($this->space, $batch));
        self::assertContains('Wiki trash: an attachment file could not be deleted.', array_column($logger->records, 'message'));
        $this->em->clear();
        self::assertNull($this->em->find(WikiPage::class, $run->getId()));
    }

    public function testAFailedRestoreIsRolledBack(): void
    {
        $page  = $this->page('p');
        $batch = $this->service()->delete($page);

        $this->expectExceptionMessage('remove failed');
        try {
            $this->service(repo: $this->hooked(null, false, true))->restore($this->space, $batch);
        } finally {
            self::assertFalse($this->em->getConnection()->isTransactionActive());
        }
    }

    public function testStaleEntriesWithoutArchivedPagesAreDropped(): void
    {
        $page  = $this->page('p');
        $batch = $this->service()->delete($page);
        $page->unarchive();
        $this->em->flush();

        self::assertSame(0, $this->service()->purge($this->space, $batch));
        self::assertSame([], $this->service()->trashedIds($this->space));
        self::assertFalse($page->isArchived());
    }

    public function testTheDryRunCountsEveryExpiredDeletion(): void
    {
        $service = $this->service(retention: 1);
        for ($i = 0; $i < 101; ++$i) {
            $service->delete($this->page('p' . $i));
        }
        $this->em->createQueryBuilder()->update(WikiTrashEntry::class, 't')->set('t.deletedAt', ':at')
            ->setParameter('at', new DateTimeImmutable('-3 days'))->getQuery()->execute();

        self::assertSame(['batches' => 101, 'pages' => 101], $service->purgeExpired(new DateTimeImmutable(), true));
        self::assertSame(['batches' => 101, 'pages' => 101], $service->purgeExpired(new DateTimeImmutable()));
    }

    public function testPurgeWithoutAttachmentsAndAnEmptyPageList(): void
    {
        $page    = $this->page('solo');
        $service = $this->service(attachments: false);
        $batch   = $service->delete($page);

        self::assertSame(1, $service->purge($this->space, $batch));
        (new DoctrineOrmWikiTrashRepository($this->em))->deletePages([]);
        self::assertSame(30, $service->retentionDays());
    }

    public function testTheRetentionPurgesOnlyOlderDeletions(): void
    {
        $service = $this->service(retention: 7);
        $old     = $this->page('old');
        $new     = $this->page('new');
        $service->delete($old);
        $service->delete($new);
        // Ten days ago for the first one.
        $this->em->createQueryBuilder()->update(WikiTrashEntry::class, 't')->set('t.deletedAt', ':at')
            ->where('t.page = :page')->setParameter('at', new DateTimeImmutable('-10 days'))->setParameter('page', $old)->getQuery()->execute();

        self::assertSame(['batches' => 1, 'pages' => 1], $service->purgeExpired(new DateTimeImmutable(), true));
        self::assertNotNull($this->em->find(WikiPage::class, $old->getId()));
        self::assertSame(['batches' => 1, 'pages' => 1], $service->purgeExpired(new DateTimeImmutable()));
        $this->em->clear();
        self::assertNull($this->em->find(WikiPage::class, $old->getId()));
        self::assertNotNull($this->em->find(WikiPage::class, $new->getId()));
        self::assertSame(['batches' => 0, 'pages' => 0], $service->purgeExpired(new DateTimeImmutable()));
    }

    public function testAFailedDeletionIsRolledBack(): void
    {
        $page   = $this->page('p');
        $writer = $this->createMock(WikiPageTreeWriterInterface::class);
        $writer->method('commitSpaceTransaction')->willThrowException(new RuntimeException('commit'));
        $writer->expects(self::once())->method('rollbackSpaceTransaction');

        $this->expectExceptionMessage('commit');
        (new WikiTrashService($this->pages, new DoctrineOrmWikiTrashRepository($this->em), $writer, new EventDispatcher()))->delete($page);
    }

    public function testTheTrashRepositoryRollsBackAFailedPurge(): void
    {
        $page = $this->page('p');
        $this->em->getConnection()->executeStatement('DROP TABLE wiki_page_links');

        try {
            (new DoctrineOrmWikiTrashRepository($this->em))->deletePages([$page]);
            self::fail('The purge did not fail.');
        } catch (Throwable) {
            self::assertFalse($this->em->getConnection()->isTransactionActive());
        }
    }

    private function service(bool $attachments = true, int $retention = 30, ?WikiTrashRepositoryInterface $repo = null, bool $failUnlink = false, ?LoggerInterface $logger = null, bool $pageFiles = false, ?Closure $onFiles = null, int $maxPurgeFiles = WikiTrashService::DEFAULT_MAX_PURGE_FILES, bool $failPurgedListener = false): WikiTrashService
    {
        $dispatcher = new EventDispatcher();
        if ($onFiles instanceof Closure) {
            $dispatcher->addListener(WikiEvents::PAGE_FILES_PURGED, $onFiles);
        }
        if ($failPurgedListener) {
            $dispatcher->addListener(WikiEvents::TRASH_PURGED, static function (): never {
                throw new RuntimeException('listener');
            });
        }
        foreach ([WikiEvents::PAGE_TRASHED, WikiEvents::TRASH_RESTORED, WikiEvents::TRASH_PURGED] as $name) {
            $dispatcher->addListener($name, function (WikiTrashEvent $event) use ($name): void {
                $this->events[] = [$name, $event];
            });
        }
        $files   = &$this->deletedFiles;
        $storage = new class($files, $failUnlink) implements WikiAttachmentStorageInterface {
            /** @param list<string> $deleted */
            public function __construct(private array &$deleted, private readonly bool $fail)
            {
            }

            public function write(string $key, mixed $stream): void
            {
            }

            public function readStream(string $key): mixed
            {
                return null;
            }

            public function delete(string $key): void
            {
                if ($this->fail) {
                    throw new RuntimeException('disk');
                }
                $this->deleted[] = $key;
            }

            public function keys(): iterable
            {
                return [];
            }
        };
        $attachmentRepository = new DoctrineOrmWikiAttachmentRepository($this->em);

        return new WikiTrashService(
            $this->pages,
            $repo ?? new DoctrineOrmWikiTrashRepository($this->em),
            $this->pages,
            $dispatcher,
            $attachments ? $storage : null,
            $attachments ? $attachmentRepository : null,
            $retention,
            $logger ?? new NullLogger(),
            $pageFiles ? new WikiPageFileExtractor() : null,
            $pageFiles ? new DoctrineOrmWikiPageRevisionRepository($this->em) : null,
            $maxPurgeFiles,
        );
    }

    private function page(string $slug, ?WikiPage $parent = null): WikiPage
    {
        $page = new WikiPage($this->space, $slug, $slug, $parent);
        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function slugs(array $ids): array
    {
        $slugs = array_map(fn (string $id): string => (string) $this->em->find(WikiPage::class, $id)?->getSlug(), $ids);
        sort($slugs);

        return $slugs;
    }

    private function hooked(?Closure $afterFindBatch, bool $failDelete = false, bool $failRemove = false): WikiTrashRepositoryInterface
    {
        $inner = new DoctrineOrmWikiTrashRepository($this->em);

        return new class($inner, $afterFindBatch, $failDelete, $failRemove) implements WikiTrashRepositoryInterface {
            public function __construct(private readonly DoctrineOrmWikiTrashRepository $inner, private ?Closure $hook, private readonly bool $fail, private readonly bool $failRemove)
            {
            }

            public function saveBatch(array $entries): void
            {
                $this->inner->saveBatch($entries);
            }

            public function findRoots(WikiSpace $space): array
            {
                return $this->inner->findRoots($space);
            }

            public function findBatch(WikiSpace $space, string $batchId): array
            {
                $entries = $this->inner->findBatch($space, $batchId);
                if ($this->hook instanceof Closure) {
                    $hook       = $this->hook;
                    $this->hook = null;
                    $hook();
                }

                return $entries;
            }

            public function trashedPageIds(WikiSpace $space): array
            {
                return $this->inner->trashedPageIds($space);
            }

            public function findExpiredBatches(DateTimeImmutable $before, int $limit = 100, int $offset = 0): array
            {
                return $this->inner->findExpiredBatches($before, $limit, $offset);
            }

            public function removeBatch(string $batchId): void
            {
                if ($this->failRemove) {
                    throw new RuntimeException('remove failed');
                }
                $this->inner->removeBatch($batchId);
            }

            public function deletePages(array $pages): void
            {
                if ($this->fail) {
                    throw new RuntimeException('database down');
                }
                $this->inner->deletePages($pages);
            }
        };
    }
}
