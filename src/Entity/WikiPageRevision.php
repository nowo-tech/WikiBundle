<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nowo\WikiBundle\ValueObject\Uuid;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Immutable content revision for a wiki page.
 */
#[ORM\Entity]
#[ORM\Table(name: 'wiki_page_revisions')]
#[ORM\UniqueConstraint(name: 'wiki_revision_page_number', columns: ['page_id', 'revision_number'])]
class WikiPageRevision
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    /**
     * Creates a revision with a generated UUID and the creation timestamp set to now.
     *
     * @param WikiPage $page page this revision belongs to
     * @param int $revisionNumber sequential revision number within the page
     * @param string $contentHtml HTML content of the revision
     * @param object $author application user entity (remapped at runtime via metadata listener)
     */
    public function __construct(#[ORM\ManyToOne(targetEntity: WikiPage::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private WikiPage $page, #[ORM\Column(name: 'revision_number', type: 'integer')]
        private int $revisionNumber, #[ORM\Column(name: 'content_html', type: 'text')]
        private string $contentHtml, #[ORM\ManyToOne(targetEntity: UserInterface::class)]
        #[ORM\JoinColumn(name: 'author_id', nullable: false, onDelete: 'CASCADE')]
        private object $author)
    {
        $this->id        = Uuid::generate()->toString();
        $this->createdAt = new DateTimeImmutable();
    }

    /**
     * Returns the revision identifier (UUID string).
     *
     * @return string revision UUID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Returns the page this revision belongs to.
     *
     * @return WikiPage revised page
     */
    public function getPage(): WikiPage
    {
        return $this->page;
    }

    /**
     * Returns the sequential revision number within the page.
     *
     * @return int revision number
     */
    public function getRevisionNumber(): int
    {
        return $this->revisionNumber;
    }

    /**
     * Returns the HTML content stored in this revision.
     *
     * @return string revision HTML
     */
    public function getContentHtml(): string
    {
        return $this->contentHtml;
    }

    /**
     * Returns the application user who authored the revision.
     *
     * @return object author user entity
     */
    public function getAuthor(): object
    {
        return $this->author;
    }

    /**
     * Returns when the revision was created.
     *
     * @return DateTimeImmutable creation date
     */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
