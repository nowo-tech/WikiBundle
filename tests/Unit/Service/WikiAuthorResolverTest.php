<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use InvalidArgumentException;
use Nowo\WikiBundle\Service\WikiAuthorResolver;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

final class WikiAuthorResolverTest extends TestCase
{
    public function testResolveByPrimaryKey(): void
    {
        $user = new TestUser('user-42');
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->with('user-42')->willReturn($user);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(TestUser::class)->willReturn($repo);

        $resolved = (new WikiAuthorResolver($em, TestUser::class))->resolveByIdentifier('user-42');

        self::assertSame($user, $resolved);
    }

    public function testResolveByEmailWhenPrimaryKeyMissing(): void
    {
        $user = new EmailUserStub();
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->willReturn(null);
        $repo->method('findOneBy')->with(['email' => 'author@example.com'])->willReturn($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(EmailUserStub::class)->willReturn($repo);

        $resolved = (new WikiAuthorResolver($em, EmailUserStub::class))->resolveByIdentifier('author@example.com');

        self::assertSame($user, $resolved);
    }

    public function testThrowsWhenUserNotFound(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->willReturn(null);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $this->expectException(InvalidArgumentException::class);
        (new WikiAuthorResolver($em, TestUser::class))->resolveByIdentifier('missing');
    }
}

final class EmailUserStub implements UserInterface
{
    public function getEmail(): string
    {
        return 'author@example.com';
    }

    public function getUserIdentifier(): string
    {
        return 'author@example.com';
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }
}
