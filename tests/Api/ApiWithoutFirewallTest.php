<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\WikiBundle\Tests\Support\RecordingLogger;

/**
 * 1.24 (spec 218 FR-005, contract F2): a host that forgot the firewall gets 500 on every request, never an answer judged
 * as nobody (or as someone else), and one logged error naming the cause.
 */
final class ApiWithoutFirewallTest extends ApiTestCase
{
    protected static bool $withFirewall = false;

    public function testEveryRequestFailsClosed(): void
    {
        $this->assertRefusal($this->call('GET', '/wikis/eng'), 500, 'SERVER_001');

        $logger = static::getContainer()->get('logger');
        self::assertInstanceOf(RecordingLogger::class, $logger);
        self::assertContains('error: Wiki API: the security user is not the token\'s user. The API must sit behind a stateless, secured firewall that authenticates the token.', $logger->lines());
    }
}
