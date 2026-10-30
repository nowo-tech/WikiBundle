<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Service;

use InvalidArgumentException;
use Nowo\WikiBundle\Attachment\WikiAttachmentReferenceFilter;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiPageCreatedEvent;
use Nowo\WikiBundle\Event\WikiPageRestoredEvent;
use Nowo\WikiBundle\Event\WikiPageUnarchivedEvent;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiPageArchivedEvent;
use Nowo\WikiBundle\Service\WikiPageSavedEvent;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\InMemoryAttachmentRepository;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class WikiPageServiceRestoreTest extends TestCase
{
    /** @var list<string> */
    private array $events = [];

    public function testCreateDispatchesSavedThenCreatedWithTheActor(): void
    {
        $user = new TestUser();
        $page = $this->service()->create(new WikiSpace('d', 'D', WikiSpaceOwnerScope::Team, 't'), 'Hello', '<p>x</p>', $user);

        self::assertSame(['saved:1:none', 'created'], $this->events);
        self::assertSame('hello', $page->getSlug());
    }

    public function testRestoreAppendsARevisionWithTheOldBody(): void
    {
        $space   = new WikiSpace('d', 'D', WikiSpaceOwnerScope::Team, 't');
        $page    = new WikiPage($space, 'p', 'Title');
        $first   = new WikiPageRevision($page, 1, '<p>first</p>', new TestUser());
        $current = new WikiPageRevision($page, 2, '<p>second</p>', new TestUser());
        $page->setCurrentRevision($current);

        $restored = $this->service(3)->restore($page, $first, new TestUser());

        self::assertSame(3, $restored->getRevisionNumber());
        self::assertSame('<p>first</p>', $restored->getContentHtml());
        self::assertSame($restored, $page->getCurrentRevision());
        self::assertSame('Title', $page->getTitle());
        self::assertSame(['saved:3:2', 'restored:1->3'], $this->events);
    }

    public function testRestoreRefusesArchivedPagesAndForeignRevisions(): void
    {
        $space = new WikiSpace('d', 'D', WikiSpaceOwnerScope::Team, 't');
        $page  = new WikiPage($space, 'p', 'P');
        $other = new WikiPage($space, 'o', 'O');

        try {
            $this->service()->restore($page, new WikiPageRevision($other, 1, '', new TestUser()), new TestUser());
            self::fail('A foreign revision must be refused.');
        } catch (InvalidArgumentException) {
        }

        $page->archive();
        $this->expectException(InvalidArgumentException::class);
        $this->service()->restore($page, new WikiPageRevision($page, 1, '', new TestUser()), new TestUser());
    }

    public function testArchiveAndUnarchiveAreIdempotentAndCarryTheActor(): void
    {
        $page    = new WikiPage(new WikiSpace('d', 'D', WikiSpaceOwnerScope::Team, 't'), 'p', 'P');
        $service = $this->service();

        $service->unarchive($page);
        $service->archive($page, new TestUser());
        $service->archive($page);
        self::assertTrue($page->isArchived());
        $service->unarchive($page, new TestUser());
        self::assertFalse($page->isArchived());
        self::assertNull($page->getArchivedAt());
        self::assertSame(['archived:actor', 'unarchived:actor'], $this->events);
    }

    public function testForeignAttachmentUrlsAreDroppedOnSave(): void
    {
        $space   = new WikiSpace('d', 'D', WikiSpaceOwnerScope::Team, 't');
        $page    = new WikiPage($space, 'p', 'P');
        $service = new WikiPageService(
            $this->createStub(WikiPageRepositoryInterface::class),
            $this->createStub(WikiPageRevisionRepositoryInterface::class),
            new WikiSlugger(),
            new WikiHtmlSanitizer(),
            new EventDispatcher(),
            null,
            new WikiAttachmentReferenceFilter(new InMemoryAttachmentRepository()),
        );

        $revision = $service->saveRevision($page, 'P', '<p><img src="/w/attachments/0b9c9a9e-1111-4222-8333-444455556666" alt="x">kept</p>', new TestUser());

        self::assertSame('<p>kept</p>', $revision->getContentHtml());
    }

    private function service(int $nextNumber = 1): WikiPageService
    {
        $pages = $this->createStub(WikiPageRepositoryInterface::class);
        $pages->method('countBySpaceAndSlug')->willReturn(0);
        $revisions = $this->createStub(WikiPageRevisionRepositoryInterface::class);
        $revisions->method('getNextRevisionNumber')->willReturn($nextNumber);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WikiEvents::PAGE_SAVED, function (WikiPageSavedEvent $e): void {
            $this->events[] = 'saved:' . $e->revision->getRevisionNumber() . ':' . ($e->previousRevision?->getRevisionNumber() ?? 'none');
        });
        $dispatcher->addListener(WikiEvents::PAGE_CREATED, function (WikiPageCreatedEvent $e): void {
            $this->events[] = 'created';
        });
        $dispatcher->addListener(WikiEvents::PAGE_RESTORED, function (WikiPageRestoredEvent $e): void {
            $this->events[] = 'restored:' . $e->restoredFrom->getRevisionNumber() . '->' . $e->revision->getRevisionNumber();
        });
        $dispatcher->addListener(WikiEvents::PAGE_ARCHIVED, function (WikiPageArchivedEvent $e): void {
            $this->events[] = 'archived:' . ($e->actor !== null ? 'actor' : 'none');
        });
        $dispatcher->addListener(WikiEvents::PAGE_UNARCHIVED, function (WikiPageUnarchivedEvent $e): void {
            $this->events[] = 'unarchived:' . ($e->actor !== null ? 'actor' : 'none');
        });

        return new WikiPageService($pages, $revisions, new WikiSlugger(), new WikiHtmlSanitizer(), $dispatcher);
    }
}
