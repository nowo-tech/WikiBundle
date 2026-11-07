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
 * User decision 2026-11-02 on CW-L1: reset only when needed.
 */
final class WikiCollabSaveListenerTest extends TestCase
{
    /** @var list<string> */
    private array $published = [];

    public function testASaveFromTheLiveSessionKeepsTheLogAndReloadsNobody(): void
    {
        [$page, $previous, $revision, $store] = $this->session();
        $request                              = new Request();
        $request->attributes->set(WikiCollabService::SESSION_SAVE_ATTRIBUTE, $page->getId());

        (new WikiCollabSaveListener($this->service($store), new RequestStack([$request])))(new WikiPageSavedEvent($page, $revision, $previous));

        self::assertSame(['AQ=='], $store->load($page->getId())->updates);
        self::assertSame($revision->getId(), $store->info($page->getId())->baseRevisionId);
        self::assertSame([], $this->published);
    }

    public function testASessionSaveWithAStaleBaseResets(): void
    {
        [$page, , $revision, $store] = $this->session();
        $request                     = new Request();
        $request->attributes->set(WikiCollabService::SESSION_SAVE_ATTRIBUTE, $page->getId());
        $other = new WikiPageRevision($page, 9, '<p>o</p>', new TestUser(), 'o');

        (new WikiCollabSaveListener($this->service($store), new RequestStack([$request])))(new WikiPageSavedEvent($page, $revision, $other));

        self::assertTrue($store->info($page->getId())->isEmpty());
        self::assertSame(['{"t":"r","c":0,"d":""}'], $this->published);
    }

    /** Audit HO-M1: inside saveOutsideSession() the listener leaves the log alone: no publish inside the transaction. */
    public function testASaveWhoseLogIsHandledByTheCallerTouchesNothing(): void
    {
        [$page, $previous, $revision, $store] = $this->session();
        $request                              = new Request();
        $request->attributes->set(WikiCollabService::LOG_HANDLED_ATTRIBUTE, $page->getId());

        (new WikiCollabSaveListener($this->service($store), new RequestStack([$request])))(new WikiPageSavedEvent($page, $revision, $previous, new TestUser()));

        self::assertSame(['AQ=='], $store->load($page->getId())->updates);
        self::assertSame([], $this->published);
    }

    public function testASaveOutsideTheSessionResets(): void
    {
        [$page, $previous, $revision, $store] = $this->session();

        (new WikiCollabSaveListener($this->service($store), new RequestStack([new Request()])))(new WikiPageSavedEvent($page, $revision, $previous));
        self::assertTrue($store->info($page->getId())->isEmpty());
        self::assertSame($revision->getId(), $store->info($page->getId())->baseRevisionId);
        self::assertSame(['{"t":"r","c":0,"d":""}'], $this->published);

        $this->published = [];
        $fresh           = new WikiPage(new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't'), 'q', 'Q');
        (new WikiCollabSaveListener($this->service($store)))(new WikiPageSavedEvent($fresh, new WikiPageRevision($fresh, 1, '<p>x</p>', new TestUser())));
        self::assertSame([], $this->published, 'no session yet: nothing to reset');
    }

    /**
     * @return array{0: WikiPage, 1: WikiPageRevision, 2: WikiPageRevision, 3: InMemoryCollabStateStore}
     */
    private function session(): array
    {
        $page     = new WikiPage(new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't'), 'p', 'P');
        $previous = new WikiPageRevision($page, 1, '<p>a</p>', new TestUser(), 'a');
        $revision = new WikiPageRevision($page, 2, '<p>b</p>', new TestUser(), 'b');
        $store    = new InMemoryCollabStateStore();
        $store->reset($page->getId(), $previous->getId());
        $store->append($page->getId(), 'AQ==');

        return [$page, $previous, $revision, $store];
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

        return new WikiCollabService($store, $publisher, $this->createStub(WikiCollabSubscriberAuthorizerInterface::class), secret: 'test-secret');
    }
}
