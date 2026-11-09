<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\User\WikiUserId;

/**
 * Dispatched after a page was created (1.9).
 */
final readonly class WikiPageCreatedEvent
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
}
