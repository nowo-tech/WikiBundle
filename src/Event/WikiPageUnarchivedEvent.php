<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Port\WikiSnapshots;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\User\WikiUserId;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * Dispatched after a page was unarchived (1.9).
 */
final readonly class WikiPageUnarchivedEvent
{
    /**
     * @param WikiPage $page the page (its space is {@see WikiPage::getSpace()})
     * @param object|null $actor user who acted
     */
    public function __construct(
        public WikiPage $page,
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
     * 1.34: the id of the page's space.
     */
    public function spaceId(): string
    {
        return $this->page->getSpace()->getId();
    }

    /**
     * 1.34: the page's id.
     */
    public function pageId(): string
    {
        return $this->page->getId();
    }

    /**
     * 1.34: the page as a framework-free snapshot (REQ-ARCH-001).
     */
    public function pageSnapshot(): WikiPageSnapshot
    {
        return WikiSnapshots::page($this->page);
    }

    /**
     * 1.34: the actor as a framework-free reference; null without an actor.
     */
    public function actorRef(): ?WikiUserRef
    {
        return WikiUserRefs::fromUserOrNull($this->actor);
    }
}
