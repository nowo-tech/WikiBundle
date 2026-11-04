<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Collaboration;

use Nowo\WikiBundle\Collaboration\MercureWikiCollabPublisher;
use Nowo\WikiBundle\Collaboration\MercureWikiCollabSubscriberAuthorizer;
use Nowo\WikiBundle\Collaboration\WikiCollabState;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\ProtocolVersion;
use Symfony\Component\Mercure\Update;

use const JSON_THROW_ON_ERROR;

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
        $factory = new LcobucciFactory(str_repeat('k', 32), protocolVersion: ProtocolVersion::Legacy);
        $hub     = new MockHub('https://wiki.test/.well-known/mercure', new StaticTokenProvider('t'), static fn (): string => 'id', $factory, 'https://wiki.test/.well-known/mercure', protocolVersion: ProtocolVersion::Legacy);
        $request = Request::create('https://wiki.test/page');

        (new MercureWikiCollabSubscriberAuthorizer(new Authorization(new HubRegistry($hub))))->authorize($request, 'urn:page:1');

        $cookies = $request->attributes->get('_mercure_authorization_cookies');
        self::assertIsArray($cookies);
        $cookie = reset($cookies);
        self::assertInstanceOf(Cookie::class, $cookie);
        $claims = json_decode(base64_decode(strtr(explode('.', (string) $cookie->getValue())[1], '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($claims);
        self::assertSame(['subscribe' => ['urn:page:1']], $claims['mercure'], 'subscribe to this topic only, no publish');
    }

    public function testSeveralTopicsShareOneGrant(): void
    {
        // 1.16: the comments topic rides on the co-editing grant (one cookie).
        $factory = new LcobucciFactory(str_repeat('k', 32), protocolVersion: ProtocolVersion::Legacy);
        $hub     = new MockHub('https://wiki.test/.well-known/mercure', new StaticTokenProvider('t'), static fn (): string => 'id', $factory, 'https://wiki.test/.well-known/mercure', protocolVersion: ProtocolVersion::Legacy);
        $request = Request::create('https://wiki.test/page');

        (new MercureWikiCollabSubscriberAuthorizer(new Authorization(new HubRegistry($hub))))->authorizeTopics($request, ['urn:page:1', 'urn:page:1/comments']);

        $cookies = $request->attributes->get('_mercure_authorization_cookies');
        self::assertIsArray($cookies);
        $cookie = reset($cookies);
        self::assertInstanceOf(Cookie::class, $cookie);
        $claims = json_decode(base64_decode(strtr(explode('.', (string) $cookie->getValue())[1], '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($claims);
        self::assertSame(['subscribe' => ['urn:page:1', 'urn:page:1/comments']], $claims['mercure']);
    }

    public function testStateIsEmptyWithoutSnapshotAndUpdates(): void
    {
        self::assertTrue((new WikiCollabState())->isEmpty());
        self::assertFalse((new WikiCollabState(snapshotSeq: 3))->isEmpty());
        self::assertFalse((new WikiCollabState(updateCount: 1))->isEmpty());
    }
}
