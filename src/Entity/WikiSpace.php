<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\ValueObject\Uuid;

/**
 * A wiki workspace (team or user scoped).
 */
#[ORM\Entity]
#[ORM\Table(name: 'wiki_spaces')]
#[ORM\UniqueConstraint(name: 'wiki_space_scope_slug', columns: ['owner_scope_type', 'owner_scope_id', 'slug'])]
class WikiSpace
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Column(type: 'string', length: 120)]
        private string $slug,
        #[ORM\Column(type: 'string', length: 255)]
        private string $name,
        #[ORM\Column(name: 'owner_scope_type', type: 'string', length: 16, enumType: WikiSpaceOwnerScope::class)]
        private WikiSpaceOwnerScope $ownerScopeType,
        #[ORM\Column(name: 'owner_scope_id', type: 'string', length: 64)]
        private string $ownerScopeId,
    ) {
        $this->id        = Uuid::generate()->toString();
        $now             = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name      = $name;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getOwnerScopeType(): WikiSpaceOwnerScope
    {
        return $this->ownerScopeType;
    }

    public function getOwnerScopeId(): string
    {
        return $this->ownerScopeId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
