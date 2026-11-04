<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentReaction;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;

/**
 * Storage of the comment threads, comments and reactions of pages (1.16, `comments.enabled`). Every lookup is scoped
 * to a page: an id of another page's thread or comment is not found.
 */
interface WikiCommentRepositoryInterface
{
    /**
     * Threads of the page, oldest first.
     *
     * @param int $limit at most this many
     *
     * @return list<WikiCommentThread>
     */
    public function threads(WikiPage $page, int $limit): array;

    /** The thread of the page with this id, or null. */
    public function thread(WikiPage $page, string $id): ?WikiCommentThread;

    /** The comment of a thread of the page with this id, or null. */
    public function comment(WikiPage $page, string $id): ?WikiComment;

    /**
     * Comments of the threads, oldest first, keyed by thread id.
     *
     * @param list<WikiCommentThread> $threads
     *
     * @return array<string, list<WikiComment>>
     */
    public function comments(array $threads): array;

    /**
     * Comments that are not deleted, per thread id (the thread's `count`).
     *
     * @param list<WikiCommentThread> $threads
     *
     * @return array<string, int>
     */
    public function commentCounts(array $threads): array;

    /**
     * Reactions to the comments.
     *
     * @param list<WikiComment> $comments
     *
     * @return list<WikiCommentReaction>
     */
    public function reactions(array $comments): array;

    /** The reaction of the user to the comment with this emoji, or null. */
    public function reaction(WikiComment $comment, object $user, string $emoji): ?WikiCommentReaction;

    /** Number of threads of the page, resolved ones included (the `threads_per_page` cap). */
    public function countThreads(WikiPage $page): int;

    /**
     * Comments the user wrote, by thread (oldest first) and position (privacy export).
     *
     * @return list<WikiComment>
     */
    public function byAuthor(object $user): array;

    /**
     * Unlinks the user from their comments, threads and resolutions, deletes their reactions and, with
     * `$deleteBodies`, blanks their comments into "Comment deleted" placeholders (privacy erasure). Flushes.
     *
     * @return int the comments of the user that were changed
     */
    public function eraseAuthor(object $user, bool $deleteBodies): int;

    /**
     * Deletes the threads, comments and reactions of these pages (children first), without relying on foreign keys:
     * the trash purge calls it, and so should a host that deletes pages or spaces itself.
     *
     * @param list<string> $pageIds
     */
    public function deleteForPages(array $pageIds): void;

    /** Number of open threads of the page started by the user (1.16.1, the `user_threads_per_page` cap). */
    public function countOpenBy(WikiPage $page, object $user): int;

    /**
     * Reactions of the user (1.16.1, privacy export).
     *
     * @return list<WikiCommentReaction>
     */
    public function reactionsBy(object $user): array;

    /**
     * Threads the user started (`createdBy`) or resolved (`resolvedBy`), oldest first (1.16.1, privacy export).
     *
     * @param 'createdBy'|'resolvedBy' $role
     *
     * @return list<WikiCommentThread>
     */
    public function threadsBy(object $user, string $role): array;

    /** Number of open (not resolved) threads of the page. */
    public function countOpen(WikiPage $page): int;

    /** Persists new entities (flushed by {@see self::flush()}). */
    public function add(WikiCommentThread|WikiComment|WikiCommentReaction $entity): void;

    /** Deletes an entity (flushed by {@see self::flush()}; a thread takes its comments and reactions with it). */
    public function remove(WikiCommentThread|WikiComment|WikiCommentReaction $entity): void;

    public function flush(): void;
}
