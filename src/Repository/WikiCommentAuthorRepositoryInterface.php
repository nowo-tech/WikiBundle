<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Entity\WikiCommentReaction;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;

/**
 * Per-author queries of the comment store (1.16.1): the `user_threads_per_page` cap and the full privacy export.
 *
 * A separate interface, so that a host's own {@see WikiCommentRepositoryInterface} written for 1.16.0 keeps working:
 * when the store does not implement it, the per-user thread cap is not applied and the export holds comments only.
 * The bundle's Doctrine store implements both.
 */
interface WikiCommentAuthorRepositoryInterface
{
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
}
