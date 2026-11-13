<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\User\WikiUserId;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * {@see WikiEvents::COMMENT_AUTHOR_ERASED} (1.16.1): a user's comments were erased (`bodiesDeleted`: blanked into
 * placeholders) or unlinked, their reactions deleted and their threads and resolutions unlinked. Hosts drop their own
 * copies (search indexes, caches, notification history). Mentions of the user in other people's comments stay.
 */
final readonly class WikiCommentAuthorErasedEvent
{
    public function __construct(
        public object $user,
        public int $comments,
        public bool $bodiesDeleted,
    ) {
    }

    /**
     * 1.32: the erased author's id; null without a usable id.
     */
    public function userId(): ?string
    {
        return WikiUserId::of($this->user);
    }

    /**
     * 1.32: the erased author as a framework-free reference.
     */
    public function userRef(): WikiUserRef
    {
        return WikiUserRefs::fromUser($this->user);
    }
}
