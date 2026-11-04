<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentReaction;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;

use function array_map;

/**
 * Doctrine comment storage (1.16): `<prefix>_comment_threads`, `<prefix>_comments` and `<prefix>_comment_reactions`,
 * part of the schema only with `comments.enabled`. Deleting a thread or a comment deletes its children explicitly (the
 * foreign keys cascade too, but a platform without enforced keys, such as SQLite by default, would keep orphans).
 */
final readonly class DoctrineOrmWikiCommentRepository implements WikiCommentRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface|WikiEntityManagerProvider $entityManager,
    ) {
    }

    public function threads(WikiPage $page, int $limit): array
    {
        /** @var list<WikiCommentThread> $threads */
        $threads = $this->entityManager()->createQueryBuilder()
            ->select('t')
            ->from(WikiCommentThread::class, 't')
            ->where('t.page = :page')
            ->setParameter('page', $page->getId())
            ->orderBy('t.createdAt', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $threads;
    }

    public function thread(WikiPage $page, string $id): ?WikiCommentThread
    {
        $thread = $this->entityManager()->getRepository(WikiCommentThread::class)->findOneBy(['id' => $id, 'page' => $page->getId()]);

        return $thread instanceof WikiCommentThread ? $thread : null;
    }

    public function comment(WikiPage $page, string $id): ?WikiComment
    {
        /** @var list<WikiComment> $comments */
        $comments = $this->entityManager()->createQueryBuilder()
            ->select('c', 't')
            ->from(WikiComment::class, 'c')
            ->innerJoin('c.thread', 't')
            ->where('c.id = :id')
            ->andWhere('t.page = :page')
            ->setParameter('id', $id)
            ->setParameter('page', $page->getId())
            ->getQuery()
            ->getResult();

        return $comments[0] ?? null;
    }

    public function comments(array $threads): array
    {
        if ($threads === []) {
            return [];
        }
        /** @var list<WikiComment> $comments */
        $comments = $this->entityManager()->createQueryBuilder()
            ->select('c')
            ->from(WikiComment::class, 'c')
            ->where('c.thread IN (:threads)')
            ->setParameter('threads', $this->ids($threads))
            ->orderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult();
        $byThread = [];
        foreach ($comments as $comment) {
            $byThread[$comment->getThread()->getId()][] = $comment;
        }

        return $byThread;
    }

    public function commentCounts(array $threads): array
    {
        if ($threads === []) {
            return [];
        }
        /** @var list<array{id: string, n: int|string}> $rows */
        $rows = $this->entityManager()->createQueryBuilder()
            ->select('IDENTITY(c.thread) AS id', 'COUNT(c.id) AS n')
            ->from(WikiComment::class, 'c')
            ->where('c.thread IN (:threads)')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('threads', $this->ids($threads))
            ->groupBy('c.thread')
            ->getQuery()
            ->getArrayResult();
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['id']] = (int) $row['n'];
        }

        return $counts;
    }

    public function reactions(array $comments): array
    {
        if ($comments === []) {
            return [];
        }
        /** @var list<WikiCommentReaction> $reactions */
        $reactions = $this->entityManager()->createQueryBuilder()
            ->select('r')
            ->from(WikiCommentReaction::class, 'r')
            ->where('r.comment IN (:comments)')
            ->setParameter('comments', array_map(static fn (WikiComment $c): string => $c->getId(), $comments))
            ->orderBy('r.emoji', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $reactions;
    }

    public function reaction(WikiComment $comment, object $user, string $emoji): ?WikiCommentReaction
    {
        $reaction = $this->entityManager()->getRepository(WikiCommentReaction::class)->findOneBy(['comment' => $comment->getId(), 'user' => $user, 'emoji' => $emoji]);

        return $reaction instanceof WikiCommentReaction ? $reaction : null;
    }

    public function countOpen(WikiPage $page): int
    {
        return (int) $this->entityManager()->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(WikiCommentThread::class, 't')
            ->where('t.page = :page')
            ->andWhere('t.resolvedAt IS NULL')
            ->setParameter('page', $page->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function add(WikiCommentThread|WikiComment|WikiCommentReaction $entity): void
    {
        // @igor-ignore - Doctrine unit of work; the entity manager is reset by Doctrine between requests.
        $this->entityManager()->persist($entity);
    }

    public function remove(WikiCommentThread|WikiComment|WikiCommentReaction $entity): void
    {
        $em = $this->entityManager();
        if ($entity instanceof WikiCommentThread) {
            foreach ($this->comments([$entity])[$entity->getId()] ?? [] as $comment) {
                $this->remove($comment);
            }
        }
        if ($entity instanceof WikiComment) {
            foreach ($this->reactions([$entity]) as $reaction) {
                // @igor-ignore - Doctrine unit of work; the entity manager is reset by Doctrine between requests.
                $em->remove($reaction);
            }
        }
        // @igor-ignore - Doctrine unit of work; the entity manager is reset by Doctrine between requests.
        $em->remove($entity);
    }

    public function flush(): void
    {
        $this->entityManager()->flush();
    }

    /**
     * @param list<WikiCommentThread> $threads
     *
     * @return list<string>
     */
    private function ids(array $threads): array
    {
        return array_map(static fn (WikiCommentThread $t): string => $t->getId(), $threads);
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->entityManager instanceof WikiEntityManagerProvider ? $this->entityManager->get() : $this->entityManager;
    }
}
