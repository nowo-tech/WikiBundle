<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Service;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Security\NullWikiTeamMembershipResolver;
use Nowo\WikiBundle\Security\WikiTeamMembershipResolverInterface;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolver;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

use function str_repeat;

final class WikiSpaceAccessResolverTest extends TestCase
{
    public function testListsUserScopedSpaces(): void
    {
        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::User, 'user-1');
        $repo  = $this->createMock(WikiSpaceRepositoryInterface::class);
        $repo->method('findAccessible')->willReturn([$space]);

        $resolver = new WikiSpaceAccessResolver($repo, new NullWikiTeamMembershipResolver(), 'user');
        $spaces   = $resolver->listSpacesForUser(new TestUser('user-1'));

        self::assertCount(1, $spaces);
        self::assertTrue($resolver->canAccessSpace(new TestUser('user-1'), $space));
    }

    public function testDeniesForeignSpace(): void
    {
        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::User, 'other');
        $repo  = $this->createMock(WikiSpaceRepositoryInterface::class);
        $repo->method('findAccessible')->willReturn([]);

        $resolver = new WikiSpaceAccessResolver($repo, new NullWikiTeamMembershipResolver(), 'user');

        self::assertFalse($resolver->canAccessSpace(new TestUser('user-1'), $space));
    }

    public function testUsesUserIdentifierWhenUserHasNoId(): void
    {
        $user = new class implements UserInterface {
            public function getUserIdentifier(): string
            {
                return 'identifier-only';
            }

            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }
        };

        $repo = $this->createMock(WikiSpaceRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('findAccessible')
            ->with(WikiSpaceOwnerScope::User->value, ['identifier-only'])
            ->willReturn([]);

        (new WikiSpaceAccessResolver($repo, new NullWikiTeamMembershipResolver(), 'user'))
            ->listSpacesForUser($user);
    }

    public function testListsTeamScopedSpaces(): void
    {
        $repo = $this->createMock(WikiSpaceRepositoryInterface::class);
        $repo->method('findAccessible')->willReturn([]);

        $spaces = (new WikiSpaceAccessResolver($repo, new NullWikiTeamMembershipResolver(), 'team'))
            ->listSpacesForUser(new TestUser());

        self::assertSame([], $spaces);
    }

    /**
     * 1.31.1 (house rule LQ): an id a collation could confuse with another owner's (padded, control or format
     * characters, too long, invalid UTF-8) is never sent to the database; integer ids are compared as strings.
     */
    public function testDropsUnusableTeamIdsAndStringifiesIntegers(): void
    {
        $teams = $this->createMock(WikiTeamMembershipResolverInterface::class);
        $teams->method('getTeamIdsForUser')->willReturn(['team-1', 'team-1 ', ' team-1', "team\u{200B}1", "team\t1", '', str_repeat('t', 65), "\xC3", 7]);

        $repo = $this->createMock(WikiSpaceRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('findAccessible')
            ->with(WikiSpaceOwnerScope::Team->value, ['team-1', '7'])
            ->willReturn([]);

        (new WikiSpaceAccessResolver($repo, $teams, 'team'))->listSpacesForUser(new TestUser());
    }

    /** 1.31.1 (house rule LQ): a padded user identifier owns nothing, so no query runs for it. */
    public function testPaddedUserIdentifierSeesNoSpace(): void
    {
        $user = new class implements UserInterface {
            public function getUserIdentifier(): string
            {
                return 'alice ';
            }

            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }
        };

        $repo = $this->createMock(WikiSpaceRepositoryInterface::class);
        $repo->expects(self::once())->method('findAccessible')->with(WikiSpaceOwnerScope::User->value, [])->willReturn([]);

        self::assertSame([], (new WikiSpaceAccessResolver($repo, new NullWikiTeamMembershipResolver(), 'user'))->listSpacesForUser($user));
    }
}
