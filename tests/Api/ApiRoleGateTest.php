<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;

/**
 * 1.24 (spec 218): the wiki's global gate (`WikiAccessCheckerInterface::canAccess`) applies to tokens too: a person the
 * gate refuses is answered like a bad token, and counted as one.
 */
final class ApiRoleGateTest extends ApiTestCase
{
    protected static array $wikiConfig = ['security' => ['access_roles' => ['ROLE_WIKI']]];

    public function testAPersonTheGateRefusesIsABadToken(): void
    {
        $this->assertRefusal($this->call('GET', '/wikis/eng'), 401, 'OAUTH_019');
        self::assertCount(1, ApiTestHost::$failedIps);
    }
}
