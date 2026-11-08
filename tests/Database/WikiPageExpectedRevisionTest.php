<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use LogicException;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Repository\WikiPageLockInterface;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiRevisionCollisionException;
use Nowo\WikiBundle\Service\WikiRevisionConflictException;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\LockingTestPageRepository;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Throwable;

/**
 * 1.24 (spec 218 FR-022, FR-023, FR-029): a save or a restore from an expected revision, compared with the page row
 * locked. Another writer is simulated behind the entity manager's back (plain SQL), so the page in memory is stale and
 * only the read under the lock can see the newer revision.
 */
final class WikiPageExpectedRevisionTest extends TestCase
{
    private EntityManager $em;

    private TestUserEntity $alice;

    private TestUserEntity $bob;

    private WikiSpace $space;

    private WikiPageService $service;

    /** @var list<string> */
    private array $events = [];

    protected function setUp(): void
    {
        $this->em    = WikiTestDatabase::create();
        $this->alice = new TestUserEntity('u-alice');
        $this->bob   = new TestUserEntity('u-bob');
        $this->space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->em->persist($this->alice);
        $this->em->persist($this->bob);
        $this->em->persist($this->space);
        $this->em->flush();
        $this->service = $this->service(new DoctrineOrmWikiPageRepository($this->em));
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
    }

    public function testASaveFromTheCurrentRevisionAppendsTheNextOne(): void
    {
        $page = $this->page();

        $revision = $this->service->saveRevision($page, 'Runbook', 'two', $this->bob, WikiPageRevision::FORMAT_MARKDOWN, 1);

        self::assertSame(2, $revision->getRevisionNumber());
        self::assertSame([WikiEvents::PAGE_SAVED], $this->events);
        self::assertSame(2, $this->storedNumber($page));
    }

    public function testAStaleSaveIsAConflictAndWritesNothing(): void
    {
        $page = $this->page();
        $this->writeBehindTheEntityManager($page, 2, 'u-bob');

        try {
            $this->service->saveRevision($page, 'Mine', 'mine', $this->alice, WikiPageRevision::FORMAT_MARKDOWN, 1);
            self::fail('A stale base revision must be refused.');
        } catch (WikiRevisionConflictException $conflict) {
            self::assertSame(1, $conflict->expectedRevision);
            self::assertSame(2, $conflict->currentRevision);
            self::assertSame('u-bob', $conflict->updatedBy?->getUserIdentifier());
            self::assertNotNull($conflict->updatedAt);
            self::assertSame('The page changed since the revision this save started from.', $conflict->getMessage());
        }

        self::assertSame([], $this->events);
        self::assertSame(2, $this->storedNumber($page));
        self::assertSame('Runbook', $this->storedTitle($page));
        self::assertFalse($this->em->getConnection()->isTransactionActive());
    }

    public function testAnExpectedRevisionAheadOfThePageIsAConflictToo(): void
    {
        $page = $this->page();

        $this->expectException(WikiRevisionConflictException::class);
        $this->service->saveRevision($page, 'Runbook', 'x', $this->alice, WikiPageRevision::FORMAT_MARKDOWN, 7);
    }

    public function testAPageWithoutRevisionIsAtRevisionZero(): void
    {
        $page = new WikiPage($this->space, 'empty', 'Empty');
        $this->em->persist($page);
        $this->em->flush();

        $revision = $this->service->saveRevision($page, 'Empty', 'first', $this->alice, WikiPageRevision::FORMAT_MARKDOWN, 0);

        self::assertSame(1, $revision->getRevisionNumber());
    }

    public function testAnUnchangedSaveReturnsTheCurrentRevisionAndWritesNothing(): void
    {
        $page    = $this->page("one\r\n");
        $current = $page->getCurrentRevision();

        $same = $this->service->saveRevision($page, 'Runbook', "one\n\n", $this->alice, WikiPageRevision::FORMAT_MARKDOWN, 1);

        self::assertSame($current, $same);
        self::assertSame([], $this->events);
        self::assertSame(1, $this->storedNumber($page));

        // A new title is a change.
        self::assertSame(2, $this->service->saveRevision($page, 'Renamed', 'one', $this->alice, WikiPageRevision::FORMAT_MARKDOWN, 1)->getRevisionNumber());
    }

    public function testUnchangedHtmlIsComparedAfterSanitizingAndAFormatChangeIsAChange(): void
    {
        $page = $this->service->create($this->space, 'Html', '<p>Body</p>', $this->alice);

        self::assertSame(1, $this->service->saveRevision($page, 'Html', '<p>Body</p><script>x</script>', $this->alice, WikiPageRevision::FORMAT_HTML, 1)->getRevisionNumber());
        self::assertSame(2, $this->service->saveRevision($page, 'Html', 'Body', $this->alice, WikiPageRevision::FORMAT_MARKDOWN, 1)->getRevisionNumber());
        // An HTML body over a Markdown revision is a change as well.
        self::assertSame(3, $this->service->saveRevision($page, 'Html', '<p>Body</p>', $this->alice, WikiPageRevision::FORMAT_HTML, 2)->getRevisionNumber());
    }

    public function testAPageArchivedMeanwhileIsRefusedUnderTheLock(): void
    {
        $page = $this->page();
        $this->em->getConnection()->executeStatement('UPDATE wiki_pages SET archived_at = ? WHERE id = ?', ['2026-11-08 10:00:00', $page->getId()]);

        try {
            $this->service->saveRevision($page, 'Runbook', 'x', $this->alice, WikiPageRevision::FORMAT_MARKDOWN, 1);
            self::fail('An archived page must not be edited.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Archived pages cannot be edited.', $exception->getMessage());
        }
        self::assertFalse($this->em->getConnection()->isTransactionActive());

        $from = $page->getCurrentRevision();
        self::assertInstanceOf(WikiPageRevision::class, $from);
        $this->expectExceptionMessage('Archived pages cannot be restored.');
        $this->service->restore($page, $from, $this->alice, 1);
    }

    public function testRestoreFromTheCurrentRevisionAppendsAndAStaleOneIsAConflict(): void
    {
        $page  = $this->page();
        $first = $page->getCurrentRevision();
        self::assertInstanceOf(WikiPageRevision::class, $first);
        $this->service->saveRevision($page, 'Runbook', 'two', $this->alice, WikiPageRevision::FORMAT_MARKDOWN);
        $this->events = [];

        $restored = $this->service->restore($page, $first, $this->bob, 2);
        self::assertSame(3, $restored->getRevisionNumber());
        self::assertSame('one', $restored->getContentMarkdown());
        self::assertSame([WikiEvents::PAGE_SAVED, WikiEvents::PAGE_RESTORED], $this->events);

        $this->events = [];
        try {
            $this->service->restore($page, $first, $this->bob, 2);
            self::fail('A stale restore must be refused.');
        } catch (WikiRevisionConflictException $conflict) {
            self::assertSame(3, $conflict->currentRevision);
        }
        self::assertSame([], $this->events);
    }

    public function testRestoreChecksTheRevisionsPageBeforeLocking(): void
    {
        $page  = $this->page();
        $other = $this->service->create($this->space, 'Other', 'x', $this->alice);
        $from  = $other->getCurrentRevision();
        self::assertInstanceOf(WikiPageRevision::class, $from);

        $this->expectExceptionMessage('The revision belongs to another page.');
        $this->service->restore($page, $from, $this->alice, 1);
    }

    public function testAnExpectedRevisionNeedsALockingRepository(): void
    {
        $page    = $this->page();
        $service = $this->service($this->plainRepository());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('An expected revision needs a page repository that implements ' . WikiPageLockInterface::class . '.');
        $service->saveRevision($page, 'Runbook', 'x', $this->alice, WikiPageRevision::FORMAT_MARKDOWN, 1);
    }

    public function testALockThatCannotBeTakenIsACollision(): void
    {
        $page    = $this->page();
        $service = $this->service(new LockingTestPageRepository(new DoctrineOrmWikiPageRepository($this->em), beginFails: $this->retryable()));

        try {
            $service->saveRevision($page, 'Runbook', 'x', $this->alice, WikiPageRevision::FORMAT_MARKDOWN, 1);
            self::fail('A lock wait failure must be a collision.');
        } catch (WikiRevisionCollisionException $collision) {
            self::assertSame('The page could not be locked: another save holds it.', $collision->getMessage());
            self::assertInstanceOf(RetryableException::class, $collision->getPrevious());
        }
    }

    public function testARetryableFailureUnderTheLockRollsBackAndIsACollision(): void
    {
        $page       = $this->page();
        $repository = new LockingTestPageRepository(new DoctrineOrmWikiPageRepository($this->em), commitFails: $this->retryable());
        $service    = $this->service($repository);

        try {
            $service->saveRevision($page, 'Runbook', 'x', $this->alice, WikiPageRevision::FORMAT_MARKDOWN, 1);
            self::fail('A deadlock under the lock must be a collision.');
        } catch (WikiRevisionCollisionException $collision) {
            self::assertSame('The revision could not be written: another save got there first.', $collision->getMessage());
        }
        self::assertSame(1, $repository->rollbacks);
        self::assertSame([], $this->events);
    }

    public function testAnyOtherFailureUnderTheLockRollsBackAndIsRethrown(): void
    {
        $page       = $this->page();
        $failure    = new RuntimeException('disk full');
        $repository = new LockingTestPageRepository(new DoctrineOrmWikiPageRepository($this->em), commitFails: $failure);

        try {
            $this->service($repository)->saveRevision($page, 'Runbook', 'x', $this->alice, WikiPageRevision::FORMAT_MARKDOWN, 1);
            self::fail('The failure must reach the caller.');
        } catch (RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }
        self::assertSame(1, $repository->rollbacks);
    }

    public function testTheRepositoryRollsBackWhenThePageCannotBeLocked(): void
    {
        $repository = new DoctrineOrmWikiPageRepository($this->em);
        $detached   = new WikiPage($this->space, 'never-saved', 'Never saved');

        try {
            $repository->beginPageTransaction($detached);
            self::fail('A page the entity manager does not manage cannot be locked.');
        } catch (Throwable) {
            self::assertFalse($this->em->getConnection()->isTransactionActive());
        }
    }

    private function page(string $body = 'one'): WikiPage
    {
        $page         = $this->service->create($this->space, 'Runbook', $body, $this->alice, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $this->events = [];

        return $page;
    }

    private function service(WikiPageRepositoryInterface $pages): WikiPageService
    {
        $dispatcher = new EventDispatcher();
        foreach ([WikiEvents::PAGE_SAVED, WikiEvents::PAGE_CREATED, WikiEvents::PAGE_RESTORED] as $name) {
            $dispatcher->addListener($name, function () use ($name): void {
                $this->events[] = $name;
            });
        }

        return new WikiPageService($pages, new DoctrineOrmWikiPageRevisionRepository($this->em), new WikiSlugger(), new WikiHtmlSanitizer(), $dispatcher);
    }

    /** Another writer's revision, committed outside this entity manager. */
    private function writeBehindTheEntityManager(WikiPage $page, int $number, string $author): void
    {
        $id         = '00000000-0000-4000-8000-00000000000' . $number;
        $connection = $this->em->getConnection();
        $connection->insert('wiki_page_revisions', [
            'id'               => $id,
            'page_id'          => $page->getId(),
            'revision_number'  => $number,
            'content_html'     => '<p>theirs</p>',
            'content_markdown' => 'theirs',
            'author_id'        => $author,
            'created_at'       => '2026-11-08 10:00:00',
            'format'           => WikiPageRevision::FORMAT_MARKDOWN,
        ]);
        $connection->executeStatement('UPDATE wiki_pages SET current_revision_id = ? WHERE id = ?', [$id, $page->getId()]);
    }

    private function storedNumber(WikiPage $page): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT MAX(revision_number) FROM wiki_page_revisions WHERE page_id = ?', [$page->getId()]);
    }

    private function storedTitle(WikiPage $page): string
    {
        return (string) $this->em->getConnection()->fetchOne('SELECT title FROM wiki_pages WHERE id = ?', [$page->getId()]);
    }

    private function plainRepository(): WikiPageRepositoryInterface
    {
        $inner = new DoctrineOrmWikiPageRepository($this->em);

        return new class($inner) implements WikiPageRepositoryInterface {
            public function __construct(private readonly WikiPageRepositoryInterface $inner)
            {
            }

            public function save(WikiPage $page): void
            {
                $this->inner->save($page);
            }

            public function findById(string $id): ?WikiPage
            {
                return $this->inner->findById($id);
            }

            public function findBySlug(WikiSpace $space, string $slug): ?WikiPage
            {
                return $this->inner->findBySlug($space, $slug);
            }

            public function findActiveBySpace(WikiSpace $space, ?int $limit = null): array
            {
                return $this->inner->findActiveBySpace($space, $limit);
            }

            public function findArchivedBySpace(WikiSpace $space, int $limit = 100): array
            {
                return $this->inner->findArchivedBySpace($space, $limit);
            }

            public function countBySpaceAndSlug(WikiSpace $space, string $slug, ?string $excludePageId = null): int
            {
                return $this->inner->countBySpaceAndSlug($space, $slug, $excludePageId);
            }
        };
    }

    private function retryable(): RetryableException
    {
        return new class('lock wait timeout') extends RuntimeException implements RetryableException {
        };
    }
}
