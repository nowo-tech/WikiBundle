<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentReaction;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Repository\WikiCommentRepositoryInterface;

/**
 * A host comment store written for 1.16.0: the base interface only, without the 1.16.1 per-author queries
 * (audit EI2-I5). Delegates to another store.
 */
final readonly class LegacyCommentRepository implements WikiCommentRepositoryInterface
{
    public function __construct(private WikiCommentRepositoryInterface $inner)
    {
    }

    public function threads(WikiPage $page, int $limit): array
    {
        return $this->inner->threads($page, $limit);
    }

    public function thread(WikiPage $page, string $id): ?WikiCommentThread
    {
        return $this->inner->thread($page, $id);
    }

    public function comment(WikiPage $page, string $id): ?WikiComment
    {
        return $this->inner->comment($page, $id);
    }

    public function comments(array $threads): array
    {
        return $this->inner->comments($threads);
    }

    public function commentCounts(array $threads): array
    {
        return $this->inner->commentCounts($threads);
    }

    public function reactions(array $comments): array
    {
        return $this->inner->reactions($comments);
    }

    public function reaction(WikiComment $comment, object $user, string $emoji): ?WikiCommentReaction
    {
        return $this->inner->reaction($comment, $user, $emoji);
    }

    public function countThreads(WikiPage $page): int
    {
        return $this->inner->countThreads($page);
    }

    public function byAuthor(object $user): array
    {
        return $this->inner->byAuthor($user);
    }

    public function eraseAuthor(object $user, bool $deleteBodies): int
    {
        return $this->inner->eraseAuthor($user, $deleteBodies);
    }

    public function deleteForPages(array $pageIds): void
    {
        $this->inner->deleteForPages($pageIds);
    }

    public function countOpen(WikiPage $page): int
    {
        return $this->inner->countOpen($page);
    }

    public function add(WikiCommentThread|WikiComment|WikiCommentReaction $entity): void
    {
        $this->inner->add($entity);
    }

    public function remove(WikiCommentThread|WikiComment|WikiCommentReaction $entity): void
    {
        $this->inner->remove($entity);
    }

    public function flush(): void
    {
        $this->inner->flush();
    }
}
