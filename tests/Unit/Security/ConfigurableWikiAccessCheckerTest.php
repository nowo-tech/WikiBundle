<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Security;

use Nowo\WikiBundle\Security\ConfigurableWikiAccessChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class ConfigurableWikiAccessCheckerTest extends TestCase
{
    public function testAdminCanEdit(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(static fn (string $role): bool => $role === 'ROLE_ADMIN');

        $checker = new ConfigurableWikiAccessChecker($security, ['ROLE_ADMIN'], [], [], [], [], [], [], [], [], []);

        self::assertTrue($checker->canEdit());
        self::assertTrue($checker->canViewHistory());
        self::assertTrue($checker->canAccess());
        self::assertTrue($checker->canList());
        self::assertTrue($checker->canCreate());
        self::assertTrue($checker->canArchive());
        self::assertTrue($checker->canAskAi());
        self::assertTrue($checker->canImport());
        self::assertTrue($checker->canExport());
    }

    public function testNoRolesDenied(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturn(false);

        $checker = new ConfigurableWikiAccessChecker(
            $security,
            ['ROLE_ADMIN'],
            ['ROLE_WIKI_VIEW'],
            ['ROLE_WIKI_VIEW'],
            ['ROLE_WIKI_EDITOR'],
            ['ROLE_WIKI_EDITOR'],
            ['ROLE_WIKI_VIEW'],
            ['ROLE_WIKI_EDITOR'],
            ['ROLE_WIKI_EDITOR'],
            ['ROLE_WIKI_VIEW'],
            ['ROLE_WIKI_IMPORT'],
            ['ROLE_WIKI_EXPORT'],
        );

        self::assertFalse($checker->canAccess());
        self::assertFalse($checker->canList());
        self::assertFalse($checker->canCreate());
        self::assertFalse($checker->canEdit());
        self::assertFalse($checker->canViewHistory());
        self::assertFalse($checker->canArchive());
        self::assertFalse($checker->canAskAi());
        self::assertFalse($checker->canImport());
        self::assertFalse($checker->canExport());
    }
}
