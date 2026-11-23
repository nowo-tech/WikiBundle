<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Service\WikiPageOperationsInterface;

/**
 * 1.55: comment threads and comments by their own id, without their page (the reads behind
 * {@see WikiPageOperationsInterface::findCommentThread()} and {@see WikiPageOperationsInterface::findComment()}).
 * {@see DoctrineOrmWikiCommentRepository} implements it; a host comment repository without it answers those reads with
 * null.
 *
 * Persistence adapter of the bundle's own entities, not a host port (REQ-ARCH-001): it is entity-typed by design;
 * hosts reach wiki data through the services, the id operations and the events. Permissions are the caller's job.
 */
interface WikiCommentByIdFinderInterface
{
    /** The thread with exactly that id (re-checked byte for byte), or null. */
    public function findThreadById(string $id): ?WikiCommentThread;

    /** The comment with exactly that id (re-checked byte for byte), or null. */
    public function findCommentById(string $id): ?WikiComment;

    /**
     * 1.55.1: the first `$limit` comments of the thread with exactly that id, in thread order (position, then id).
     *
     * @return list<WikiComment>
     */
    public function findThreadComments(string $threadId, int $limit): array;
}
