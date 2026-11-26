<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use Nowo\WikiBundle\Event\WikiCommentAuthorErasedEvent;
use Nowo\WikiBundle\Event\WikiEvents;

/**
 * 2.0.1 (Beacon 207 T030): a person's erasure through the comment service ({@see WikiEvents::COMMENT_AUTHOR_ERASED}:
 * what `wiki:comments:erase-user` and a host's own erasure flow dispatch) clears their co-editing seed claims too.
 *
 * @internal
 */
final readonly class WikiCollabSeedClaimErasureListener
{
    public function __construct(private WikiCollabSeedClaims $claims)
    {
    }

    public function onAuthorErased(WikiCommentAuthorErasedEvent $event): void
    {
        // @igor-ignore - a write to the database through a stateless service (rows, not service state); nothing is kept between requests.
        $this->claims->eraseUser($event->user);
    }
}
