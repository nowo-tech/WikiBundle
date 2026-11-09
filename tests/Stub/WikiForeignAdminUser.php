<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Stub;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Back-office admin of a second identity domain (audit JX-W1 tests): its ids overlap with the
 * user_class ids; never listed in accepted_user_classes.
 */
final readonly class WikiForeignAdminUser implements UserInterface
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
