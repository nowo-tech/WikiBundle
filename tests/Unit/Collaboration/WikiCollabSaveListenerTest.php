<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Collaboration;

use Nowo\WikiBundle\Collaboration\WikiCollabPublisherInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabSaveListener;
use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Nowo\WikiBundle\Collaboration\WikiCollabSubscriberAuthorizerInterface;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Service\WikiPageSavedEvent;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\InMemoryCollabStateStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * User decision 2026-11-02: a replacement (restore, import, conversion) wins over a live session.
 */
final class WikiCollabSaveListenerTest extends TestCase
{
    /** @var list<string> */
    private array $published = [];

    public function testTheCollaborativeSaveKeepsTheLogAndAnyOtherSaveResetsIt(): void
    {
        $page     = new WikiPage(new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't'), 'p', 'P');
        $revision = new WikiPageRevision($page, 2, '<p>x</p>', new TestUser(), 'x');
        $store    = new InMemoryCollabStateStore();
        $service  = $this->service($store);
        $event    = new WikiPageSavedEvent($page, $revision);

        (new WikiCollabSaveListener($service))($event);
        self::assertSame([], $this->published, 'no session yet: nothing to reset');
        self::assertNull($store->load($page->getId())->updatedAt);

        $store->append($page->getId(), 'AQ==');
        $collabRequest = new Request();
        $collabRequest->attributes->set(WikiCollabService::COLLAB_SAVE_ATTRIBUTE, $page->getId());
        $stack = new RequestStack([$collabRequest]);
        (new WikiCollabSaveListener($service, $stack))($event);
        self::assertSame(['AQ=='], $store->load($page->getId())->updates);
        self::assertSame($revision->getId(), $store->load($page->getId())->baseRevisionId);
        self::assertSame([], $this->published);

        (new WikiCollabSaveListener($service, new RequestStack([new Request()])))($event);
        self::assertSame([], $store->load($page->getId())->updates);
        self::assertSame(['{"t":"r","c":0,"d":""}'], $this->published);
    }

    private function service(InMemoryCollabStateStore $store): WikiCollabService
    {
        $publisher = new class($this->published) implements WikiCollabPublisherInterface {
            /** @param list<string> $published */
            public function __construct(private array &$published)
            {
            }

            public function publish(string $topic, string $message): void
            {
                $this->published[] = $message;
            }
        };

        return new WikiCollabService($store, $publisher, $this->createStub(WikiCollabSubscriberAuthorizerInterface::class));
    }
}
