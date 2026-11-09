<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\User\WikiUserId;

/**
 * Dispatched when a page is archived.
 */
final readonly class WikiPageArchivedEvent
{
    /**
     * Creates the event for an archived page.
     *
     * @param WikiPage $page page that was archived
     * @param object|null $actor user who archived it (1.9)
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
}
