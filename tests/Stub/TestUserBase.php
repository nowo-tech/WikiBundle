<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Stub;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Extendable test user (display-name variants in collaboration tests).
 */
abstract class TestUserBase implements UserInterface
{
    public function getUserIdentifier(): string
    {
        return 'named@wiki.local';
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
