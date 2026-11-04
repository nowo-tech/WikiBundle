<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Nowo\WikiBundle\ValueObject\Uuid;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A comment thread of a page (1.16, `comments.enabled`): inline (anchored on a text quote, Milkdown anchor v1, stored
 * as opaque validated JSON) or page-level (no anchor). Threads belong to the page: they follow it to the trash and
 * back, and the database deletes them with it on purge (FK cascade). Resolved threads are kept until then (D-W9).
 */
#[ORM\Entity]
#[ORM\Table(name: 'wiki_comment_threads')]
#[ORM\Index(name: 'wiki_comment_thread_page', columns: ['page_id', 'resolved_at'])]
class WikiCommentThread
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(name: 'resolved_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $resolvedAt = null;

    #[ORM\ManyToOne(targetEntity: UserInterface::class)]
    #[ORM\JoinColumn(name: 'resolved_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?object $resolvedBy = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    /**
     * @param WikiPage $page the commented page
     * @param object $createdBy the user who started the thread (remapped to the user class)
     * @param string|null $anchor the normalised anchor JSON (null: a page-level thread)
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: WikiPage::class)]
        #[ORM\JoinColumn(name: 'page_id', nullable: false, onDelete: 'CASCADE')]
        private WikiPage $page,
        #[ORM\ManyToOne(targetEntity: UserInterface::class)]
        #[ORM\JoinColumn(name: 'created_by_id', nullable: true, onDelete: 'SET NULL')]
        private ?object $createdBy,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $anchor = null,
        /** Last known `quote.exact` of the anchor (text: notifications, erasure); null for a page-level thread. */
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $quote = null,
    ) {
        $this->id        = Uuid::generate()->toString();
        $this->createdAt = $this->now();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPage(): WikiPage
    {
        return $this->page;
    }

    public function getCreatedBy(): ?object
    {
        return $this->createdBy;
    }

    public function getAnchor(): ?string
    {
        return $this->anchor;
    }

    public function getQuote(): ?string
    {
        return $this->quote;
    }

    public function isPageLevel(): bool
    {
        return $this->anchor === null;
    }

    /** Replaces the anchor of an inline thread (anchor refresh) and its quote copy. */
    public function moveAnchor(string $anchor, string $quote): void
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->anchor = $anchor;
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->quote = $quote;
        $this->touch();
    }

    public function isResolved(): bool
    {
        return $this->resolvedAt instanceof DateTimeImmutable;
    }

    public function getResolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function getResolvedBy(): ?object
    {
        return $this->resolvedBy;
    }

    public function resolve(object $user): void
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->resolvedAt = $this->now();
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->resolvedBy = $user;
        $this->touch();
    }

    public function reopen(): void
    {
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->resolvedAt = null;
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->resolvedBy = null;
        $this->touch();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** Marks a change (reply, edit, reaction, resolve): `updatedAt` is the `base` of the anchor refresh. */
    public function touch(): void
    {
        $now = $this->now();
        // Whole seconds (what every platform stores) and strictly increasing, so two changes within one second still
        // make an older `base` stale after a database round trip.
        // @igor-ignore - Doctrine entity field; instance-scoped, not a shared service.
        $this->updatedAt = $now > $this->updatedAt ? $now : $this->updatedAt->modify('+1 second');
    }

    private function now(): DateTimeImmutable
    {
        $now = new DateTimeImmutable();

        return $now->setTime((int) $now->format('H'), (int) $now->format('i'), (int) $now->format('s'));
    }
}
