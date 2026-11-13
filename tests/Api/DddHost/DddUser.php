<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\DddHost;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The DDD host's security user: a plain PHP object, no Doctrine mapping, no bundle base class or trait. The host's
 * domain keeps its own user; this adapter only carries the id, the identifier and the team the host decided.
 */
final readonly class DddUser implements UserInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private string $id,
        public string $team,
        private array $roles = ['ROLE_USER'],
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
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
    }
}
