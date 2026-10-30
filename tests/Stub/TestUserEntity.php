<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Stub;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Doctrine user entity for the SQLite integration tests (targets of author / uploaded-by associations).
 */
#[ORM\Entity]
#[ORM\Table(name: 'test_users')]
class TestUserEntity implements UserInterface
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'string', length: 36)]
        private string $id = 'user-1'
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->id;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }
}
