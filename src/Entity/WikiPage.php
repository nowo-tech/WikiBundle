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

    /**
     * Creates a page with a generated UUID and both timestamps set to now.
     *
     * @param WikiSpace $space space the page belongs to
     * @param string $slug URL-safe slug, unique within the space
     * @param string $title page title
     * @param WikiPage|null $parent parent page, or null for a root page
     */
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

    /**
     * Returns the page identifier (UUID string).
     *
     * @return string page UUID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Returns the space the page belongs to.
     *
     * @return WikiSpace owning space
     */
    public function getSpace(): WikiSpace
    {
        return $this->space;
    }

    /**
     * Returns the page slug.
     *
     * @return string URL-safe slug, unique within the space
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Returns the page title.
     *
     * @return string page title
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Changes the page title and refreshes the update timestamp.
     *
     * @param string $title new page title
     */
    public function setTitle(string $title): void
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->title = $title;
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Returns the parent page.
     *
     * @return WikiPage|null parent page, or null for a root page
     */
    public function getParent(): ?self
    {
        return $this->parent;
    }

    /**
     * Returns the sort position of the page among its siblings.
     *
     * @return int sort position
     */
    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * Changes the sort position of the page and refreshes the update timestamp.
     *
     * @param int $position new sort position among siblings
     */
    public function setPosition(int $position): void
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->position = $position;
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Returns the revision currently shown for the page.
     *
     * @return WikiPageRevision|null current revision, or null before the first revision is saved
     */
    public function getCurrentRevision(): ?WikiPageRevision
    {
        return $this->currentRevision;
    }

    /**
     * Sets the revision currently shown for the page and refreshes the update timestamp.
     *
     * @param WikiPageRevision|null $revision revision to show, or null to clear it
     */
    public function setCurrentRevision(?WikiPageRevision $revision): void
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->currentRevision = $revision;
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Returns when the page was archived.
     *
     * @return DateTimeImmutable|null archive date, or null when the page is active
     */
    public function getArchivedAt(): ?DateTimeImmutable
    {
        return $this->archivedAt;
    }

    /**
     * Tells whether the page has been archived.
     *
     * @return bool true when an archive date is set
     */
    public function isArchived(): bool
    {
        return $this->archivedAt instanceof DateTimeImmutable;
    }

    /**
     * Marks the page as archived now and refreshes the update timestamp.
     */
    public function archive(): void
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->archivedAt = new DateTimeImmutable();
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Returns when the page was created.
     *
     * @return DateTimeImmutable creation date
     */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Returns when the page was last modified.
     *
     * @return DateTimeImmutable last modification date
     */
    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
