<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Port\WikiSnapshots;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\User\WikiUserId;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * A comment change (1.16): {@see WikiEvents::COMMENT_CREATED} (a new thread), {@see WikiEvents::COMMENT_REPLIED},
 * {@see WikiEvents::COMMENT_EDITED}, {@see WikiEvents::COMMENT_DELETED}, {@see WikiEvents::COMMENT_RESOLVED} or
 * {@see WikiEvents::COMMENT_REOPENED}, or (1.16.1) {@see WikiEvents::COMMENT_THREAD_DELETED}, dispatched after the
 * flush. Hosts build notifications from it (in-app, e-mail).
 *
 * The event carries the page, the thread, the comment (null for resolve / reopen and a deleted thread), the actor and
 * the handles mentioned in the body (without `@`). Listeners must:
 *
 * - check that each recipient may read the page and its comments ({@see WikiAction::ViewComments});
 * - render the body with {@see WikiCommentService::renderBodyForNotification()} (viewer-neutral: a listener runs in the
 *   actor's request, whose rights are not the recipient's), never print it as is;
 * - show the quote with {@see WikiCommentService::visibleQuote()} (1.16.1, audit EI-I15), never
 *   `$thread->getQuote()`, which keeps text that may have left the page since;
 * - not serialise the event (it carries entities; the comment holds its body): copy the ids they need.
 */
final readonly class WikiCommentEvent
{
    /**
     * @param list<string> $mentions mentioned handles (distinct, without `@`)
     */
    public function __construct(
        public WikiPage $page,
        public WikiCommentThread $thread,
        public ?WikiComment $comment,
        public object $actor,
        public array $mentions = [],
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
     * 1.34: the id of the page's space.
     */
    public function spaceId(): string
    {
        return $this->page->getSpace()->getId();
    }

    /**
     * 1.34: the page's id.
     */
    public function pageId(): string
    {
        return $this->page->getId();
    }

    /**
     * 1.34: the page as a framework-free snapshot (REQ-ARCH-001).
     */
    public function pageSnapshot(): WikiPageSnapshot
    {
        return WikiSnapshots::page($this->page);
    }

    /**
     * 1.34: the thread's id.
     */
    public function threadId(): string
    {
        return $this->thread->getId();
    }

    /**
     * 1.34: the comment's id; null for a thread-level change.
     */
    public function commentId(): ?string
    {
        return $this->comment?->getId();
    }

    /**
     * 1.34: the actor as a framework-free reference.
     */
    public function actorRef(): WikiUserRef
    {
        return WikiUserRefs::fromUser($this->actor);
    }
}
