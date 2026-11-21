<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\ValueObject\Uuid;
use Nowo\WikiBundle\ValueObject\WikiSpaceIcon;

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

    /** 1.32: UX icon name such as `tabler:book`; null shows the default book icon. */
    #[ORM\Column(type: 'string', length: WikiSpaceIcon::MAX_LENGTH, nullable: true)]
    private ?string $icon = null;

    /** 1.50: when the space was archived (read-only, hidden from navigation and search); null while it is active. */
    #[ORM\Column(name: 'archived_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $archivedAt = null;

    /** 1.50: when a purge of the space started (it stays archived until the purge deletes it); null otherwise. */
    #[ORM\Column(name: 'purge_started_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $purgeStartedAt = null;

    /** 1.50: the optimistic lock of rename, archive and unarchive; raised by each of them. */
    #[ORM\Column(name: 'lock_version', type: 'integer', options: ['default' => 1])]
    private int $version = 1;

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
     * Changes the slug (1.50, rename). The caller validates it and checks it is free within the owner scope.
     *
     * @param string $slug new URL-safe slug
     */
    public function changeSlug(string $slug): void
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->slug = $slug;
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * When the space was archived (1.50), or null while it is active.
     */
    public function getArchivedAt(): ?DateTimeImmutable
    {
        return $this->archivedAt;
    }

    /**
     * Whether the space is archived (1.50): read-only, hidden from navigation and search.
     */
    public function isArchived(): bool
    {
        return $this->archivedAt instanceof DateTimeImmutable;
    }

    /**
     * Archives (`true`) or brings back (`false`) the space (1.50) and refreshes the update timestamp.
     */
    public function changeArchived(bool $archived): void
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->archivedAt = $archived ? new DateTimeImmutable() : null;
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * When a purge of the space started (1.50), or null.
     */
    public function getPurgeStartedAt(): ?DateTimeImmutable
    {
        return $this->purgeStartedAt;
    }

    /**
     * Records that a purge started (1.50): the space is archived and stays so until the purge deletes it.
     */
    public function startPurge(): void
    {
        if (!$this->isArchived()) {
            // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
            $this->archivedAt = new DateTimeImmutable();
        }
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->purgeStartedAt ??= new DateTimeImmutable();
    }

    /**
     * The optimistic lock version (1.50): raised by every rename, archive and unarchive.
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * Raises the optimistic lock version (1.50).
     */
    public function bumpVersion(): void
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        ++$this->version;
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

    /**
     * Returns the UX icon name of the space (1.32).
     *
     * @return string|null icon name such as `tabler:book`, or null for the default book icon
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * Sets or clears the UX icon name of the space (1.32) and refreshes the update timestamp.
     *
     * @param string|null $icon `<prefix>:<name>` (lowercase letters, digits, dashes; max 64 characters), or null
     * @param list<string> $allowedPrefixes accepted icon set prefixes (`space_icon.allowed_prefixes`)
     *
     * @throws InvalidArgumentException when the value is not a valid icon name
     */
    public function setIcon(?string $icon, array $allowedPrefixes = WikiSpaceIcon::DEFAULT_PREFIXES): void
    {
        if ($icon !== null) {
            WikiSpaceIcon::assertValid($icon, $allowedPrefixes);
        }
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->icon = $icon;
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->updatedAt = new DateTimeImmutable();
    }
}
