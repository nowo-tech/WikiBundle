<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Stub;

use Symfony\Component\Security\Core\User\UserInterface;

final readonly class TestUser implements UserInterface
{
    public function __construct(
        private string $id = 'user-1',
        private string $identifier = 'demo@wiki.local',
        /** @var list<string> */
        private array $roles = ['ROLE_USER'],
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
    }
}
