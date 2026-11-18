<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Security;

use Nowo\WikiBundle\Security\ConfigurableWikiAccessChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

use function in_array;

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

    /**
     * 1.44 (audit TI-M1): a user other than the session's is decided on that user's own roles, never on the session's
     * token; the session's own user (and a call without a session user) still on the token.
     */
    public function testAnotherUserThanTheSessionsIsDecidedForThatUser(): void
    {
        $session  = new InMemoryUser('alice', null, ['ROLE_WIKI']);
        $bob      = new InMemoryUser('bob', null, []);
        $carol    = new InMemoryUser('carol', null, ['ROLE_WIKI']);
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($session);
        // The session (Alice) holds every role; only the per-user decision tells Bob and Carol apart.
        $security->method('isGranted')->willReturn(true);
        $security->method('isGrantedForUser')->willReturnCallback(static fn (UserInterface $user, string $role): bool => in_array($role, $user->getRoles(), true));
        $checker = new ConfigurableWikiAccessChecker($security, ['ROLE_ADMIN'], ['ROLE_WIKI'], ['ROLE_WIKI'], [], [], [], [], [], [], []);

        self::assertFalse($checker->canList($bob));
        self::assertFalse($checker->canAccess($bob));
        self::assertTrue($checker->canList($carol));
        self::assertTrue($checker->canList(new InMemoryUser('alice', null, [])));
        self::assertTrue($checker->canList());
    }
}
