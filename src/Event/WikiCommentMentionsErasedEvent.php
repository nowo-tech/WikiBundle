<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

/**
 * {@see WikiEvents::COMMENT_MENTIONS_ERASED} (1.17): the `@handle` mentions of an erased person were rewritten to a
 * label in other people's comments (bodies) and thread quotes. Hosts refresh their own copies (search indexes, caches,
 * notification history) of the listed comments and threads. Dispatched after the erasure's own commit: when the caller
 * wraps the call in an outer transaction, it fires before that outer commit (audit FG2-I5).
 */
final readonly class WikiCommentMentionsErasedEvent
{
    /**
     * @param list<string> $handles the handles that were erased (without `@`)
     * @param list<string> $commentIds the comments whose body was rewritten
     * @param list<string> $threadIds the threads whose quote was rewritten
     * @param list<string> $skippedCommentIds candidate comments left untouched: their body could not be scanned (audit FG2-I1)
     * @param list<string> $skippedThreadIds candidate threads left untouched: their quote or anchor could not be scanned
     */
    public function __construct(
        public array $handles,
        public array $commentIds,
        public array $threadIds,
        public array $skippedCommentIds = [],
        public array $skippedThreadIds = [],
    ) {
    }
}
