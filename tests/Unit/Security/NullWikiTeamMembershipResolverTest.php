<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Security;

use Nowo\WikiBundle\Security\NullWikiTeamMembershipResolver;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\TestCase;

final class NullWikiTeamMembershipResolverTest extends TestCase
{
    public function testReturnsEmptyTeamList(): void
    {
        self::assertSame([], (new NullWikiTeamMembershipResolver())->getTeamIdsForUser(new TestUser()));
    }
}
