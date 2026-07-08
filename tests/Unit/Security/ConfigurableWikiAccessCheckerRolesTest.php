<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Security;

use Nowo\WikiBundle\Security\ConfigurableWikiAccessChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class ConfigurableWikiAccessCheckerRolesTest extends TestCase
{
    public function testRoleBasedAccess(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (string $role): bool => $role === 'ROLE_WIKI_EDITOR',
        );

        $checker = new ConfigurableWikiAccessChecker(
            $security,
            ['ROLE_ADMIN'],
            ['ROLE_WIKI_VIEW'],
            ['ROLE_WIKI_VIEW'],
            ['ROLE_WIKI_EDITOR'],
            ['ROLE_WIKI_EDITOR'],
            ['ROLE_WIKI_VIEW'],
            ['ROLE_WIKI_EDITOR'],
            ['ROLE_WIKI_VIEW'],
            [],
            [],
        );

        self::assertTrue($checker->canEdit());
        self::assertTrue($checker->canCreate());
        self::assertFalse($checker->canAccess());
    }
}
