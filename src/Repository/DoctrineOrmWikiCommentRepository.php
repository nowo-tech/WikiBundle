<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Doctrine\WikiSavepoint;
use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentReaction;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\User\WikiUserFields;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;

use function array_chunk;
use function array_filter;
use function array_key_last;
use function array_map;
use function array_slice;
use function array_values;
use function count;
use function explode;
use function implode;
use function is_string;
use function max;
use function mb_strtolower;
use function strtr;

/**
 * Doctrine comment storage (1.16): `<prefix>_comment_threads`, `<prefix>_comments` and `<prefix>_comment_reactions`,
 * part of the schema only with `comments.enabled`. Deleting a thread or a comment deletes its children explicitly (the
 * foreign keys cascade too, but a platform without enforced keys, such as SQLite by default, would keep orphans).
 */
final readonly class DoctrineOrmWikiCommentRepository implements WikiCommentRepositoryInterface, WikiCommentAuthorRepositoryInterface, WikiCommentMentionRepositoryInterface
{
    /** Underscores in a handle up to which every `_` / `\_` spelling is queried (2^n LIKE terms). */
    private const MAX_UNDERSCORE_VARIANTS = 3;

    public function __construct(
        private EntityManagerInterface|WikiEntityManagerProvider $entityManager,
    ) {
    }

    public function threads(WikiPage $page, int $limit): array
    {
        /** @var list<WikiCommentThread> $threads */
        // 1.16.1 (audit EI2-L1): open threads first, so resolved ones never push open ones out of the window.
        $threads = $this->entityManager()->createQueryBuilder()
            ->select('t')
            ->addSelect('CASE WHEN t.resolvedAt IS NULL THEN 0 ELSE 1 END AS HIDDEN resolvedLast')
            ->from(WikiCommentThread::class, 't')
            ->where('t.page = :page')
            ->setParameter('page', $page->getId())
            ->orderBy('resolvedLast', 'ASC')
            ->addOrderBy('t.createdAt', 'ASC')
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
        $em = $this->entityManager();
        if (WikiUserFields::byId($em, WikiCommentReaction::class, 'user')) {
            // 1.33 (database.user_reference: id): every candidate re-checked byte for byte (REQ-SEC-006 items 2 and 6).
            $id = WikiUserFields::exactId($user);
            if ($id === null) {
                return null;
            }
            foreach ($em->getRepository(WikiCommentReaction::class)->findBy(['comment' => $comment->getId(), 'user' => $id, 'emoji' => $emoji]) as $candidate) {
                if (WikiUserFields::is($candidate->getUser(), $id) && $candidate->getEmoji() === $emoji) {
                    return $candidate;
                }
            }

            return null;
        }
        $reaction = $em->getRepository(WikiCommentReaction::class)->findOneBy(['comment' => $comment->getId(), 'user' => $user, 'emoji' => $emoji]);

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

    public function countThreads(WikiPage $page): int
    {
        return (int) $this->entityManager()->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(WikiCommentThread::class, 't')
            ->where('t.page = :page')
            ->setParameter('page', $page->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function byAuthor(object $user): array
    {
        $parameter = $this->userParameter(WikiComment::class, 'author', $user);
        if ($parameter === null) {
            return [];
        }
        /** @var list<WikiComment> $comments */
        $comments = $this->entityManager()->createQueryBuilder()
            ->select('c', 't')
            ->from(WikiComment::class, 'c')
            ->innerJoin('c.thread', 't')
            ->where('c.author = :user')
            ->setParameter('user', $parameter)
            ->orderBy('t.createdAt', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->addOrderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult();

        return is_string($parameter) ? array_values(array_filter($comments, static fn (WikiComment $c): bool => WikiUserFields::is($c->getAuthor(), $parameter))) : $comments;
    }

    public function eraseAuthor(object $user, bool $deleteBodies): int
    {
        $em       = $this->entityManager();
        $comments = $this->byAuthor($user);
        foreach ($comments as $comment) {
            if ($deleteBodies && !$comment->isDeleted()) {
                $comment->markDeleted();
            }
            $comment->unlinkAuthor();
        }
        // @igor-ignore - Doctrine unit of work; the entity manager is reset by Doctrine between requests.
        $em->flush();
        if (WikiUserFields::byId($em, WikiCommentReaction::class, 'user')) {
            $id = WikiUserFields::exactId($user);
            WikiSavepoint::write($em, fn () => $this->eraseStoredId($em, $id));

            return count($comments);
        }
        // 1.33.0: in a savepoint inside the application's transaction (private entity manager only).
        WikiSavepoint::write($em, static function () use ($em, $user): void {
            $em->createQueryBuilder()->delete(WikiCommentReaction::class, 'r')->where('r.user = :user')->setParameter('user', $user)->getQuery()->execute();
            foreach (['createdBy', 'resolvedBy'] as $field) {
                $em->createQueryBuilder()->update(WikiCommentThread::class, 't')->set('t.' . $field, ':none')
                    ->where('t.' . $field . ' = :user')->setParameter('none', null)->setParameter('user', $user)->getQuery()->execute();
            }
        });

        return count($comments);
    }

    public function commentsMentioning(string $handle, int $limit, ?string $afterId = null): WikiCommentMentionBatch
    {
        $query = $this->entityManager()->createQueryBuilder()
            ->select('c.id')
            ->from(WikiComment::class, 'c')
            ->where('c.deletedAt IS NULL')
            ->andWhere($this->mentionCondition('LOWER(c.body)', $handle, $parameters))
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(max(1, $limit));
        if ($afterId !== null) {
            $query->andWhere('c.id > :after')->setParameter('after', $afterId);
        }
        foreach ($parameters as $name => $value) {
            $query->setParameter($name, $value);
        }

        return $this->batch(WikiComment::class, $query->getQuery()->getSingleColumnResult());
    }

    public function threadsMentioning(string $handle, int $limit, ?string $afterId = null): WikiCommentMentionBatch
    {
        $quote  = $this->mentionCondition('LOWER(t.quote)', $handle, $parameters);
        $anchor = $this->mentionCondition('LOWER(t.anchor)', $handle, $parameters);
        $query  = $this->entityManager()->createQueryBuilder()
            ->select('t.id')
            ->from(WikiCommentThread::class, 't')
            ->where($quote . ' OR ' . $anchor)
            ->orderBy('t.id', 'ASC')
            ->setMaxResults(max(1, $limit));
        if ($afterId !== null) {
            $query->andWhere('t.id > :after')->setParameter('after', $afterId);
        }
        foreach ($parameters as $name => $value) {
            $query->setParameter($name, $value);
        }

        return $this->batch(WikiCommentThread::class, $query->getQuery()->getSingleColumnResult());
    }

    public function begin(): void
    {
        $this->entityManager()->beginTransaction();
    }

    public function commit(): void
    {
        $this->entityManager()->commit();
    }

    public function rollBack(): void
    {
        $this->entityManager()->rollback();
    }

    public function release(array $entities): void
    {
        $entityManager = $this->entityManager();
        foreach ($entities as $entity) {
            if ($entityManager->contains($entity)) {
                $entityManager->detach($entity);
            }
        }
    }

    public function deleteForPages(array $pageIds): void
    {
        if ($pageIds === []) {
            return;
        }
        $em      = $this->entityManager();
        $threads = $em->createQueryBuilder()->select('t.id')->from(WikiCommentThread::class, 't')->where('t.page IN (:pages)')->setParameter('pages', $pageIds)->getQuery()->getSingleColumnResult();
        if ($threads !== []) {
            $comments = $em->createQueryBuilder()->select('c.id')->from(WikiComment::class, 'c')->where('c.thread IN (:threads)')->setParameter('threads', $threads)->getQuery()->getSingleColumnResult();
            if ($comments !== []) {
                $em->createQueryBuilder()->delete(WikiCommentReaction::class, 'r')->where('r.comment IN (:comments)')->setParameter('comments', $comments)->getQuery()->execute();
            }
            $em->createQueryBuilder()->delete(WikiComment::class, 'c')->where('c.thread IN (:threads)')->setParameter('threads', $threads)->getQuery()->execute();
        }
        $em->createQueryBuilder()->delete(WikiCommentThread::class, 't')->where('t.page IN (:pages)')->setParameter('pages', $pageIds)->getQuery()->execute();
    }

    public function countOpenBy(WikiPage $page, object $user): int
    {
        $em = $this->entityManager();
        if (WikiUserFields::byId($em, WikiCommentThread::class, 'createdBy')) {
            // 1.33: counted in PHP after an exact re-check of each opener (REQ-SEC-006 item 2).
            $id = WikiUserFields::exactId($user);
            if ($id === null) {
                return 0;
            }
            $openers = $em->createQueryBuilder()
                ->select('t.createdBy AS opener')
                ->from(WikiCommentThread::class, 't')
                ->where('t.page = :page')
                ->andWhere('t.resolvedAt IS NULL')
                ->andWhere('t.createdBy = :user')
                ->setParameter('page', $page->getId())
                ->setParameter('user', $id)
                ->getQuery()
                ->getArrayResult();

            return count(array_filter($openers, static fn (array $row): bool => self::storedId($row['opener']) === $id));
        }

        return (int) $em->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(WikiCommentThread::class, 't')
            ->where('t.page = :page')
            ->andWhere('t.resolvedAt IS NULL')
            ->andWhere('t.createdBy = :user')
            ->setParameter('page', $page->getId())
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function reactionsBy(object $user): array
    {
        $parameter = $this->userParameter(WikiCommentReaction::class, 'user', $user);
        if ($parameter === null) {
            return [];
        }
        /** @var list<WikiCommentReaction> $reactions */
        $reactions = $this->entityManager()->createQueryBuilder()
            ->select('r', 'c', 't')
            ->from(WikiCommentReaction::class, 'r')
            ->innerJoin('r.comment', 'c')
            ->innerJoin('c.thread', 't')
            ->where('r.user = :user')
            ->setParameter('user', $parameter)
            ->orderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();

        return is_string($parameter) ? array_values(array_filter($reactions, static fn (WikiCommentReaction $r): bool => WikiUserFields::is($r->getUser(), $parameter))) : $reactions;
    }

    public function threadsBy(object $user, string $role): array
    {
        $field     = $role === 'resolvedBy' ? 'resolvedBy' : 'createdBy';
        $parameter = $this->userParameter(WikiCommentThread::class, $field, $user);
        if ($parameter === null) {
            return [];
        }
        /** @var list<WikiCommentThread> $threads */
        $threads = $this->entityManager()->createQueryBuilder()
            ->select('t')
            ->from(WikiCommentThread::class, 't')
            ->where('t.' . $field . ' = :user')
            ->setParameter('user', $parameter)
            ->orderBy('t.createdAt', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();

        if (!is_string($parameter)) {
            return $threads;
        }

        return array_values(array_filter($threads, static fn (WikiCommentThread $t): bool => WikiUserFields::is($field === 'resolvedBy' ? $t->getResolvedBy() : $t->getCreatedBy(), $parameter)));
    }

    /**
     * The query parameter of a user field (1.33): the user object for a relation; in the id mode its exact id, or null
     * when it has none (it then matches nothing). Results of an id parameter are re-checked with `===` by the caller.
     *
     * @param class-string $class the wiki entity class
     */
    private function userParameter(string $class, string $field, object $user): object|string|null
    {
        return WikiUserFields::byId($this->entityManager(), $class, $field) ? WikiUserFields::exactId($user) : $user;
    }

    /**
     * The stored id of a scalar row value of a user field (converted to a {@see WikiStoredUserRef} by its column type;
     * null for a value that identifies nobody).
     */
    private static function storedId(mixed $value): ?string
    {
        return $value instanceof WikiStoredUserRef ? $value->id : null;
    }

    /**
     * 1.33 (database.user_reference: id): the erasure of a user's reactions and thread links selects the rows first,
     * keeps those whose stored id is exactly the user's, then deletes / updates them by primary key (REQ-SEC-006 item 2):
     * a look-alike id on a folding collation is never touched.
     */
    private function eraseStoredId(EntityManagerInterface $em, ?string $id): void
    {
        if ($id === null) {
            return;
        }
        $exact = static function (array $rows) use ($id): array {
            $ids = [];
            foreach ($rows as $row) {
                if (self::storedId($row['ref']) === $id) {
                    $ids[] = (string) $row['id'];
                }
            }

            return $ids;
        };
        $reactions = $exact($em->createQueryBuilder()->select('r.id AS id', 'r.user AS ref')->from(WikiCommentReaction::class, 'r')
            ->where('r.user = :user')->setParameter('user', $id)->getQuery()->getArrayResult());
        foreach (array_chunk($reactions, 500) as $chunk) {
            $em->createQueryBuilder()->delete(WikiCommentReaction::class, 'r')->where('r.id IN (:ids)')->setParameter('ids', $chunk)->getQuery()->execute();
        }
        foreach (['createdBy', 'resolvedBy'] as $field) {
            $threads = $exact($em->createQueryBuilder()->select('t.id AS id', 't.' . $field . ' AS ref')->from(WikiCommentThread::class, 't')
                ->where('t.' . $field . ' = :user')->setParameter('user', $id)->getQuery()->getArrayResult());
            foreach (array_chunk($threads, 500) as $chunk) {
                $em->createQueryBuilder()->update(WikiCommentThread::class, 't')->set('t.' . $field, ':none')
                    ->where('t.id IN (:ids)')->setParameter('none', null)->setParameter('ids', $chunk)->getQuery()->execute();
            }
        }
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

    /**
     * The entities of the ids, in id order, telling apart those this lookup loads from those already managed (audit
     * FG2-L1: the caller's own instances, which must never be released).
     *
     * @template T of WikiComment|WikiCommentThread
     *
     * @param class-string<T> $class
     * @param array<mixed> $ids
     *
     * @return WikiCommentMentionBatch<T>
     */
    private function batch(string $class, array $ids): WikiCommentMentionBatch
    {
        if ($ids === []) {
            return new WikiCommentMentionBatch([]);
        }
        $em      = $this->entityManager();
        $managed = [];
        foreach ($ids as $id) {
            if ($em->getUnitOfWork()->tryGetById($id, $class) !== false) {
                $managed[(string) $id] = true;
            }
        }
        /** @var list<T> $entities */
        $entities = $em->createQueryBuilder()
            ->select('e')
            ->from($class, 'e')
            ->where('e.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
        $loaded = array_values(array_filter($entities, static fn (WikiComment|WikiCommentThread $entity): bool => !isset($managed[$entity->getId()])));

        return new WikiCommentMentionBatch($entities, $loaded, count($ids), (string) $ids[array_key_last($ids)]);
    }

    /**
     * DQL condition: the column contains `@handle` (lower case), each `_` of the handle stored as `_` or as the
     * Markdown-escaped `\_` (audit FG-L3: the whole handle is matched, not a prefix). Up to
     * {@see self::MAX_UNDERSCORE_VARIANTS} underscores every spelling is listed; beyond that the column must contain
     * `@` + the first underscore-free part and every other part (a superset; the caller filters with the exact regex).
     *
     * @param array<string, string>|null $parameters the LIKE parameters, added to
     *
     * @param-out array<string, string> $parameters
     */
    private function mentionCondition(string $column, string $handle, ?array &$parameters): string
    {
        $parameters ??= [];
        $parts = explode('_', strtr(mb_strtolower($handle, 'UTF-8'), ['!' => '!!', '%' => '!%']));
        if (count($parts) - 1 <= self::MAX_UNDERSCORE_VARIANTS) {
            $needles = [''];
            foreach ($parts as $index => $part) {
                $next = [];
                foreach ($needles as $needle) {
                    if ($index === 0) {
                        $next[] = $part;
                        continue;
                    }
                    $next[] = $needle . '!_' . $part;
                    $next[] = $needle . '\\!_' . $part;
                }
                $needles = $next;
            }
            $glue    = ' OR ';
            $needles = array_map(static fn (string $needle): string => '%@' . $needle . '%', $needles);
        } else {
            $needles = ['%@' . $parts[0] . '%'];
            foreach (array_slice($parts, 1) as $part) {
                if ($part !== '') {
                    $needles[] = '%' . $part . '%';
                }
            }
            $glue = ' AND ';
        }
        $conditions = [];
        foreach ($needles as $needle) {
            $name              = 'mention' . count($parameters);
            $parameters[$name] = $needle;
            $conditions[]      = $column . ' LIKE :' . $name . " ESCAPE '!'";
        }

        return '(' . implode($glue, $conditions) . ')';
    }
}
