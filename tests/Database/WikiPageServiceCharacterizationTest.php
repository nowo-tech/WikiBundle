<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiPageCreatedEvent;
use Nowo\WikiBundle\Event\WikiPageRestoredEvent;
use Nowo\WikiBundle\Event\WikiPageUnarchivedEvent;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiPageArchivedEvent;
use Nowo\WikiBundle\Service\WikiPageSavedEvent;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiRevisionCollisionException;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function array_map;

/**
 * Spec 218 S0: what {@see WikiPageService} writes and dispatches today, pinned on SQLite and, in CI, on MySQL and
 * PostgreSQL (`WIKI_TEST_DATABASE_URL`). The base-revision work (S1) must leave every one of these unchanged when it is
 * not asked for.
 */
final class WikiPageServiceCharacterizationTest extends TestCase
{
    private EntityManager $em;

    private TestUserEntity $alice;

    private TestUserEntity $bob;

    private WikiSpace $space;

    /** @var list<array{0: string, 1: object}> */
    private array $events = [];

    private WikiPageService $service;

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

        $dispatcher = new EventDispatcher();
        foreach ([WikiEvents::PAGE_SAVED, WikiEvents::PAGE_CREATED, WikiEvents::PAGE_RESTORED, WikiEvents::PAGE_ARCHIVED, WikiEvents::PAGE_UNARCHIVED] as $name) {
            $dispatcher->addListener($name, function (object $event) use ($name): void {
                $this->events[] = [$name, $event];
            });
        }

        $this->service = new WikiPageService(
            new DoctrineOrmWikiPageRepository($this->em),
            new DoctrineOrmWikiPageRevisionRepository($this->em),
            new WikiSlugger(),
            new WikiHtmlSanitizer(),
            $dispatcher,
        );
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
    }

    public function testCreateStoresRevisionOneAndDispatchesSavedThenCreated(): void
    {
        $page = $this->service->create($this->space, 'Runbook', "# Steps\r\n\r\nRestart.", $this->alice, null, null, WikiPageRevision::FORMAT_MARKDOWN);

        self::assertSame('runbook', $page->getSlug());
        self::assertSame([WikiEvents::PAGE_SAVED, WikiEvents::PAGE_CREATED], $this->names());
        $saved = $this->events[0][1];
        self::assertInstanceOf(WikiPageSavedEvent::class, $saved);
        self::assertNull($saved->previousRevision);
        self::assertSame($this->alice, $saved->actor);
        self::assertFalse($saved->conversion);
        self::assertInstanceOf(WikiPageCreatedEvent::class, $this->events[1][1]);

        $this->em->clear();
        $stored = $this->em->find(WikiPage::class, $page->getId());
        self::assertInstanceOf(WikiPage::class, $stored);
        $revision = $stored->getCurrentRevision();
        self::assertInstanceOf(WikiPageRevision::class, $revision);
        self::assertSame(1, $revision->getRevisionNumber());
        self::assertSame("# Steps\n\nRestart.", $revision->getContentMarkdown());
        self::assertSame('u-alice', $revision->getAuthor()->getUserIdentifier());
    }

    public function testCreateRefusesATakenSlug(): void
    {
        $this->service->create($this->space, 'Runbook', 'a', $this->alice);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Page slug already exists in this space.');
        $this->service->create($this->space, 'Other', 'b', $this->alice, null, 'runbook');
    }

    public function testSaveRevisionAppendsNumbersInOrderAndUpdatesTheTitle(): void
    {
        $page   = $this->service->create($this->space, 'Runbook', 'one', $this->alice, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $first  = $page->getCurrentRevision();
        $second = $this->service->saveRevision($page, 'Runbook v2', 'two', $this->bob, WikiPageRevision::FORMAT_MARKDOWN);
        // The same body again still appends a revision: last write wins, no comparison (218 FR-023 is API-only).
        $third = $this->service->saveRevision($page, 'Runbook v2', 'two', $this->bob, WikiPageRevision::FORMAT_MARKDOWN);

        self::assertSame([2, 3], [$second->getRevisionNumber(), $third->getRevisionNumber()]);
        self::assertSame('Runbook v2', $page->getTitle());
        self::assertSame($third, $page->getCurrentRevision());
        self::assertSame([WikiEvents::PAGE_SAVED, WikiEvents::PAGE_CREATED, WikiEvents::PAGE_SAVED, WikiEvents::PAGE_SAVED], $this->names());
        $event = $this->events[2][1];
        self::assertInstanceOf(WikiPageSavedEvent::class, $event);
        self::assertSame($first, $event->previousRevision);
        self::assertSame($this->bob, $event->actor);

        $this->em->clear();
        $numbers = $this->em->createQuery('SELECT r.revisionNumber FROM ' . WikiPageRevision::class . ' r WHERE r.page = :p ORDER BY r.revisionNumber')
            ->setParameter('p', $page->getId())
            ->getSingleColumnResult();
        self::assertSame([1, 2, 3], array_map(intval(...), $numbers));
    }

    public function testSaveRevisionOnAnArchivedPageIsRefusedAndWritesNothing(): void
    {
        $page = $this->service->create($this->space, 'Runbook', 'one', $this->alice);
        $this->service->archive($page, $this->alice);

        try {
            $this->service->saveRevision($page, 'x', 'y', $this->alice);
            self::fail('An archived page must not be edited.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Archived pages cannot be edited.', $exception->getMessage());
        }
        self::assertSame(1, $page->getCurrentRevision()?->getRevisionNumber());
    }

    public function testADuplicateRevisionNumberIsACollisionAndNothingIsWritten(): void
    {
        $page = $this->service->create($this->space, 'Runbook', 'one', $this->alice);
        // Another writer stored number 2 behind this entity manager's back: the unique index refuses ours.
        $this->em->getConnection()->insert('wiki_page_revisions', [
            'id'              => '00000000-0000-4000-8000-000000000002',
            'page_id'         => $page->getId(),
            'revision_number' => 2,
            'content_html'    => '<p>theirs</p>',
            'author_id'       => 'u-bob',
            'created_at'      => '2026-11-08 10:00:00',
            'format'          => WikiPageRevision::FORMAT_HTML,
        ]);
        $inner      = new DoctrineOrmWikiPageRevisionRepository($this->em);
        $repository = new class($inner) implements WikiPageRevisionRepositoryInterface {
            public function __construct(private readonly WikiPageRevisionRepositoryInterface $inner)
            {
            }

            public function save(WikiPageRevision $revision): void
            {
                $this->inner->save($revision);
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
                return 2;
            }
        };
        $service = new WikiPageService(new DoctrineOrmWikiPageRepository($this->em), $repository, new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher());

        $this->expectException(WikiRevisionCollisionException::class);
        $service->saveRevision($page, 'Runbook', 'mine', $this->alice);
    }

    public function testRestoreAppendsTheOldBodyKeepsTheTitleAndDispatchesSavedThenRestored(): void
    {
        $page  = $this->service->create($this->space, 'Runbook', 'one', $this->alice, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $first = $page->getCurrentRevision();
        self::assertInstanceOf(WikiPageRevision::class, $first);
        $this->service->saveRevision($page, 'Renamed', 'two', $this->alice, WikiPageRevision::FORMAT_MARKDOWN);
        $this->events = [];

        $restored = $this->service->restore($page, $first, $this->bob);

        self::assertSame(3, $restored->getRevisionNumber());
        self::assertSame('one', $restored->getContentMarkdown());
        self::assertSame('Renamed', $page->getTitle());
        self::assertSame([WikiEvents::PAGE_SAVED, WikiEvents::PAGE_RESTORED], $this->names());
        $event = $this->events[1][1];
        self::assertInstanceOf(WikiPageRestoredEvent::class, $event);
        self::assertSame($first, $event->restoredFrom);
        self::assertSame($restored, $event->revision);
    }

    public function testRestoreRefusesARevisionOfAnotherPageAndAnArchivedPage(): void
    {
        $page  = $this->service->create($this->space, 'One', 'one', $this->alice);
        $other = $this->service->create($this->space, 'Two', 'two', $this->alice);
        $from  = $other->getCurrentRevision();
        self::assertInstanceOf(WikiPageRevision::class, $from);

        try {
            $this->service->restore($page, $from, $this->alice);
            self::fail('A revision of another page must be refused.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('The revision belongs to another page.', $exception->getMessage());
        }

        $this->service->archive($page, $this->alice);
        $this->expectExceptionMessage('Archived pages cannot be restored.');
        $this->service->restore($page, $from, $this->alice);
    }

    public function testArchiveAndUnarchiveAreIdempotentAndDispatchOnce(): void
    {
        $page         = $this->service->create($this->space, 'Runbook', 'one', $this->alice);
        $this->events = [];

        $this->service->archive($page, $this->bob);
        $this->service->archive($page, $this->bob);
        $this->service->unarchive($page, $this->bob);
        $this->service->unarchive($page, $this->bob);

        self::assertSame([WikiEvents::PAGE_ARCHIVED, WikiEvents::PAGE_UNARCHIVED], $this->names());
        self::assertInstanceOf(WikiPageArchivedEvent::class, $this->events[0][1]);
        self::assertInstanceOf(WikiPageUnarchivedEvent::class, $this->events[1][1]);
        self::assertFalse($page->isArchived());
    }

    /** @return list<string> */
    private function names(): array
    {
        return array_map(static fn (array $entry): string => $entry[0], $this->events);
    }
}
