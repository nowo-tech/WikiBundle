<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

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
}
