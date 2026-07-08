<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nowo\WikiBundle\ValueObject\Uuid;

/**
 * A page inside a wiki space (tree via parent reference).
 */
#[ORM\Entity]
#[ORM\Table(name: 'wiki_pages')]
#[ORM\UniqueConstraint(name: 'wiki_page_space_slug', columns: ['space_id', 'slug'])]
class WikiPage
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'integer')]
    private int $position = 0;

    #[ORM\ManyToOne(targetEntity: WikiPageRevision::class)]
    #[ORM\JoinColumn(name: 'current_revision_id', nullable: true, onDelete: 'SET NULL')]
    private ?WikiPageRevision $currentRevision = null;

    #[ORM\Column(name: 'archived_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $archivedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(#[ORM\ManyToOne(targetEntity: WikiSpace::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private WikiSpace $space, #[ORM\Column(type: 'string', length: 120)]
        private string $slug, #[ORM\Column(type: 'string', length: 255)]
        private string $title, #[ORM\ManyToOne(targetEntity: self::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?self $parent = null)
    {
        $this->id        = Uuid::generate()->toString();
        $now             = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSpace(): WikiSpace
    {
        return $this->space;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title     = $title;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position  = $position;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCurrentRevision(): ?WikiPageRevision
    {
        return $this->currentRevision;
    }

    public function setCurrentRevision(?WikiPageRevision $revision): void
    {
        $this->currentRevision = $revision;
        $this->updatedAt       = new DateTimeImmutable();
    }

    public function getArchivedAt(): ?DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt instanceof DateTimeImmutable;
    }

    public function archive(): void
    {
        $this->archivedAt = new DateTimeImmutable();
        $this->updatedAt  = new DateTimeImmutable();
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
