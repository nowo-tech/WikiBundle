<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Stub;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Security user of a hexagonal host (1.27 tests): a plain read model, no Doctrine entity, carrying the id of the
 * persisted user and its own identifier (an SSO subject, unlike the entity's).
 */
final readonly class WikiSecurityUserDto implements UserInterface
{
    public function __construct(
        private int|string $id,
        private string $identifier = 'sso|dto',
    ) {
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }
}
