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

    /**
     * Creates a space with a generated UUID and both timestamps set to now.
     *
     * @param string $slug URL-safe slug, unique within the owner scope
     * @param string $name display name
     * @param WikiSpaceOwnerScope $ownerScopeType kind of owner (team or user)
     * @param string $ownerScopeId identifier of the owning team or user
     */
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

    /**
     * Returns the space identifier (UUID string).
     *
     * @return string space UUID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Returns the space slug.
     *
     * @return string URL-safe slug, unique within the owner scope
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Returns the space display name.
     *
     * @return string display name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Changes the space display name and refreshes the update timestamp.
     *
     * @param string $name new display name
     */
    public function setName(string $name): void
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->name = $name;
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Returns the kind of owner of the space.
     *
     * @return WikiSpaceOwnerScope owner scope type (team or user)
     */
    public function getOwnerScopeType(): WikiSpaceOwnerScope
    {
        return $this->ownerScopeType;
    }

    /**
     * Returns the identifier of the owning team or user.
     *
     * @return string owner scope identifier
     */
    public function getOwnerScopeId(): string
    {
        return $this->ownerScopeId;
    }

    /**
     * Returns when the space was created.
     *
     * @return DateTimeImmutable creation date
     */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Returns when the space was last modified.
     *
     * @return DateTimeImmutable last modification date
     */
    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
