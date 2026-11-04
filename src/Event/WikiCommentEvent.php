<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Enum\WikiAction;

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
}
