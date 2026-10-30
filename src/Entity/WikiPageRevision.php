<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nowo\WikiBundle\ValueObject\Uuid;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Immutable content revision for a wiki page.
 *
 * 1.11: a revision is either HTML (`format` = {@see self::FORMAT_HTML}, the Tiptap editor) or Markdown
 * (`format` = {@see self::FORMAT_MARKDOWN}): then `content_markdown` is the source and `content_html` its sanitized
 * render cache (search, AI context, attachment references and readers of the HTML keep working).
 */
#[ORM\Entity]
#[ORM\Table(name: 'wiki_page_revisions')]
#[ORM\UniqueConstraint(name: 'wiki_revision_page_number', columns: ['page_id', 'revision_number'])]
class WikiPageRevision
{
    /** The revision body is HTML (Tiptap editor); `content_markdown` is null. */
    public const FORMAT_HTML = 'html';

    /** The revision body is Markdown (`content_markdown`); `content_html` is its sanitized render cache. */
    public const FORMAT_MARKDOWN = 'markdown';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'format', type: 'string', length: 16, options: ['default' => self::FORMAT_HTML])]
    private string $format;

    /**
     * Creates a revision with a generated UUID and the creation timestamp set to now.
     *
     * @param WikiPage $page page this revision belongs to
     * @param int $revisionNumber sequential revision number within the page
     * @param string $contentHtml HTML content of the revision
     * @param object $author application user entity (remapped at runtime via metadata listener)
     * @param string|null $contentMarkdown Markdown source (1.11); when set the revision format is Markdown and
     *                                     $contentHtml is its rendered cache
     */
    public function __construct(#[ORM\ManyToOne(targetEntity: WikiPage::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private WikiPage $page, #[ORM\Column(name: 'revision_number', type: 'integer')]
        private int $revisionNumber, #[ORM\Column(name: 'content_html', type: 'text')]
        private string $contentHtml, #[ORM\ManyToOne(targetEntity: UserInterface::class)]
        #[ORM\JoinColumn(name: 'author_id', nullable: false, onDelete: 'CASCADE')]
        private object $author, #[ORM\Column(name: 'content_markdown', type: 'text', nullable: true)]
        private ?string $contentMarkdown = null)
    {
        $this->id        = Uuid::generate()->toString();
        $this->createdAt = new DateTimeImmutable();
        $this->format    = $this->contentMarkdown === null ? self::FORMAT_HTML : self::FORMAT_MARKDOWN;
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
     * Returns the Markdown source of a Markdown revision, null for an HTML revision (1.11).
     *
     * @return string|null revision Markdown
     */
    public function getContentMarkdown(): ?string
    {
        return $this->contentMarkdown;
    }

    /**
     * Returns the revision format: {@see self::FORMAT_HTML} or {@see self::FORMAT_MARKDOWN} (1.11).
     *
     * @return string revision format
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * Whether the Markdown is the source of this revision (1.11).
     *
     * @return bool true for a Markdown revision
     */
    public function isMarkdown(): bool
    {
        return $this->format === self::FORMAT_MARKDOWN;
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
