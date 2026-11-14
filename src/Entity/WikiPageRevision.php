<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nowo\WikiBundle\Provenance\WikiWriteProvenance;
use Nowo\WikiBundle\Provenance\WikiWriteProvenanceErasure;
use Nowo\WikiBundle\Search\WikiPlainText;
use Nowo\WikiBundle\Util\WikiPlainLabel;
use Nowo\WikiBundle\ValueObject\Uuid;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Immutable content revision for a wiki page.
 *
 * 1.11: a revision is either HTML (`format` = {@see self::FORMAT_HTML}, the Tiptap editor) or Markdown
 * (`format` = {@see self::FORMAT_MARKDOWN}): then `content_markdown` is the source and `content_html` its sanitized
 * render cache (search, AI context, attachment references and readers of the HTML keep working).
 *
 * 1.16: `content_text` is the plain text of `content_html` ({@see WikiPlainText::fromHtml()}), filled when the revision
 * is saved and by `wiki:search:backfill-text`; the search matches it, and hosts may filter on it in SQL (public API).
 *
 * 1.26: a revision outlives its author (as TimeTrack time entries do). `author_id` is nullable with `ON DELETE SET
 * NULL` (it was `NOT NULL ... ON DELETE CASCADE`, so deleting a user deleted every revision they wrote); the history
 * then shows "Former person" and keeps nothing else about them.
 *
 * Spec 234: `channel` (web | app | api | mcp) and `actor_label` (a token name or host label, at most 120
 * characters, plain text) record where the write came from, as the host's provenance provider said at write time
 * (both NULL without one). The actor label is personal data: it is never shown once the author is deleted, and
 * {@see WikiWriteProvenanceErasure} exports and erases it.
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
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

    /** 1.16: plain text of the body (null: written before 1.16 and not backfilled yet). */
    #[ORM\Column(name: 'content_text', type: 'text', nullable: true)]
    private ?string $contentText = null;

    /** Spec 234: the channel of the write ({@see WikiWriteProvenance::CHANNELS}), null when not recorded. */
    #[ORM\Column(name: 'channel', type: 'string', length: 8, nullable: true)]
    private ?string $channel = null;

    /** Spec 234: the plain-text actor label of the write (at most 120 characters), null when not recorded or erased. */
    #[ORM\Column(name: 'actor_label', type: 'string', length: 120, nullable: true)]
    private ?string $actorLabel = null;

    /**
     * Creates a revision with a generated UUID and the creation timestamp set to now.
     *
     * @param WikiPage $page page this revision belongs to
     * @param int $revisionNumber sequential revision number within the page
     * @param string $contentHtml HTML content of the revision
     * @param object|null $author application user entity (remapped at runtime via metadata listener); 1.26: null once
     *                            the host deleted the account (`ON DELETE SET NULL`); the services always pass one
     * @param string|null $contentMarkdown Markdown source (1.11); when set the revision format is Markdown and
     *                                     $contentHtml is its rendered cache
     */
    public function __construct(#[ORM\ManyToOne(targetEntity: WikiPage::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private WikiPage $page, #[ORM\Column(name: 'revision_number', type: 'integer')]
        private int $revisionNumber, #[ORM\Column(name: 'content_html', type: 'text')]
        private string $contentHtml, #[ORM\ManyToOne(targetEntity: UserInterface::class)]
        #[ORM\JoinColumn(name: 'author_id', nullable: true, onDelete: 'SET NULL')]
        private ?object $author, #[ORM\Column(name: 'content_markdown', type: 'text', nullable: true)]
        private ?string $contentMarkdown = null)
    {
        $this->id        = Uuid::generate()->toString();
        $this->createdAt = new DateTimeImmutable();
        $this->format    = $this->contentMarkdown === null ? self::FORMAT_HTML : self::FORMAT_MARKDOWN;
    }

    /**
     * Fills `content_text` when the revision is first saved (1.16; revisions are immutable, so once is enough).
     */
    #[ORM\PrePersist]
    public function fillContentText(): void
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->contentText ??= WikiPlainText::fromHtml($this->contentHtml);
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
     * Returns the plain text of the body (1.16, the `content_text` column): the text of the sanitized HTML, with no
     * markup, directive marker or attribute value. Null for a revision written before 1.16 until
     * `wiki:search:backfill-text` runs.
     *
     * @return string|null revision plain text
     */
    public function getContentText(): ?string
    {
        return $this->contentText;
    }

    /**
     * Recomputes `content_text` from `content_html` (1.16, `wiki:search:backfill-text`); the body itself never changes.
     *
     * @return bool true when the stored text changed
     */
    public function refreshContentText(): bool
    {
        $text = WikiPlainText::fromHtml($this->contentHtml);
        if ($text === $this->contentText) {
            return false;
        }
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->contentText = $text;

        return true;
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
     * Returns the application user who authored the revision; null once the host deleted that user (1.26).
     *
     * @return object|null author user entity
     */
    public function getAuthor(): ?object
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

    /**
     * Records where the write came from (spec 234). The label is normalised and cut to 120 characters again here, so a
     * value object built around the checks cannot store more.
     */
    public function recordProvenance(WikiWriteProvenance $provenance): void
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->channel = $provenance->channel;
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->actorLabel = WikiPlainLabel::normalize($provenance->actorLabel, WikiWriteProvenance::MAX_LABEL);
    }

    /**
     * The channel of the write (spec 234): web, app, api or mcp; null when not recorded.
     */
    public function getChannel(): ?string
    {
        // Audit PQ-I2: re-checked on read (a value written outside the bundle is not trusted).
        return WikiWriteProvenance::channelOrNull($this->channel);
    }

    /**
     * The plain-text actor label of the write (spec 234), null when not recorded or erased. Escape it when printing it.
     */
    public function getActorLabel(): ?string
    {
        return $this->actorLabel;
    }
}
