<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use Closure;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\WriteUnit\ConcurrencyConflict;
use Nowo\WikiBundle\Attachment\WikiAttachmentStorageInterface;
use Nowo\WikiBundle\Entity\WikiApiIdempotencyRecord;
use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\Entity\WikiCollabDocument;
use Nowo\WikiBundle\Entity\WikiCollabUpdate;
use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentReaction;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageFavourite;
use Nowo\WikiBundle\Entity\WikiPageLink;
use Nowo\WikiBundle\Entity\WikiPageReference;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiPageVisit;
use Nowo\WikiBundle\Entity\WikiRevisionLabel;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Entity\WikiTrashEntry;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiAttachmentEvent;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiPageFilesPurgedEvent;
use Nowo\WikiBundle\Event\WikiSpaceAccessCheckEvent;
use Nowo\WikiBundle\Event\WikiSpaceLifecycleEvent;
use Nowo\WikiBundle\Event\WikiSpacePurgedEvent;
use Nowo\WikiBundle\PageFile\WikiPageFileExtractor;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiSpaceRepository;
use Nowo\WikiBundle\Security\WikiArchivedSpaceAccessChecker;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Service\WikiEventDeferral;
use Nowo\WikiBundle\Service\WikiInvalidSlugException;
use Nowo\WikiBundle\Service\WikiPageArchivedException;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Service\WikiSlugTakenException;
use Nowo\WikiBundle\Space\WikiSpaceAccessDeniedException;
use Nowo\WikiBundle\Space\WikiSpaceLifecycle;
use Nowo\WikiBundle\Space\WikiSpaceLifecycleAction;
use Nowo\WikiBundle\Space\WikiSpaceNotFoundException;
use Nowo\WikiBundle\Space\WikiSpacePurger;
use Nowo\WikiBundle\Space\WikiSpacePurgeResult;
use Nowo\WikiBundle\Space\WikiSpaceStateException;
use Nowo\WikiBundle\Space\WikiSpaceVersionConflictException;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\LoadReadinessFactory;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use Nowo\WikiBundle\Util\WikiSlugger;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_keys;
use function array_unique;
use function array_values;
use function count;
use function in_array;
use function is_string;
use function preg_match;
use function sort;
use function str_repeat;
use function str_starts_with;

/**
 * 1.50: rename, archive, unarchive and purge of a space ({@see WikiSpaceLifecycle}) on SQLite and — through
 * WIKI_TEST_DATABASE_URL — MySQL 8.4, MariaDB 11.4 and PostgreSQL 16: permissions (a space of another tenant answers as
 * an unknown one), optimistic locking, REQ-RUNTIME-003 rule 6 (a lock conflict before anything committed is retry-safe;
 * after part of a purge committed it says so), the archived space hidden from search and navigation and read-only,
 * purge completeness (no row of any wiki table left, other spaces untouched), purge resume after a failure mid-batch.
 */
final class WikiSpaceLifecycleTest extends TestCase
{
    private EntityManager $em;

    private WikiTransactionRunner $runner;

    private EventDispatcher $dispatcher;

    private RecordingLogger $logger;

    /** @var list<string> */
    private array $deletedKeys = [];

    /** @var list<string> storage keys whose delete fails */
    private array $brokenKeys = [];

    /** @var array<string, list<string>> event name => space ids or attachment keys */
    private array $events = [];

    /** @var array<string, array<string, list<WikiAction>>> user identifier => space slug => actions */
    private array $grants = [];

    /** SQL fragment that makes the next statement fail (null: none). */
    private ?string $failOn = null;

    /** Statements of $failOn to let through before failing. */
    private int $failAfter = 0;

    /** What the failing statement throws: 'error' or 'transient'. */
    private string $failWith = 'error';

    /** @var array<string, Closure(): void> regex => runs once before the next statement it matches */
    private array $hooks = [];

    private WikiSpace $doomed;

    private WikiSpace $keep;

    private TestUserEntity $author;

    private WikiUserRef $ana;

    private WikiUserRef $bob;

    private WikiUserRef $eve;

    protected function setUp(): void
    {
        $test     = $this;
        $this->em = WikiTestDatabase::create(new class($test) extends AbstractLogger {
            public function __construct(private readonly WikiSpaceLifecycleTest $test)
            {
            }

            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $sql = $context['sql'] ?? null;
                if (is_string($sql)) {
                    $this->test->onStatement($sql);
                }
            }
        });
        $this->runner     = new WikiTransactionRunner($this->em, backoffBaseMilliseconds: 0);
        $this->dispatcher = new EventDispatcher();
        $this->logger     = new RecordingLogger();
        foreach ([WikiEvents::SPACE_RENAMED, WikiEvents::SPACE_ARCHIVED, WikiEvents::SPACE_UNARCHIVED] as $name) {
            $this->dispatcher->addListener($name, function (WikiSpaceLifecycleEvent $event) use ($name): void {
                $this->events[$name][] = $event->space->id . '@' . $event->space->version . '<' . $event->before->version;
            });
        }
        $this->dispatcher->addListener(WikiEvents::SPACE_PURGED, function (WikiSpacePurgedEvent $event): void {
            $this->events[WikiEvents::SPACE_PURGED][] = $event->space->id . ':' . $event->pages;
        });
        $this->dispatcher->addListener(WikiEvents::ATTACHMENT_DELETED, function (WikiAttachmentEvent $event): void {
            $this->events[WikiEvents::ATTACHMENT_DELETED][] = $event->attachment->getStorageKey();
        });
        $this->dispatcher->addListener(WikiEvents::PAGE_FILES_PURGED, function (WikiPageFilesPurgedEvent $event): void {
            foreach ($event->files as $file) {
                $this->events[WikiEvents::PAGE_FILES_PURGED][] = $file->url . ($event->truncated ? ' (truncated)' : '');
            }
        });

        $this->author = new TestUserEntity('u-author');
        $this->doomed = new WikiSpace('doomed', 'Doomed', WikiSpaceOwnerScope::Team, 'team-1');
        $this->keep   = new WikiSpace('keep', 'Keep', WikiSpaceOwnerScope::Team, 'team-1');
        $other        = new WikiSpace('elsewhere', 'Other tenant', WikiSpaceOwnerScope::Team, 'team-9');
        foreach ([$this->author, $this->doomed, $this->keep, $other] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();

        $this->ana    = WikiUserRefs::fromUser(new TestUser('u-ana', 'ana'));
        $this->bob    = WikiUserRefs::fromUser(new TestUser('u-bob', 'bob'));
        $this->eve    = WikiUserRefs::fromUser(new TestUser('u-eve', 'eve'));
        $all          = [WikiAction::View, WikiAction::Edit, WikiAction::Create, WikiAction::Manage, WikiAction::History];
        $this->grants = [
            'ana' => ['doomed' => $all, 'docs' => $all, 'keep' => $all],
            'bob' => ['doomed' => [WikiAction::View, WikiAction::Edit], 'keep' => [WikiAction::View, WikiAction::Edit]],
            'eve' => ['elsewhere' => $all],
        ];
    }

    /** Called by the SQL log before each statement: fails the chosen one. */
    public function onStatement(string $sql): void
    {
        foreach ($this->hooks as $pattern => $run) {
            if (preg_match($pattern, $sql) === 1) {
                unset($this->hooks[$pattern]);
                $run();
            }
        }
        if ($this->failOn === null || !str_starts_with($sql, $this->failOn)) {
            return;
        }
        if ($this->failAfter > 0) {
            --$this->failAfter;

            return;
        }
        $this->failOn = null;

        throw $this->failWith === 'transient' ? new TransientStorageFailure(2, 'simulated lock conflict') : new RuntimeException('simulated failure');
    }

    public function testRenameChangesNameAndSlugUnderTheOptimisticLock(): void
    {
        $lifecycle = $this->lifecycle();

        $renamed = $lifecycle->renameSpace($this->doomed->getId(), '  Docs  ', 'docs', $this->ana, 1);

        self::assertSame('Docs', $renamed->name);
        self::assertSame('docs', $renamed->slug);
        self::assertSame(2, $renamed->version);
        self::assertSame([$this->doomed->getId() . '@2<1'], $this->events[WikiEvents::SPACE_RENAMED]);
        $spaces = new DoctrineOrmWikiSpaceRepository($this->em);
        self::assertNull($spaces->findBySlug(WikiSpaceOwnerScope::Team, 'team-1', 'doomed'));
        self::assertSame($this->doomed->getId(), $spaces->findBySlug(WikiSpaceOwnerScope::Team, 'team-1', 'docs')?->getId());

        // The same values again: nothing written, no event, the version stays.
        self::assertSame(2, $lifecycle->renameSpace($this->doomed->getId(), 'Docs', 'docs', $this->ana)->version);
        self::assertCount(1, $this->events[WikiEvents::SPACE_RENAMED]);

        // A stale version: refused, nothing written.
        try {
            $lifecycle->renameSpace($this->doomed->getId(), 'Stale', null, $this->ana, 1);
            self::fail('Expected a version conflict.');
        } catch (WikiSpaceVersionConflictException $conflict) {
            self::assertSame(1, $conflict->expected);
            self::assertSame(2, $conflict->actual);
        }
        self::assertSame('Docs', $this->fresh($this->doomed)->getName());
        self::assertSame(3, $lifecycle->renameSpace($this->doomed->getId(), 'Docs v2', null, $this->ana)->version);
    }

    public function testRenameRefusesInvalidInputAndATakenSlug(): void
    {
        $lifecycle = $this->lifecycle();
        $id        = $this->doomed->getId();

        foreach ([[null, null], ['   ', null], [str_repeat('a', 256), null]] as [$name, $slug]) {
            try {
                $lifecycle->renameSpace($id, $name, $slug, $this->ana);
                self::fail('Expected an invalid argument.');
            } catch (InvalidArgumentException $exception) {
                self::assertNotInstanceOf(WikiInvalidSlugException::class, $exception);
            }
        }
        foreach (['Bad Slug', str_repeat('a', 121)] as $slug) {
            try {
                $lifecycle->renameSpace($id, null, $slug, $this->ana);
                self::fail('Expected an invalid slug.');
            } catch (WikiInvalidSlugException) {
            }
        }
        $this->expectException(WikiSlugTakenException::class);
        $lifecycle->renameSpace($id, null, 'keep', $this->ana);
    }

    public function testASlugTakenBetweenTheCheckAndTheCommitIsRefused(): void
    {
        // Another request takes the slug after the pre-check, before this rename's UPDATE: the unique key answers.
        $connection                            = WikiTestDatabase::isServer() ? WikiTestDatabase::connect()->getConnection() : $this->em->getConnection();
        $keep                                  = $this->keep->getId();
        $this->hooks['/^UPDATE wiki_spaces /'] = static function () use ($connection, $keep): void {
            $connection->executeStatement("UPDATE wiki_spaces SET slug = 'taken' WHERE id = ?", [$keep]);
        };

        try {
            $this->lifecycle()->renameSpace($this->doomed->getId(), null, 'taken', $this->ana);
            self::fail('Expected the slug to be taken.');
        } catch (WikiSlugTakenException $taken) {
            self::assertNotNull($taken->getPrevious());
        }
        self::assertSame('doomed', $this->fresh($this->doomed)->getSlug());
        self::assertTrue($this->em->isOpen());
    }

    public function testPermissionsAnswerAnotherTenantLikeAnUnknownSpace(): void
    {
        $lifecycle = $this->lifecycle();

        foreach ([[$this->doomed->getId(), $this->eve], ['00000000-0000-4000-8000-000000000000', $this->ana]] as [$id, $actor]) {
            try {
                $lifecycle->archiveSpace($id, $actor);
                self::fail('Expected not found.');
            } catch (WikiSpaceNotFoundException $exception) {
                self::assertSame('Space not found.', $exception->getMessage());
            }
        }
        try {
            $lifecycle->purgeSpace($this->doomed->getId(), $this->bob, true);
            self::fail('Expected access denied.');
        } catch (WikiSpaceAccessDeniedException $denied) {
            self::assertSame(WikiSpaceLifecycleAction::Purge, $denied->action);
        }
        self::assertFalse($this->fresh($this->doomed)->isArchived());

        // A listener may narrow (deny sticks) or widen the default.
        $this->dispatcher->addListener(WikiEvents::SPACE_ACCESS_CHECK, static function (WikiSpaceAccessCheckEvent $event): void {
            if ($event->actor->identifier === 'ana' && $event->action === WikiSpaceLifecycleAction::Archive) {
                self::assertTrue($event->isDefaultGranted());
                $event->deny();
                $event->grant();
            }
            if ($event->actor->identifier === 'bob' && $event->action === WikiSpaceLifecycleAction::Rename) {
                self::assertFalse($event->isDefaultGranted());
                $event->grant();
            }
        });
        try {
            $lifecycle->archiveSpace($this->doomed->getId(), $this->ana);
            self::fail('Expected access denied.');
        } catch (WikiSpaceAccessDeniedException) {
        }
        self::assertSame('Bob named it', $lifecycle->renameSpace($this->doomed->getId(), 'Bob named it', null, $this->bob)->name);
        // A trusted call (no person) checks nothing.
        self::assertTrue($lifecycle->archiveSpace($this->doomed->getId())->isArchived());
    }

    public function testAnArchivedSpaceIsReadOnlyHiddenAndReversible(): void
    {
        $lifecycle = $this->lifecycle();
        $page      = $this->page($this->doomed, 'roadmap', 'Roadmap');
        $search    = new WikiSearchService($this->em);
        self::assertCount(1, $search->searchTitles([$this->doomed], 'Roadmap', 10));

        $archived = $lifecycle->archiveSpace($this->doomed->getId(), $this->ana, 1);

        self::assertTrue($archived->isArchived());
        self::assertSame(2, $archived->version);
        self::assertSame([$this->doomed->getId() . '@2<1'], $this->events[WikiEvents::SPACE_ARCHIVED]);
        $space = $this->fresh($this->doomed);
        self::assertSame([], $search->searchTitles([$space], 'Roadmap', 10));
        self::assertSame([], $search->searchAcrossSpaces([$space], 'Roadmap'));
        $checker = $this->checker();
        self::assertFalse($checker->isGranted(new TestUser('u-bob', 'bob'), WikiAction::View, $space), 'Readers no longer see it.');
        self::assertTrue($checker->isGranted(new TestUser('u-ana', 'ana'), WikiAction::View, $space), 'Managers still do.');
        self::assertFalse($checker->isGranted(new TestUser('u-ana', 'ana'), WikiAction::Edit, $space));
        self::assertFalse($checker->isGranted(new TestUser('u-ana', 'ana'), WikiAction::Manage, $space, $page));
        self::assertTrue($checker->isGranted(new TestUser('u-ana', 'ana'), WikiAction::Manage, $space));

        // Read-only for the services too.
        $pages = LoadReadinessFactory::pages($this->em);
        $page  = $this->em->find(WikiPage::class, $page->getId());
        self::assertInstanceOf(WikiPage::class, $page);
        self::assertTrue($this->em->contains($page), 'managed before the save');
        self::assertTrue($this->em->contains($page->getSpace()), 'space managed before the save');
        $current0 = $page->getCurrentRevision();
        self::assertTrue(!$current0 instanceof WikiPageRevision || $this->em->contains($current0), 'revision managed before the save');
        foreach ([
            fn (): WikiPageRevision => $pages->saveRevision($this->reloaded($page), 'Roadmap', 'new', $this->author),
            fn (): WikiPageRevision => $pages->saveRevision($this->reloaded($page), 'Roadmap', 'new', $this->author, WikiPageRevision::FORMAT_HTML, 1),
            fn (): WikiPage => $pages->create($this->fresh($this->doomed), 'New', 'body', $this->author),
        ] as $write) {
            try {
                $write();
                self::fail('Expected the archived space refusal.');
            } catch (WikiPageArchivedException $refusal) {
                self::assertSame(WikiPageService::ARCHIVED_SPACE, $refusal->getMessage());
            }
        }

        // Without the transaction runner (the writes of 1.36) too.
        $page    = $this->reloaded($page);
        $plain   = new WikiPageService(new DoctrineOrmWikiPageRepository($this->em), new DoctrineOrmWikiPageRevisionRepository($this->em), new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher());
        $current = $page->getCurrentRevision();
        self::assertInstanceOf(WikiPageRevision::class, $current);
        foreach ([fn (): WikiPageRevision => $plain->saveRevision($page, 'Roadmap', 'new', $this->author), fn (): WikiPageRevision => $plain->restore($page, $current, $this->author)] as $write) {
            try {
                $write();
                self::fail('Expected the archived space refusal.');
            } catch (WikiPageArchivedException $refusal) {
                self::assertSame(WikiPageService::ARCHIVED_SPACE, $refusal->getMessage());
            }
        }

        // Archiving again writes nothing (a retry after a lost answer).
        self::assertSame(2, $lifecycle->archiveSpace($this->doomed->getId(), $this->ana, 1)->version);
        self::assertCount(1, $this->events[WikiEvents::SPACE_ARCHIVED]);

        $back = $lifecycle->unarchiveSpace($this->doomed->getId(), $this->ana, 2);
        self::assertFalse($back->isArchived());
        self::assertSame(3, $back->version);
        self::assertSame(3, $lifecycle->unarchiveSpace($this->doomed->getId(), $this->ana)->version);
        self::assertCount(1, $search->searchTitles([$this->fresh($this->doomed)], 'Roadmap', 10));

        $this->expectException(WikiSpaceVersionConflictException::class);
        $lifecycle->archiveSpace($this->doomed->getId(), $this->ana, 2);
    }

    public function testPurgeRemovesEveryRowOfTheSpaceAndNothingElse(): void
    {
        $this->fixtures($this->keep, 'k');
        $before = $this->counts();
        --$before[WikiSpace::class]; // the purged space's own row
        $this->fixtures($this->doomed, 'd');
        // A reference from a kept page to a doomed one goes with the doomed page.
        $keptPage   = $this->em->getRepository(WikiPage::class)->findOneBy(['slug' => 'k-root']);
        $doomedPage = $this->em->getRepository(WikiPage::class)->findOneBy(['slug' => 'd-root']);
        self::assertInstanceOf(WikiPage::class, $keptPage);
        self::assertInstanceOf(WikiPage::class, $doomedPage);
        $this->em->persist(new WikiPageReference($keptPage, $doomedPage->getId()));
        $this->em->flush();
        $hostRow = new TestUserEntity('u-host');
        $this->em->persist($hostRow);
        $this->em->flush();
        $lifecycle = $this->lifecycle(batchSize: 2);

        try {
            $lifecycle->purgeSpace($this->doomed->getId(), $this->ana);
            self::fail('Expected the archive requirement.');
        } catch (WikiSpaceStateException $state) {
            self::assertSame(WikiSpaceStateException::NOT_ARCHIVED, $state->reason);
        }

        $result = $lifecycle->purgeSpace($this->doomed->getId(), $this->ana, true);

        self::assertTrue($result->finished);
        self::assertSame(3, $result->pages);
        self::assertSame(3, $result->batches, 'Two pages per batch, then the space row.');
        self::assertSame($before, $this->counts(), 'Every row of the purged space is gone; the other spaces keep theirs.');
        self::assertSame(['d-a1', 'd-a2'], $this->sorted($this->deletedKeys));
        self::assertSame(['d-a1', 'd-a2'], $this->sorted($this->events[WikiEvents::ATTACHMENT_DELETED]));
        self::assertSame(['/files/d-1.png', '/files/d-2.png'], $this->sorted($this->events[WikiEvents::PAGE_FILES_PURGED]));
        self::assertSame([$this->doomed->getId() . ':3'], $this->events[WikiEvents::SPACE_PURGED]);
        self::assertTrue($this->em->isOpen());
        self::assertTrue($this->em->contains($hostRow), 'The host\'s rows stay managed.');
        self::assertFalse($this->em->contains($this->doomed), 'The purged rows are forgotten.');
        $this->em->flush();

        $this->expectException(WikiSpaceNotFoundException::class);
        $lifecycle->purgeSpace($this->doomed->getId(), $this->ana);
    }

    public function testAPurgeResumesAfterAFailureMidBatch(): void
    {
        $this->fixtures($this->keep, 'k');
        $before = $this->counts();
        --$before[WikiSpace::class]; // the purged space's own row
        $this->fixtures($this->doomed, 'd');
        $this->brokenKeys = ['d-a1'];
        $lifecycle        = $this->lifecycle(batchSize: 1, maxFiles: 1);
        $lifecycle->archiveSpace($this->doomed->getId(), $this->ana);

        // The second page batch fails on its last statement: the first batch stays committed, the second rolls back.
        $this->failOn    = 'DELETE FROM wiki_pages';
        $this->failAfter = 1;
        try {
            $lifecycle->purgeSpace($this->doomed->getId(), $this->ana);
            self::fail('Expected the simulated failure.');
        } catch (RuntimeException $failure) {
            self::assertSame('simulated failure', $failure->getMessage());
        }
        $space = $this->fresh($this->doomed);
        self::assertTrue($space->isArchived());
        self::assertNotNull($space->getPurgeStartedAt());
        self::assertSame(2, $this->pagesOf($space));

        // Being purged: only the purge goes on.
        foreach ([fn (): WikiSpaceSnapshot => $lifecycle->unarchiveSpace($space->getId(), $this->ana), fn (): WikiSpaceSnapshot => $lifecycle->renameSpace($space->getId(), 'New', null, $this->ana)] as $change) {
            try {
                $change();
                self::fail('Expected the purging refusal.');
            } catch (WikiSpaceStateException $state) {
                self::assertSame(WikiSpaceStateException::PURGING, $state->reason);
                self::assertSame('The space is being purged.', $state->getMessage());
            }
        }

        // A budget: one batch per call, then the end.
        $partial = $lifecycle->purgeSpace($space->getId(), $this->ana, false, 1);
        self::assertFalse($partial->finished);
        self::assertSame(1, $partial->pages);
        $rest = $lifecycle->purgeSpace($space->getId(), $this->ana, false, 0);
        self::assertSame(0, $rest->batches);
        $done = $lifecycle->purgeSpace($space->getId(), $this->ana);
        self::assertTrue($done->finished);
        self::assertSame($before, $this->counts());
        self::assertContains('warning: Wiki space purge: an attachment file could not be deleted.', $this->logger->lines());
        self::assertContains('/files/d-1.png (truncated)', $this->events[WikiEvents::PAGE_FILES_PURGED]);
    }

    public function testALockConflictAfterPartOfThePurgeCommittedSaysSo(): void
    {
        $this->fixtures($this->doomed, 'd');
        $lifecycle = $this->lifecycle(batchSize: 1);
        $lifecycle->archiveSpace($this->doomed->getId(), $this->ana);
        $this->failOn    = 'DELETE FROM wiki_pages';
        $this->failAfter = 1;
        $this->failWith  = 'transient';

        try {
            $lifecycle->purgeSpace($this->doomed->getId(), $this->ana);
            self::fail('Expected the conflict.');
        } catch (ConcurrencyConflict $conflict) {
            self::assertTrue($conflict->committed, 'CONFLICT_002: read again before calling again.');
        }

        // Resumed by a later call that meets a conflict before committing anything: retry-safe.
        $this->failOn    = 'DELETE FROM wiki_pages';
        $this->failAfter = 0;
        try {
            $lifecycle->purgeSpace($this->doomed->getId(), $this->ana);
            self::fail('Expected the conflict.');
        } catch (TransientStorageFailure $conflict) {
            self::assertFalse($conflict instanceof ConcurrencyConflict && $conflict->committed, 'CONFLICT_001: nothing of this call committed.');
        }
        self::assertTrue($lifecycle->purgeSpace($this->doomed->getId(), $this->ana)->finished);
    }

    public function testALockedSpaceRowAnswersARetrySafeConflict(): void
    {
        if (!WikiTestDatabase::isServer()) {
            self::markTestSkipped('Needs MySQL, MariaDB or PostgreSQL in ' . WikiTestDatabase::ENV . '.');
        }
        $lifecycle = $this->lifecycle();
        $other     = WikiTestDatabase::connect()->getConnection();
        $other->beginTransaction();
        $other->fetchOne('SELECT id FROM wiki_spaces WHERE id = ? FOR UPDATE', [$this->doomed->getId()]);
        $this->shortLockTimeout($this->em->getConnection());

        try {
            foreach ([fn (): WikiSpaceSnapshot => $lifecycle->archiveSpace($this->doomed->getId(), $this->ana), fn (): WikiSpaceSnapshot => $lifecycle->renameSpace($this->doomed->getId(), 'X', null, $this->ana), fn (): WikiSpacePurgeResult => $lifecycle->purgeSpace($this->doomed->getId(), $this->ana, true)] as $change) {
                try {
                    $change();
                    self::fail('Expected the lock conflict.');
                } catch (TransientStorageFailure $conflict) {
                    self::assertFalse($conflict instanceof ConcurrencyConflict && $conflict->committed, 'CONFLICT_001: nothing committed.');
                }
            }
        } finally {
            $other->rollBack();
            $other->close();
        }
        self::assertTrue($this->em->isOpen());
        $space = $this->fresh($this->doomed);
        self::assertFalse($space->isArchived());
        self::assertSame('Doomed', $space->getName());
        self::assertSame(1, $space->getVersion());
    }

    public function testASpaceGoneBeforeItsLockAnswersNotFound(): void
    {
        $empty = new WikiSpace('empty', 'Empty', WikiSpaceOwnerScope::Team, 'team-1');
        $this->em->persist($empty);
        $this->em->flush();
        $connection = WikiTestDatabase::isServer() ? WikiTestDatabase::connect()->getConnection() : $this->em->getConnection();
        $id         = $empty->getId();
        // Another request deletes the space between the permission check and the lock.
        $this->hooks['/^SELECT \\S+ AS \\S+ FROM wiki_spaces \\S+ WHERE/'] = static function () use ($connection, $id): void {
            $connection->executeStatement('DELETE FROM wiki_spaces WHERE id = ?', [$id]);
        };

        try {
            $this->lifecycle()->archiveSpace($id); // a trusted call: only the lock can miss it
            self::fail('Expected not found.');
        } catch (WikiSpaceNotFoundException) {
        }
        self::assertSame([], $this->hooks, 'The space went between the check and the lock.');
        self::assertTrue($this->purger()->batch('00000000-0000-4000-8000-000000000000', 10)->finished, 'A purge batch of a space that is gone has nothing to do.');
    }

    public function testEventsWaitForTheDeferralScope(): void
    {
        $deferral  = new WikiEventDeferral();
        $lifecycle = $this->lifecycle(deferral: $deferral);

        $deferral->run(function () use ($lifecycle): void {
            $lifecycle->archiveSpace($this->doomed->getId(), $this->ana);
            self::assertArrayNotHasKey(WikiEvents::SPACE_ARCHIVED, $this->events);
        });

        self::assertCount(1, $this->events[WikiEvents::SPACE_ARCHIVED]);
    }

    private function lifecycle(int $batchSize = 100, ?WikiEventDeferral $deferral = null, int $maxFiles = 50): WikiSpaceLifecycle
    {
        $storage = new class($this) implements WikiAttachmentStorageInterface {
            public function __construct(private readonly WikiSpaceLifecycleTest $test)
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
                $this->test->deleted($key);
            }

            public function keys(): iterable
            {
                return [];
            }
        };

        return new WikiSpaceLifecycle(
            $this->runner,
            new DoctrineOrmWikiSpaceRepository($this->em),
            $this->purger($maxFiles),
            new WikiSlugger(),
            $this->checker(),
            $this->dispatcher,
            $deferral,
            $storage,
            $this->logger,
            $batchSize,
        );
    }

    /**
     * @param list<string> $values
     *
     * @return list<string> sorted, without duplicates
     */
    private function sorted(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    private function purger(int $maxFiles = 50): WikiSpacePurger
    {
        return new WikiSpacePurger($this->runner, [
            'comments'         => true,
            'trash'            => true,
            'history_timeline' => true,
            'collaboration'    => true,
            'favourites'       => true,
            'recents'          => true,
            'backlinks'        => true,
            'api_idempotency'  => true,
        ], new WikiPageFileExtractor(), new DoctrineOrmWikiPageRevisionRepository($this->em), $maxFiles);
    }

    /** Called by the test storage. */
    public function deleted(string $key): void
    {
        if (in_array($key, $this->brokenKeys, true)) {
            throw new RuntimeException('disk full');
        }
        $this->deletedKeys[] = $key;
    }

    private function checker(): WikiSpaceAccessCheckerInterface
    {
        $grants = $this->grants;

        return new WikiArchivedSpaceAccessChecker(new class($grants) implements WikiSpaceAccessCheckerInterface {
            /** @param array<string, array<string, list<WikiAction>>> $grants */
            public function __construct(private readonly array $grants)
            {
            }

            public function isGranted(UserInterface $user, WikiAction $action, WikiSpace $space, ?WikiPage $page = null): bool
            {
                return in_array($action, $this->grants[$user->getUserIdentifier()][$space->getSlug()] ?? [], true);
            }
        });
    }

    /**
     * A tree of three pages (root, child, grandchild) with a row in every wiki table.
     */
    private function fixtures(WikiSpace $space, string $prefix): void
    {
        $root  = new WikiPage($space, $prefix . '-root', 'Root ' . $prefix);
        $child = new WikiPage($space, $prefix . '-child', 'Child ' . $prefix, $root);
        $leaf  = new WikiPage($space, $prefix . '-leaf', 'Leaf ' . $prefix, $child);
        $this->em->persist($root);
        $this->em->persist($child);
        $this->em->persist($leaf);
        foreach ([$root, $child, $leaf] as $number => $page) {
            $revision = new WikiPageRevision($page, 1, '<p>x</p>', $this->author, '![a](/files/' . $prefix . '-1.png) ![b](/files/' . $prefix . '-' . ($number === 0 ? '2' : '1') . '.png)');
            $this->em->persist($revision);
            $page->setCurrentRevision($revision);
        }
        $revision = $root->getCurrentRevision();
        self::assertInstanceOf(WikiPageRevision::class, $revision);
        $thread  = new WikiCommentThread($root, $this->author);
        $comment = new WikiComment($thread, $this->author, 'Hello');
        $this->em->persist(new WikiRevisionLabel($revision, 'v1'));
        $this->em->persist($thread);
        $this->em->persist($comment);
        $this->em->persist(new WikiCommentReaction($comment, $this->author, '+1'));
        $this->em->persist(new WikiAttachment($root, $space, 'a.txt', $prefix . '-a1', 'text/plain', 1, str_repeat('0', 64), $this->author));
        $this->em->persist(new WikiAttachment($leaf, $space, 'b.txt', $prefix . '-a2', 'text/plain', 1, str_repeat('0', 64), $this->author));
        $this->em->persist(new WikiPageFavourite($root, 'u-ana'));
        $this->em->persist(new WikiPageVisit($child, 'u-ana', new DateTimeImmutable()));
        $this->em->persist(new WikiPageReference($root, $child->getId()));
        $this->em->persist(new WikiPageLink($root, 'task', $prefix . '-T1', $this->author));
        $this->em->persist(new WikiTrashEntry($leaf, $prefix . '-batch', true, false, new DateTimeImmutable()));
        $this->em->persist(new WikiCollabDocument($child->getId()));
        $this->em->persist(new WikiCollabUpdate($child->getId(), 'update', 'AAA'));
        $this->em->persist(new WikiApiIdempotencyRecord('scope', str_repeat($prefix, 64), 'f', new DateTimeImmutable(), new DateTimeImmutable('+1 day'), 201, '{}', $space->getSlug(), $leaf->getId()));
        $this->em->flush();
    }

    /**
     * @return array<string, int> rows per wiki table
     */
    private function counts(): array
    {
        $counts = [];
        foreach ([WikiSpace::class, WikiPage::class, WikiPageRevision::class, WikiRevisionLabel::class, WikiCommentThread::class, WikiComment::class, WikiCommentReaction::class, WikiAttachment::class, WikiPageFavourite::class, WikiPageVisit::class, WikiPageReference::class, WikiPageLink::class, WikiTrashEntry::class, WikiCollabDocument::class, WikiCollabUpdate::class, WikiApiIdempotencyRecord::class] as $class) {
            $counts[$class] = (int) $this->em->createQuery('SELECT COUNT(x) FROM ' . $class . ' x')->getSingleScalarResult();
        }
        self::assertSame(16, count(array_keys($counts)));

        return $counts;
    }

    private function page(WikiSpace $space, string $slug, string $title): WikiPage
    {
        $page     = new WikiPage($space, $slug, $title);
        $revision = new WikiPageRevision($page, 1, '<p>' . $title . '</p>', $this->author);
        $page->setCurrentRevision($revision);
        $this->em->persist($page);
        $this->em->persist($revision);
        $this->em->flush();

        return $page;
    }

    /** The page as the manager holds it now (a refused unit forgets the rows it locked). */
    private function reloaded(WikiPage $page): WikiPage
    {
        $found = $this->em->find(WikiPage::class, $page->getId());
        self::assertInstanceOf(WikiPage::class, $found);

        return $found;
    }

    private function pagesOf(WikiSpace $space): int
    {
        return (int) $this->em->createQuery('SELECT COUNT(p) FROM ' . WikiPage::class . ' p WHERE p.space = :space')->setParameter('space', $space->getId())->getSingleScalarResult();
    }

    private function fresh(WikiSpace $space): WikiSpace
    {
        $found = (new DoctrineOrmWikiSpaceRepository($this->em))->findById($space->getId());
        self::assertInstanceOf(WikiSpace::class, $found);

        return $found;
    }

    private function shortLockTimeout(Connection $connection): void
    {
        $connection->executeStatement(str_starts_with((string) WikiTestDatabase::url(), 'mysql')
            ? 'SET SESSION innodb_lock_wait_timeout = 1'
            : "SET lock_timeout = '1s'");
    }
}
