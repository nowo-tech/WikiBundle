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
    /** 1.16: an unmapped display name (author labels and avatars under history.show_names); a default, because Doctrine
     * hydrates entities loaded from the database without the constructor (PHP 8.4 native lazy objects). */
    private ?string $displayName = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'string', length: 36)]
        private string $id = 'user-1',
    ) {
    }

    /** 1.16: sets the unmapped display name (fluent; a constructor argument would be skipped by lazy hydration). */
    public function withDisplayName(string $displayName): static
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
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
