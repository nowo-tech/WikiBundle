<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Port\WikiSnapshots;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\User\WikiUserId;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiRevisionSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * Dispatched when a page revision is saved.
 */
final readonly class WikiPageSavedEvent
{
    /**
     * Creates the event for a saved page revision.
     *
     * @param WikiPage $page page that was saved
     * @param WikiPageRevision $revision revision that was created
     * @param WikiPageRevision|null $previousRevision revision that was current before (null for a new page; 1.9)
     * @param object|null $actor user who saved it (1.9)
     * @param bool $conversion true when the revision comes from the bulk HTML → Markdown conversion
     *                         (`wiki:convert-markdown`, 1.11): nobody edited the page, so hosts should not notify
     */
    public function __construct(
        public WikiPage $page,
        public WikiPageRevision $revision,
        public ?WikiPageRevision $previousRevision = null,
        public ?object $actor = null,
        public bool $conversion = false,
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
     * 1.32: the id of the page's space.
     */
    public function spaceId(): string
    {
        return $this->page->getSpace()->getId();
    }

    /**
     * 1.32: the page's id.
     */
    public function pageId(): string
    {
        return $this->page->getId();
    }

    /**
     * 1.32: the page as a framework-free snapshot (REQ-ARCH-001).
     */
    public function pageSnapshot(): WikiPageSnapshot
    {
        return WikiSnapshots::page($this->page);
    }

    /**
     * 1.32: the id of the saved revision.
     */
    public function revisionId(): string
    {
        return $this->revision->getId();
    }

    /**
     * 1.32: the saved revision as a snapshot.
     */
    public function revisionSnapshot(): WikiRevisionSnapshot
    {
        return WikiSnapshots::revision($this->revision);
    }

    /**
     * 1.32: the id of the revision it replaced; null for a new page.
     */
    public function previousRevisionId(): ?string
    {
        return $this->previousRevision?->getId();
    }

    /**
     * 1.32: the revision it replaced as a snapshot; null for a new page.
     */
    public function previousRevisionSnapshot(): ?WikiRevisionSnapshot
    {
        return WikiSnapshots::revisionOrNull($this->previousRevision);
    }

    /**
     * 1.32: the actor as a framework-free reference; null without an actor.
     */
    public function actorRef(): ?WikiUserRef
    {
        return WikiUserRefs::fromUserOrNull($this->actor);
    }
}
