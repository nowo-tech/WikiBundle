<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Service;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Security\NullWikiTeamMembershipResolver;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolver;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\TestCase;

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
        $user = new class implements \Symfony\Component\Security\Core\User\UserInterface {
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
}
