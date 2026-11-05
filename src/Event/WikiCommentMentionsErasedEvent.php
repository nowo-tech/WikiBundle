<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

/**
 * {@see WikiEvents::COMMENT_MENTIONS_ERASED} (1.17): the `@handle` mentions of an erased person were rewritten to a
 * label in other people's comments (bodies) and thread quotes. Hosts refresh their own copies (search indexes, caches,
 * notification history) of the listed comments and threads.
 */
final readonly class WikiCommentMentionsErasedEvent
{
    /**
     * @param list<string> $handles the handles that were erased (without `@`)
     * @param list<string> $commentIds the comments whose body was rewritten
     * @param list<string> $threadIds the threads whose quote was rewritten
     */
    public function __construct(
        public array $handles,
        public array $commentIds,
        public array $threadIds,
    ) {
    }
}
