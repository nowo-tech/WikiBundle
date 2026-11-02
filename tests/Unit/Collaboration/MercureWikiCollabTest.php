<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Collaboration;

use Nowo\WikiBundle\Collaboration\MercureWikiCollabPublisher;
use Nowo\WikiBundle\Collaboration\MercureWikiCollabSubscriberAuthorizer;
use Nowo\WikiBundle\Collaboration\WikiCollabState;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;

final class MercureWikiCollabTest extends TestCase
{
    public function testPublishesPrivateUpdates(): void
    {
        $sent = [];
        $hub  = new MockHub('https://hub.test/.well-known/mercure', new StaticTokenProvider('t'), static function (Update $update) use (&$sent): string {
            $sent[] = $update;

            return 'id';
        });

        (new MercureWikiCollabPublisher($hub))->publish('urn:page:1', '{"t":"u"}');

        self::assertCount(1, $sent);
        self::assertSame(['urn:page:1'], $sent[0]->getTopics());
        self::assertSame('{"t":"u"}', $sent[0]->getData());
        self::assertTrue($sent[0]->isPrivate());
    }

    public function testTheCookieAllowsSubscribingToTheTopicOnly(): void
    {
        $hub     = new MockHub('https://wiki.test/.well-known/mercure', new StaticTokenProvider('t'), static fn (): string => 'id', new LcobucciFactory(str_repeat('k', 32)), 'https://wiki.test/.well-known/mercure');
        $request = Request::create('https://wiki.test/page');

        (new MercureWikiCollabSubscriberAuthorizer(new Authorization(new HubRegistry($hub))))->authorize($request, 'urn:page:1');

        $cookie = $request->attributes->get('_mercure_authorization_cookies');
        self::assertIsArray($cookie);
        self::assertNotEmpty($cookie);
    }

    public function testStateIsEmptyWithoutSnapshotAndUpdates(): void
    {
        self::assertTrue((new WikiCollabState())->isEmpty());
        self::assertFalse((new WikiCollabState(snapshotSeq: 3))->isEmpty());
        self::assertFalse((new WikiCollabState(updateCount: 1))->isEmpty());
    }
}
