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

    public function getId(): string
    {
        return $this->id;
    }

    public function getPage(): WikiPage
    {
        return $this->page;
    }

    public function getRevisionNumber(): int
    {
        return $this->revisionNumber;
    }

    public function getContentHtml(): string
    {
        return $this->contentHtml;
    }

    public function getAuthor(): object
    {
        return $this->author;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
