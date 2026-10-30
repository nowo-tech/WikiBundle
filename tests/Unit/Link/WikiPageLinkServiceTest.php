<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Link;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageLink;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiPageLinkEvent;
use Nowo\WikiBundle\Link\WikiLinkedObject;
use Nowo\WikiBundle\Link\WikiObjectLinkResolverInterface;
use Nowo\WikiBundle\Link\WikiPageLinkService;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\InMemoryLinkRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Core\User\UserInterface;

final class WikiPageLinkServiceTest extends TestCase
{
    public function testLinkIsIdempotentAndUnlinkRemovesIt(): void
    {
        $page       = new WikiPage(new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't'), 'p', 'P');
        $repo       = new InMemoryLinkRepository();
        $dispatcher = new EventDispatcher();
        $events     = [];
        $dispatcher->addListener(WikiEvents::PAGE_LINKED, static function (WikiPageLinkEvent $event) use (&$events): void {
            $events[] = 'linked:' . $event->objectType . ':' . $event->objectId;
        });
        $dispatcher->addListener(WikiEvents::PAGE_UNLINKED, static function (WikiPageLinkEvent $event) use (&$events): void {
            $events[] = 'unlinked:' . $event->objectId;
        });
        $service = new WikiPageLinkService($repo, [], $dispatcher);

        $link = $service->link($page, 'issue', '42');
        self::assertSame($link, $service->link($page, 'issue', '42'));
        self::assertTrue($service->unlink($page, 'issue', '42'));
        self::assertFalse($service->unlink($page, 'issue', '42'));
        self::assertSame(['linked:issue:42', 'unlinked:42'], $events);
    }

    public function testUsedInAsksEachResolverOncePerTypeAndKeepsOnlyVisibleObjects(): void
    {
        $page = new WikiPage(new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't'), 'p', 'P');
        $repo = new InMemoryLinkRepository();
        $repo->save(new WikiPageLink($page, 'issue', '42'));
        $repo->save(new WikiPageLink($page, 'issue', '43'));
        $repo->save(new WikiPageLink($page, 'task', '7'));
        $repo->save(new WikiPageLink($page, 'alien', 'x'));

        $resolver = new class implements WikiObjectLinkResolverInterface {
            /** @var list<array{string, list<string>}> */
            public array $calls = [];

            public function supports(string $objectType): bool
            {
                return $objectType !== 'alien';
            }

            public function resolveVisible(UserInterface $user, string $objectType, array $objectIds): array
            {
                $this->calls[] = [$objectType, $objectIds];

                return $objectType === 'issue' ? ['42' => new WikiLinkedObject('issue', '42', 'BEACON-42', '/issues/42', 'open')] : [];
            }
        };

        $service = new WikiPageLinkService($repo, [$resolver], new EventDispatcher());
        $usedIn  = $service->usedIn($page, new TestUser());

        self::assertSame(['issue'], array_keys($usedIn));
        self::assertSame('BEACON-42', $usedIn['issue'][0]->label);
        self::assertSame('open', $usedIn['issue'][0]->badge);
        self::assertSame([['issue', ['42', '43']], ['task', ['7']]], $resolver->calls);
    }
}
