<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Comment;

use Countable;

use function count;

/**
 * What {@see WikiCommentService::eraseMentions()} did (1.17). Countable: the comments and threads that were rewritten.
 *
 * Skipped rows (audit FG2-I1) still hold the mention: their text could not be scanned (invalid UTF-8, or another regex
 * failure), so they were left untouched rather than overwritten. Fix or delete them by hand, then run the erasure again.
 */
final readonly class WikiCommentMentionErasure implements Countable
{
    /**
     * @param list<string> $handles the handles that were erased (without `@`)
     * @param list<string> $commentIds the comments whose body was rewritten
     * @param list<string> $threadIds the threads whose quote or anchor was rewritten
     * @param list<string> $skippedCommentIds candidate comments left untouched because their body could not be scanned
     * @param list<string> $skippedThreadIds candidate threads left untouched because their quote or anchor could not be scanned
     */
    public function __construct(
        public array $handles = [],
        public array $commentIds = [],
        public array $threadIds = [],
        public array $skippedCommentIds = [],
        public array $skippedThreadIds = [],
    ) {
    }

    /** The comments and threads that were rewritten. */
    public function count(): int
    {
        return count($this->commentIds) + count($this->threadIds);
    }

    /** The comments and threads left untouched because they could not be scanned. */
    public function skipped(): int
    {
        return count($this->skippedCommentIds) + count($this->skippedThreadIds);
    }
}
