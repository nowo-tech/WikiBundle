<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\ValueObject\Uuid;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * One comment of a thread (1.16): the body is Markdown in the page dialect (at most
 * {@see WikiCommentService::MAX_BODY_CHARS} characters), rendered per viewer with the safe
 * renderer. A deleted comment that replies still follow stays as a "Comment deleted" placeholder (body erased).
 */
#[ORM\Entity]
#[ORM\Table(name: 'wiki_comments')]
#[ORM\Index(name: 'wiki_comment_thread_position', columns: ['thread_id', 'position'])]
class WikiComment
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'edited_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $editedAt = null;

    #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $deletedAt = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: WikiCommentThread::class)]
        #[ORM\JoinColumn(name: 'thread_id', nullable: false, onDelete: 'CASCADE')]
        private WikiCommentThread $thread,
        #[ORM\ManyToOne(targetEntity: UserInterface::class)]
        #[ORM\JoinColumn(name: 'author_id', nullable: true, onDelete: 'SET NULL')]
        private ?object $author,
        #[ORM\Column(type: 'text')]
        private string $body,
        /** Order in the thread (0 for the first comment). */
        #[ORM\Column(type: 'integer')]
        private int $position = 0,
    ) {
        $this->id        = Uuid::generate()->toString();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getThread(): WikiCommentThread
    {
        return $this->thread;
    }

    /** The author (null when the account was deleted). */
    public function getAuthor(): ?object
    {
        return $this->author;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function edit(string $body): void
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->body = $body;
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->editedAt = new DateTimeImmutable();
    }

    /** Erases the body and keeps a placeholder (replies follow it). */
    public function markDeleted(): void
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->body = '';
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->deletedAt = new DateTimeImmutable();
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt instanceof DateTimeImmutable;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEditedAt(): ?DateTimeImmutable
    {
        return $this->editedAt;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }
}
