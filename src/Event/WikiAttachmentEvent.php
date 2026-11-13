<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\Port\WikiSnapshots;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\User\WikiUserId;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * Dispatched after an attachment was uploaded or deleted (1.9).
 */
final readonly class WikiAttachmentEvent
{
    /**
     * @param WikiAttachment $attachment the attachment (detached after a delete)
     * @param object|null $actor user who acted (null for maintenance jobs)
     */
    public function __construct(
        public WikiAttachment $attachment,
        public ?object $actor = null,
    ) {
    }

    /**
     * The actor's id (`getId()` as a string; null without an actor or an id), so listeners need not know the user
     * class (1.27).
     */
    public function actorId(): ?string
    {
        return WikiUserId::of($this->actor);
    }

    /**
     * 1.32: the attachment's id.
     */
    public function attachmentId(): string
    {
        return $this->attachment->getId();
    }

    /**
     * 1.32: the id of the attachment's space.
     */
    public function spaceId(): string
    {
        return $this->attachment->getSpace()->getId();
    }

    /**
     * 1.32: the id of the attachment's page.
     */
    public function pageId(): string
    {
        return $this->attachment->getPage()->getId();
    }

    /**
     * 1.32: the attachment's page as a snapshot.
     */
    public function pageSnapshot(): WikiPageSnapshot
    {
        return WikiSnapshots::page($this->attachment->getPage());
    }

    /**
     * 1.32: the actor as a framework-free reference; null without an actor.
     */
    public function actorRef(): ?WikiUserRef
    {
        return WikiUserRefs::fromUserOrNull($this->actor);
    }
}
