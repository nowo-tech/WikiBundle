<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Member;

use Nowo\WikiBundle\Member\WikiMemberBadge;
use Nowo\WikiBundle\Member\WikiMemberBadges;
use Nowo\WikiBundle\Service\WikiRequestBoundaryResetter;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\RecordingBadgeProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

use function array_map;
use function memory_get_usage;
use function range;

/**
 * Spec 234: the per-request badge memo — one provider call per list (no N+1), filtered answers, and nothing kept
 * across requests in a kept-alive kernel.
 */
final class WikiMemberBadgesTest extends TestCase
{
    public function testAListIsResolvedInOneCall(): void
    {
        $agent    = new WikiMemberBadge('Agent', 'agent');
        $provider = new RecordingBadgeProvider(['bot' => [$agent]]);
        $memo     = new WikiMemberBadges($provider);
        $ana      = new TestUserEntity('ana');
        $bot      = new TestUserEntity('bot');

        $memo->prime([$ana, $bot, $ana, new TestUserEntity('bot'), new stdClass()]);
        self::assertSame([['ana', 'bot']], $provider->calls, 'distinct members with an id, one call');

        self::assertSame([$agent], $memo->of($bot));
        self::assertSame([], $memo->of($ana));
        $memo->prime([$ana, $bot]);
        self::assertCount(1, $provider->calls, 'memoised within the request');

        self::assertSame([], $memo->of(null));
        self::assertSame([], $memo->of(new stdClass()));
        self::assertCount(1, $provider->calls, 'users without an id are never asked about');
    }

    public function testTheNumberOfCallsDoesNotGrowWithTheList(): void
    {
        $provider = new RecordingBadgeProvider();
        $memo     = new WikiMemberBadges($provider);
        $memo->prime(array_map(static fn (int $i): TestUserEntity => new TestUserEntity('u' . $i), range(1, 300)));

        self::assertCount(1, $provider->calls);
        self::assertCount(300, $provider->calls[0]);
    }

    public function testAMemberNotPrimedIsResolvedOnTheSpot(): void
    {
        $provider = new RecordingBadgeProvider(['ana' => [new WikiMemberBadge('Admin', 'info')]]);
        $memo     = new WikiMemberBadges($provider);

        self::assertSame('Admin', $memo->of(new TestUserEntity('ana'))[0]->label);
        self::assertSame([['ana']], $provider->calls);
    }

    public function testOnlyBadgesAreKeptAndAtMostThree(): void
    {
        $badges   = array_map(static fn (int $i): WikiMemberBadge => new WikiMemberBadge('B' . $i), range(1, 5));
        $provider = new RecordingBadgeProvider(['ana' => ['<b>raw html</b>', null, ...$badges]]);
        $memo     = new WikiMemberBadges($provider);

        self::assertSame(['B1', 'B2', 'B3'], array_map(static fn (WikiMemberBadge $b): string => $b->label, $memo->of(new TestUserEntity('ana'))));
    }

    public function testTheMemoIsBounded(): void
    {
        $provider = new RecordingBadgeProvider();
        $memo     = new WikiMemberBadges($provider);
        $memo->prime(array_map(static fn (int $i): TestUserEntity => new TestUserEntity('u' . $i), range(1, WikiMemberBadges::MAX_MEMO)));
        $memo->prime([new TestUserEntity('one-more')]);
        $memo->prime([new TestUserEntity('u1')]);

        self::assertCount(3, $provider->calls, 'the full memo was emptied, so u1 is asked again');
    }

    public function testKernelResetEmptiesTheMemo(): void
    {
        $provider = new RecordingBadgeProvider(['ana' => [new WikiMemberBadge('Agent', 'agent')]]);
        $memo     = new WikiMemberBadges($provider);
        $memo->of(new TestUserEntity('ana'));
        $memo->reset();
        $provider->badges = [];

        self::assertSame([], $memo->of(new TestUserEntity('ana')));
        self::assertCount(2, $provider->calls);
    }

    /**
     * REQ-RUNTIME-002: one kernel kept alive (no kernel.reset), two requests by two users — the second request never
     * reads the first one's answers, and memory stays flat over many requests.
     */
    public function testWorkerModeTwoRequestsTwoUsersOneKernel(): void
    {
        $provider   = new RecordingBadgeProvider(['ana' => [new WikiMemberBadge('Agent', 'agent')]]);
        $memo       = new WikiMemberBadges($provider);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new WikiRequestBoundaryResetter([$memo]));
        $kernel  = $this->createStub(HttpKernelInterface::class);
        $request = static function () use ($dispatcher, $kernel): void {
            $dispatcher->dispatch(new RequestEvent($kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST), KernelEvents::REQUEST);
        };

        $request();
        self::assertSame('Agent', $memo->of(new TestUserEntity('ana'))[0]->label ?? null);

        // The host revoked Ana's service-account flag; Bob makes the next request in the same worker.
        $provider->badges = ['bob' => [new WikiMemberBadge('Bot', 'agent')]];
        $request();
        self::assertSame([], $memo->of(new TestUserEntity('ana')), 'nothing of the previous request survives');
        self::assertSame('Bot', $memo->of(new TestUserEntity('bob'))[0]->label ?? null);

        $provider->badges = [];
        $before           = 0;
        for ($i = 0; $i < 2000; ++$i) {
            $request();
            $memo->prime([new TestUserEntity('ana'), new TestUserEntity('u' . $i)]);
            $provider->calls = [];
            if ($i === 100) {
                $before = memory_get_usage();
            }
        }
        self::assertLessThan(256 * 1024, memory_get_usage() - $before, 'memory stays flat');
    }
}
