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
 * {@see WikiEvents::COMMENT_REOPENED}, dispatched after the flush. Hosts build notifications from it (in-app, e-mail).
 *
 * The event carries the page, the thread, the comment (null for resolve / reopen and a deleted thread), the actor and
 * the handles mentioned in the body (without `@`). Never put the body into a notification as is: render it for each
 * recipient with {@see WikiCommentService::renderBody()} (per-viewer page links and mentions),
 * and check that the recipient may read the page and its comments ({@see WikiAction::ViewComments}).
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
