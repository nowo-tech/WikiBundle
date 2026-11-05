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
 *
 * The lookups are paged by id (keyset): the eraser walks them in batches, so a large wiki is never loaded at once.
 */
interface WikiCommentMentionRepositoryInterface
{
    /**
     * Comments not deleted whose body may mention `@handle`: case-insensitive, each `_` of the handle stored as `_` or
     * as the Markdown-escaped `\_`. A superset is fine (the caller filters), a subset is not.
     *
     * @param string $handle the handle, without `@`
     * @param int $limit at most this many
     * @param string|null $afterId only comments whose id sorts after this one (the previous batch's last id)
     *
     * @return list<WikiComment> ordered by id
     */
    public function commentsMentioning(string $handle, int $limit, ?string $afterId = null): array;

    /**
     * Inline threads whose quote or anchor may mention `@handle` (same matching as {@see self::commentsMentioning()}).
     *
     * @param string $handle the handle, without `@`
     * @param int $limit at most this many
     * @param string|null $afterId only threads whose id sorts after this one
     *
     * @return list<WikiCommentThread> ordered by id
     */
    public function threadsMentioning(string $handle, int $limit, ?string $afterId = null): array;

    /** Starts the transaction of one erasure (every batch is flushed inside it). */
    public function begin(): void;

    /** Commits the transaction started by {@see self::begin()}. */
    public function commit(): void;

    /** Rolls back the transaction started by {@see self::begin()}: nothing of the erasure is kept. */
    public function rollBack(): void;

    /**
     * Stops tracking entities whose changes were flushed (frees memory between batches); never other entities.
     *
     * @param list<WikiComment|WikiCommentThread> $entities
     */
    public function release(array $entities): void;
}
