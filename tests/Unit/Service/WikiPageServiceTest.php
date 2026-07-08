<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Service;

use InvalidArgumentException;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiPageArchivedEvent;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class WikiPageServiceTest extends TestCase
{
    public function testCreatePageAndFirstRevision(): void
    {
        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->expects(self::exactly(2))->method('save');
        $pageRepo->method('countBySpaceAndSlug')->willReturn(0);

        $revisionRepo = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $revisionRepo->expects(self::once())->method('save');

        $dispatcher = new EventDispatcher();
        $dispatched = false;
        $dispatcher->addListener(WikiEvents::PAGE_SAVED, static function () use (&$dispatched): void {
            $dispatched = true;
        });

        $space   = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 't1');
        $service = new WikiPageService($pageRepo, $revisionRepo, new WikiSlugger(), new WikiHtmlSanitizer(), $dispatcher);
        $page    = $service->create($space, 'Hello', '<p>Body</p>', new TestUser());

        self::assertSame('hello', $page->getSlug());
        self::assertTrue($dispatched);
    }

    public function testRejectsDuplicateSlug(): void
    {
        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('countBySpaceAndSlug')->willReturn(1);

        $service = new WikiPageService(
            $pageRepo,
            $this->createMock(WikiPageRevisionRepositoryInterface::class),
            new WikiSlugger(),
            new WikiHtmlSanitizer(),
            new EventDispatcher(),
        );

        $this->expectException(InvalidArgumentException::class);
        $service->create(new WikiSpace('d', 'D', WikiSpaceOwnerScope::Team, 't'), 'Title', '', new TestUser());
    }

    public function testSaveRevisionOnExistingPage(): void
    {
        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 't1');
        $page  = new WikiPage($space, 'page', 'Old');

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->expects(self::once())->method('save');

        $revisionRepo = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $revisionRepo->method('getNextRevisionNumber')->willReturn(2);
        $revisionRepo->expects(self::once())->method('save');

        $service = new WikiPageService($pageRepo, $revisionRepo, new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher());
        $service->saveRevision($page, 'New title', '<p>v2</p>', new TestUser());

        self::assertSame('New title', $page->getTitle());
    }

    public function testArchiveDispatchesEvent(): void
    {
        $page     = new WikiPage(new WikiSpace('d', 'D', WikiSpaceOwnerScope::Team, 't'), 'p', 'P');
        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->expects(self::once())->method('save');

        $dispatcher = new EventDispatcher();
        $captured   = null;
        $dispatcher->addListener(WikiEvents::PAGE_ARCHIVED, static function (WikiPageArchivedEvent $event) use (&$captured): void {
            $captured = $event;
        });

        $service = new WikiPageService($pageRepo, $this->createMock(WikiPageRevisionRepositoryInterface::class), new WikiSlugger(), new WikiHtmlSanitizer(), $dispatcher);
        $service->archive($page);

        self::assertInstanceOf(WikiPageArchivedEvent::class, $captured);
        self::assertTrue($page->isArchived());
    }

    public function testCannotEditArchivedPage(): void
    {
        $page = new WikiPage(new WikiSpace('d', 'D', WikiSpaceOwnerScope::Team, 't'), 'p', 'P');
        $page->archive();

        $service = new WikiPageService(
            $this->createMock(WikiPageRepositoryInterface::class),
            $this->createMock(WikiPageRevisionRepositoryInterface::class),
            new WikiSlugger(),
            new WikiHtmlSanitizer(),
            new EventDispatcher(),
        );

        $this->expectException(InvalidArgumentException::class);
        $service->saveRevision($page, 'x', 'y', new TestUser());
    }

    public function testRejectsInvalidSlugOnCreate(): void
    {
        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);

        $service = new WikiPageService(
            $pageRepo,
            $this->createMock(WikiPageRevisionRepositoryInterface::class),
            new WikiSlugger(),
            new WikiHtmlSanitizer(),
            new EventDispatcher(),
        );

        $this->expectException(InvalidArgumentException::class);
        $service->create(new WikiSpace('d', 'D', WikiSpaceOwnerScope::Team, 't'), 'Title', '', new TestUser(), null, '!!!');
    }

    public function testArchiveNoOpWhenAlreadyArchived(): void
    {
        $page = new WikiPage(new WikiSpace('d', 'D', WikiSpaceOwnerScope::Team, 't'), 'p', 'P');
        $page->archive();

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->expects(self::never())->method('save');

        (new WikiPageService(
            $pageRepo,
            $this->createMock(WikiPageRevisionRepositoryInterface::class),
            new WikiSlugger(),
            new WikiHtmlSanitizer(),
            new EventDispatcher(),
        ))->archive($page);
    }
}
