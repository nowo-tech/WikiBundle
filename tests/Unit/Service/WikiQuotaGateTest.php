<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Service;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiQuotaCheckEvent;
use Nowo\WikiBundle\Service\WikiQuotaGate;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\Translation\TranslatorInterface;

use function str_repeat;

/**
 * 1.21: the cancellable quota check (`nowo_wiki.quota.check`).
 */
final class WikiQuotaGateTest extends TestCase
{
    public function testWithoutADispatcherOrAListenerEverythingMayHappen(): void
    {
        $space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');

        self::assertNull((new WikiQuotaGate())->refusal(WikiQuotaCheckEvent::PAGE_CREATE, new TestUser(), $space));
        self::assertNull((new WikiQuotaGate(new EventDispatcher()))->refusal(WikiQuotaCheckEvent::PAGE_CREATE, new TestUser(), $space));
    }

    public function testAListenerSeesTheWriteAndItsDenyStopsTheOthers(): void
    {
        $space  = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $page   = new WikiPage($space, 'run', 'Runbooks');
        $user   = new TestUser();
        $seen   = null;
        $later  = false;
        $events = new EventDispatcher();
        $events->addListener(WikiEvents::QUOTA_CHECK, static function (WikiQuotaCheckEvent $event) use (&$seen): void {
            $seen = $event;
            self::assertFalse($event->isDenied());
            self::assertNull($event->getReason());
            $event->deny("Storage\nfull\t ");
        }, 10);
        $events->addListener(WikiEvents::QUOTA_CHECK, static function () use (&$later): void {
            $later = true;
        });

        self::assertSame('Storage full', (new WikiQuotaGate($events))->refusal(WikiQuotaCheckEvent::ATTACHMENT_UPLOAD, $user, $space, 1, 2048, $page));
        self::assertFalse($later);
        self::assertInstanceOf(WikiQuotaCheckEvent::class, $seen);
        self::assertSame(WikiQuotaCheckEvent::ATTACHMENT_UPLOAD, $seen->action);
        self::assertSame($space, $seen->space);
        self::assertSame($user, $seen->user);
        self::assertSame(1, $seen->count);
        self::assertSame(2048, $seen->bytes);
        self::assertSame($page, $seen->page);
        self::assertTrue($seen->isDenied());
        self::assertTrue($seen->isPropagationStopped());
    }

    public function testAnEmptyReasonBecomesTheDefaultTextAndALongOneIsCut(): void
    {
        $space  = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $reason = '';
        $events = new EventDispatcher();
        $events->addListener(WikiEvents::QUOTA_CHECK, static function (WikiQuotaCheckEvent $event) use (&$reason): void {
            $event->deny($reason);
        });
        $translator = new class implements TranslatorInterface {
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return '[' . $domain . '] ' . $id;
            }

            public function getLocale(): string
            {
                return 'en';
            }
        };

        self::assertSame(WikiQuotaGate::DEFAULT_REASON, (new WikiQuotaGate($events))->refusal(WikiQuotaCheckEvent::IMPORT, new TestUser(), $space));
        self::assertSame('[NowoWikiBundle] wiki.quota.denied', (new WikiQuotaGate($events, $translator))->refusal(WikiQuotaCheckEvent::IMPORT, new TestUser(), $space));

        $reason = str_repeat('é', 600);
        self::assertSame(str_repeat('é', WikiQuotaCheckEvent::MAX_REASON), (new WikiQuotaGate($events))->refusal(WikiQuotaCheckEvent::IMPORT, new TestUser(), $space));
    }
}
