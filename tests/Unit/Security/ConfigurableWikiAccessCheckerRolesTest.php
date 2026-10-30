<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Security;

use Nowo\WikiBundle\Security\ConfigurableWikiAccessChecker;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use function in_array;

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

        // 1.10: an action role alone is not enough, the access roles are the general gate.
        self::assertFalse($checker->canEdit());
        self::assertFalse($checker->canCreate());
        self::assertFalse($checker->canAccess());
    }

    public function testAnActionNeedsAnAccessRoleAndARoleOfThatAction(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (string $role): bool => in_array($role, ['ROLE_WIKI_VIEW', 'ROLE_WIKI_EDITOR'], true),
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

        self::assertTrue($checker->canAccess());
        self::assertTrue($checker->canList());
        self::assertTrue($checker->canEdit());
        self::assertTrue($checker->canArchive());
        self::assertFalse($checker->canImport(), 'an empty list grants nothing');
        self::assertFalse($checker->canExport());
    }

    public function testRolesFromTokenLeftByPreviousRequestAreIgnoredOutsideSecuredFirewall(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturn(true);

        $requestStack = new RequestStack();
        $checker      = new ConfigurableWikiAccessChecker(
            $security,
            ['ROLE_ADMIN'],
            ['ROLE_WIKI_VIEW'],
            ['ROLE_WIKI_VIEW'],
            ['ROLE_WIKI_EDITOR'],
            ['ROLE_WIKI_EDITOR'],
            ['ROLE_WIKI_VIEW'],
            ['ROLE_WIKI_EDITOR'],
            ['ROLE_WIKI_VIEW'],
            ['ROLE_WIKI_EDITOR'],
            ['ROLE_WIKI_VIEW'],
            new WikiTokenGuard($requestStack),
        );

        $secured = Request::create('/tools/wiki');
        $secured->attributes->set('_firewall_context', 'security.firewall.map.context.main');
        $requestStack->push($secured);
        self::assertTrue($checker->canEdit());
        self::assertTrue($checker->canAccess());
        $requestStack->pop();

        $requestStack->push(Request::create('/public'));
        self::assertFalse($checker->canEdit());
        self::assertFalse($checker->canAccess());
        self::assertFalse($checker->canExport());
    }
}
