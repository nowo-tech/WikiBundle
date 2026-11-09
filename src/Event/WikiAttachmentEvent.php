<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\User\WikiUserId;

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
}
