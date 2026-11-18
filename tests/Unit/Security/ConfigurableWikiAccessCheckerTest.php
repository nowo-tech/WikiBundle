<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Security;

use Nowo\WikiBundle\Security\ConfigurableWikiAccessChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException as SecurityInvalidArgumentException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

use function in_array;
use function str_starts_with;

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

    /** 1.44 (audit TI-D1): authentication attributes cannot be decided for another user (offline token): not granted, no exception. */
    public function testAuthenticationAttributesForAnotherUserAreNotGrantedAndNeverThrow(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(new InMemoryUser('alice', null, ['ROLE_USER']));
        $security->method('isGranted')->willReturn(true);
        $security->method('isGrantedForUser')->willReturnCallback(static function (UserInterface $user, string $role): bool {
            if (str_starts_with($role, 'IS_AUTHENTICATED') || $role === 'PUBLIC_ACCESS') {
                throw new SecurityInvalidArgumentException('Cannot decide on authentication attributes when an offline token is used.');
            }

            return in_array($role, $user->getRoles(), true);
        });
        $checker = new ConfigurableWikiAccessChecker($security, ['PUBLIC_ACCESS'], ['ROLE_USER', 'IS_AUTHENTICATED_FULLY'], ['ROLE_USER', 'IS_AUTHENTICATED_FULLY'], [], [], [], [], [], [], []);

        self::assertFalse($checker->canList(new InMemoryUser('bob', null, [])));
        self::assertTrue($checker->canList(new InMemoryUser('carol', null, ['ROLE_USER'])));
    }

    /**
     * 1.44 (audit TI-D2): the REST API's principal may be a security adapter of another class with the same id as the
     * firewall's user (another identifier, broader stored roles): it is the session's user, judged on the token only.
     */
    public function testAnAdapterWithTheSessionUsersIdIsJudgedOnTheToken(): void
    {
        $firewall = new class implements UserInterface {
            public function getId(): string
            {
                return '7';
            }

            public function getUserIdentifier(): string
            {
                return 'sso|7';
            }

            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }
        };
        $adapter = new class implements UserInterface {
            public function getId(): int
            {
                return 7;
            }

            public function getUserIdentifier(): string
            {
                return 'admin-7';
            }

            public function getRoles(): array
            {
                return ['ROLE_ADMIN'];
            }

            public function eraseCredentials(): void
            {
            }
        };
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($firewall);
        $security->method('isGranted')->willReturn(false);
        $security->expects(self::never())->method('isGrantedForUser');
        $checker = new ConfigurableWikiAccessChecker($security, ['ROLE_ADMIN'], ['ROLE_USER'], ['ROLE_USER'], [], [], [], [], [], [], []);

        self::assertFalse($checker->canList($adapter));
        self::assertFalse($checker->canAccess($adapter));
    }
}
