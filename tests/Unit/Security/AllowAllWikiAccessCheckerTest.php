<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Security;

use Nowo\WikiBundle\Security\AllowAllWikiAccessChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(AllowAllWikiAccessChecker::class)]
final class AllowAllWikiAccessCheckerTest extends TestCase
{
    public function testAllowsAnonymousAndAuthenticated(): void
    {
        $checker = new AllowAllWikiAccessChecker();
        $user    = new InMemoryUser('demo', null, ['ROLE_USER']);

        self::assertTrue($checker->canAccess());
        self::assertTrue($checker->canList());
        self::assertTrue($checker->canCreate());
        self::assertTrue($checker->canEdit());
        self::assertTrue($checker->canViewHistory());
        self::assertTrue($checker->canArchive());
        self::assertTrue($checker->canAskAi());
        self::assertTrue($checker->canImport());
        self::assertTrue($checker->canExport());
        self::assertTrue($checker->canAccess($user));
        self::assertTrue($checker->canList($user));
        self::assertTrue($checker->canCreate($user));
        self::assertTrue($checker->canEdit($user));
        self::assertTrue($checker->canViewHistory($user));
        self::assertTrue($checker->canArchive($user));
        self::assertTrue($checker->canAskAi($user));
        self::assertTrue($checker->canImport($user));
        self::assertTrue($checker->canExport($user));
    }
}
