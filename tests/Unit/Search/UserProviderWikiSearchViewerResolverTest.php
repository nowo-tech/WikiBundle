<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Search;

use Nowo\WikiBundle\Search\UserProviderWikiSearchViewerResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class UserProviderWikiSearchViewerResolverTest extends TestCase
{
    public function testLoadsTheViewerThroughTheUserProvider(): void
    {
        $resolver = new UserProviderWikiSearchViewerResolver(new InMemoryUserProvider(['ana@example.com' => ['password' => 'x', 'roles' => ['ROLE_USER']]]));

        $viewer = $resolver->viewer('ana@example.com');
        self::assertInstanceOf(InMemoryUser::class, $viewer);
        self::assertSame('ana@example.com', $viewer->getUserIdentifier());
        self::assertNull($resolver->viewer('nobody@example.com'));
        self::assertNull($resolver->viewer(''));
    }

    public function testNoProviderOrARefusingProviderMeansNoViewer(): void
    {
        self::assertNull((new UserProviderWikiSearchViewerResolver(null))->viewer('ana'));

        /** @var MockObject&UserProviderInterface<UserInterface> $users */
        $users = $this->createMock(UserProviderInterface::class);
        $users->method('loadUserByIdentifier')->willThrowException(new UserNotFoundException());
        self::assertNull((new UserProviderWikiSearchViewerResolver($users))->viewer('ana'));
    }
}
