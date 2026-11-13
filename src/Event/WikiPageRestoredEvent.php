<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Nowo\WikiBundle\Port\WikiSnapshots;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiRevisionSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\User\WikiUserId;

/**
 * Dispatched after an old revision was restored as a new revision (1.9).
 */
final readonly class WikiPageRestoredEvent
{
    /**
     * @param WikiPage $page the page
     * @param WikiPageRevision $restoredFrom the old revision whose title and body were copied
     * @param WikiPageRevision $revision the new revision
     * @param object|null $actor user who restored it
     */
    public function __construct(
        public WikiPage $page,
        public WikiPageRevision $restoredFrom,
        public WikiPageRevision $revision,
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
     * 1.32: the id of the new revision.
     */
    public function revisionId(): string
    {
        return $this->revision->getId();
    }

    /**
     * 1.32: the new revision as a snapshot.
     */
    public function revisionSnapshot(): WikiRevisionSnapshot
    {
        return WikiSnapshots::revision($this->revision);
    }

    /**
     * 1.32: the id of the revision whose body was restored.
     */
    public function restoredFromId(): string
    {
        return $this->restoredFrom->getId();
    }

    /**
     * 1.32: the revision whose body was restored, as a snapshot.
     */
    public function restoredFromSnapshot(): WikiRevisionSnapshot
    {
        return WikiSnapshots::revision($this->restoredFrom);
    }

    /**
     * 1.32: the actor as a framework-free reference; null without an actor.
     */
    public function actorRef(): ?WikiUserRef
    {
        return WikiUserRefs::fromUserOrNull($this->actor);
    }
}
