<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

/**
 * 1.24 (spec 218): the role gate's `list` permission applies to API reads as to the UI's.
 */
final class ApiListGateTest extends ApiTestCase
{
    protected static array $wikiConfig = ['security' => ['access_roles' => ['ROLE_USER'], 'roles' => ['list' => ['ROLE_LISTER']]]];

    public function testReadsNeedTheListPermission(): void
    {
        $this->assertRefusal($this->call('GET', '/wikis'), 403, 'ACCESS_002');
        $this->assertRefusal($this->call('GET', '/wikis/eng'), 403, 'ACCESS_002');
        $this->assertRefusal($this->call('GET', '/search?q=x'), 403, 'ACCESS_002');
    }
}
