<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentThread;

/**
 * Text lookups of the comment store for the erasure of mentions (1.17, DATA-WIKI-COMMENT-MENTION-ERASE).
 *
 * A separate interface, so that a host's own {@see WikiCommentRepositoryInterface} keeps working; without it
 * {@see WikiCommentService::eraseMentions()} refuses to run (a privacy erasure must never
 * silently do nothing). The bundle's Doctrine store implements it.
 */
interface WikiCommentMentionRepositoryInterface
{
    /**
     * Comments not deleted whose body contains the text (case-insensitive; a superset is fine, the caller filters).
     *
     * @return list<WikiComment>
     */
    public function commentsContaining(string $text): array;

    /**
     * Inline threads whose quote or anchor contains the text (case-insensitive; a superset is fine).
     *
     * @return list<WikiCommentThread>
     */
    public function threadsQuoting(string $text): array;
}
