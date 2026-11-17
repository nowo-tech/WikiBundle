<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Port\WikiSnapshots;
use Nowo\WikiBundle\Service\WikiPageDuplicator;
use Nowo\WikiBundle\User\WikiUserId;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;

/**
 * 1.41 (audit SM-I1): dispatched after "Duplicate" wrote a copy ({@see WikiPageDuplicator}),
 * once per copy, after {@see WikiEvents::PAGE_CREATED} of that copy. The copy has the readers of its place in the tree:
 * the wiki copies no host access rule, so a host with page-level rules may copy them from the source here.
 */
final readonly class WikiPageDuplicatedEvent
{
    /**
     * @param WikiPage $source the page that was copied
     * @param WikiPage $copy the new page
     * @param object|null $actor user who duplicated it
     */
    public function __construct(
        public WikiPage $source,
        public WikiPage $copy,
        public ?object $actor = null,
    ) {
    }

    /** The actor's id (`getId()` as a string; null without an actor or an id). */
    public function actorId(): ?string
    {
        return WikiUserId::of($this->actor);
    }

    /** The id of the space of both pages. */
    public function spaceId(): string
    {
        return $this->copy->getSpace()->getId();
    }

    /** The source page as a framework-free snapshot (REQ-ARCH-001). */
    public function sourceSnapshot(): WikiPageSnapshot
    {
        return WikiSnapshots::page($this->source);
    }

    /** The copy as a framework-free snapshot (REQ-ARCH-001). */
    public function copySnapshot(): WikiPageSnapshot
    {
        return WikiSnapshots::page($this->copy);
    }
}
