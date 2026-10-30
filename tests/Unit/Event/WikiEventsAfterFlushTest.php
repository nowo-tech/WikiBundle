<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Event;

use Nowo\WikiBundle\Attachment\ConfiguredWikiAttachmentLimitsProvider;
use Nowo\WikiBundle\Attachment\LocalWikiAttachmentStorage;
use Nowo\WikiBundle\Attachment\NullWikiAttachmentScanner;
use Nowo\WikiBundle\Attachment\WikiAttachmentService;
use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageLink;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiAttachmentEvent;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiPageLinkEvent;
use Nowo\WikiBundle\Link\WikiPageLinkService;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\InMemoryAttachmentRepository;
use Nowo\WikiBundle\Tests\Support\InMemoryLinkRepository;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function in_array;
use function iterator_to_array;

/**
 * FR-005: every wiki event is dispatched after the change is saved (the repositories flush in save / remove), in order.
 */
final class WikiEventsAfterFlushTest extends TestCase
{
    /** 1x1 transparent PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /** @var list<string> */
    private array $log = [];

    public function testPageEventsFollowTheirFlushInOrder(): void
    {
        $user    = new TestUser();
        $service = $this->pageService();
        $page    = $service->create(new WikiSpace('d', 'D', WikiSpaceOwnerScope::Team, 't'), 'Hello', '<p>one</p>', $user);
        $first   = $page->getCurrentRevision();
        self::assertInstanceOf(WikiPageRevision::class, $first);
        $service->saveRevision($page, 'Hello', '<p>two</p>', $user);
        $service->restore($page, $first, $user);
        $service->archive($page, $user);
        $service->unarchive($page, $user);

        self::assertSame([
            'flush:page', 'flush:revision', 'flush:page', 'event:' . WikiEvents::PAGE_SAVED, 'event:' . WikiEvents::PAGE_CREATED,
            'flush:revision', 'flush:page', 'event:' . WikiEvents::PAGE_SAVED,
            'flush:revision', 'flush:page', 'event:' . WikiEvents::PAGE_SAVED, 'event:' . WikiEvents::PAGE_RESTORED,
            'flush:page', 'event:' . WikiEvents::PAGE_ARCHIVED,
            'flush:page', 'event:' . WikiEvents::PAGE_UNARCHIVED,
        ], $this->log);
    }

    public function testLinkEventsSeeTheSavedState(): void
    {
        $page       = new WikiPage(new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't'), 'p', 'P');
        $repo       = new InMemoryLinkRepository();
        $dispatcher = new EventDispatcher();
        foreach ([WikiEvents::PAGE_LINKED, WikiEvents::PAGE_UNLINKED] as $name) {
            $dispatcher->addListener($name, function (WikiPageLinkEvent $event, string $eventName) use ($repo): void {
                $this->log[] = $eventName . ':' . ($repo->findOne($event->page, $event->objectType, $event->objectId) instanceof WikiPageLink ? 'stored' : 'gone') . ':' . ($event->actor !== null ? 'actor' : 'none');
            });
        }

        $service = new WikiPageLinkService($repo, [], $dispatcher);
        $service->link($page, 'issue', '42', new TestUser());
        $service->unlink($page, 'issue', '42', new TestUser());

        self::assertSame([WikiEvents::PAGE_LINKED . ':stored:actor', WikiEvents::PAGE_UNLINKED . ':gone:actor'], $this->log);
    }

    public function testAttachmentEventsSeeTheSavedState(): void
    {
        $dir        = sys_get_temp_dir() . '/wiki-evt-' . bin2hex(random_bytes(4));
        $repo       = new InMemoryAttachmentRepository();
        $storage    = new LocalWikiAttachmentStorage($dir);
        $dispatcher = new EventDispatcher();
        foreach ([WikiEvents::ATTACHMENT_UPLOADED, WikiEvents::ATTACHMENT_DELETED] as $name) {
            $dispatcher->addListener($name, function (WikiAttachmentEvent $event, string $eventName) use ($repo, $storage): void {
                $stored      = $repo->findById($event->attachment->getId()) instanceof WikiAttachment;
                $this->log[] = $eventName . ':' . ($stored ? 'row' : 'no-row') . ':' . (in_array($event->attachment->getStorageKey(), iterator_to_array($storage->keys(), false), true) ? 'file' : 'no-file');
            });
        }

        $service = new WikiAttachmentService($repo, $storage, new NullWikiAttachmentScanner(), new ConfiguredWikiAttachmentLimitsProvider(1_000_000, 10_000_000), $dispatcher);
        $path    = (string) tempnam(sys_get_temp_dir(), 'wiki-evt-');
        file_put_contents($path, base64_decode(self::PNG, true));

        try {
            $attachment = $service->upload(new WikiPage(new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't'), 'p', 'P'), $path, 'a.png', new TestUser());
            $service->delete($attachment, new TestUser());
        } finally {
            exec('rm -rf ' . escapeshellarg($dir) . ' ' . escapeshellarg($path));
        }

        self::assertSame([WikiEvents::ATTACHMENT_UPLOADED . ':row:file', WikiEvents::ATTACHMENT_DELETED . ':no-row:no-file'], $this->log);
    }

    private function pageService(): WikiPageService
    {
        $number = 0;
        $pages  = $this->createStub(WikiPageRepositoryInterface::class);
        $pages->method('countBySpaceAndSlug')->willReturn(0);
        $pages->method('save')->willReturnCallback(function (): void {
            $this->log[] = 'flush:page';
        });
        $revisions = $this->createStub(WikiPageRevisionRepositoryInterface::class);
        $revisions->method('getNextRevisionNumber')->willReturnCallback(static function () use (&$number): int {
            return ++$number;
        });
        $revisions->method('save')->willReturnCallback(function (): void {
            $this->log[] = 'flush:revision';
        });

        $dispatcher = new EventDispatcher();
        foreach ([WikiEvents::PAGE_SAVED, WikiEvents::PAGE_CREATED, WikiEvents::PAGE_RESTORED, WikiEvents::PAGE_ARCHIVED, WikiEvents::PAGE_UNARCHIVED] as $name) {
            $dispatcher->addListener($name, function (object $event, string $eventName): void {
                $this->log[] = 'event:' . $eventName;
            });
        }

        return new WikiPageService($pages, $revisions, new WikiSlugger(), new WikiHtmlSanitizer(), $dispatcher);
    }
}
