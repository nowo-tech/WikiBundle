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

/**
 * User decision 2026-11-02 and audit CW-L1: every save makes the saved Markdown the source of truth.
 */
final class WikiCollabSaveListenerTest extends TestCase
{
    /** @var list<string> */
    private array $published = [];

    public function testEverySaveDropsTheLogAndReloadsTheEditors(): void
    {
        $page     = new WikiPage(new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't'), 'p', 'P');
        $revision = new WikiPageRevision($page, 2, '<p>x</p>', new TestUser(), 'x');
        $store    = new InMemoryCollabStateStore();
        $listener = new WikiCollabSaveListener($this->service($store));

        $listener(new WikiPageSavedEvent($page, $revision));
        self::assertSame([], $this->published, 'no session yet: nothing to reset');

        $store->append($page->getId(), 'AQ==');
        $listener(new WikiPageSavedEvent($page, $revision));
        self::assertTrue($store->info($page->getId())->isEmpty());
        self::assertSame($revision->getId(), $store->info($page->getId())->baseRevisionId);
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
