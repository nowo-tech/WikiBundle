<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\QuickAccess;

use Nowo\WikiBundle\Event\WikiCommentAuthorErasedEvent;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;

/**
 * 1.45 (audit VC-3): a person's erasure through the comment service ({@see WikiEvents::COMMENT_AUTHOR_ERASED}: what
 * `wiki:comments:erase-user` and a host's own erasure flow dispatch) erases their favourites and recent visits too.
 */
final readonly class WikiQuickAccessErasureListener
{
    public function __construct(private WikiQuickAccessStore $store)
    {
    }

    public function onAuthorErased(WikiCommentAuthorErasedEvent $event): void
    {
        $userId = $event->userId();
        if ($userId !== null && WikiStoredUserRef::isValid($userId)) {
            // @igor-ignore - a write to the database through a stateless service (rows, not service state); nothing is kept between requests.
            $this->store->eraseUser($userId);
        }
    }
}
