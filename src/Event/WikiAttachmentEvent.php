<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Nowo\WikiBundle\Entity\WikiAttachment;

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
}
