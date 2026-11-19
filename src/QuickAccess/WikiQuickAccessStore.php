<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\QuickAccess;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageFavourite;
use Nowo\WikiBundle\Entity\WikiPageVisit;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use Nowo\WikiBundle\ValueObject\Uuid;

use function array_filter;
use function array_map;
use function array_values;
use function count;

/**
 * 1.45: the rows of favourites ({@see WikiPageFavourite}) and recent visits ({@see WikiPageVisit}).
 *
 * Writes go through DBAL inside the wiki's runner ({@see WikiTransactionRunner::runOnConnection()}): nothing is flushed,
 * so an open host entity manager is never flushed, cleared or reset by them, and a failed unit leaves nothing of its own
 * open. A unique violation of `(user_id, page_id)` is the expected "only one row" race of two requests of the same
 * person: it is answered with the domain result (already a favourite, visit already recorded), never as a conflict.
 * Lock conflicts surface as the runner's {@see TransientStorageFailure}.
 *
 * Reads are ORM queries already narrowed in SQL to the person, the given spaces and live pages (archived and trashed
 * pages never come back); the user id is compared again with `===` (a case-folding collation could match a look-alike).
 * Stateless: nothing is kept between calls (worker mode).
 */
final readonly class WikiQuickAccessStore
{
    /** Visits beyond `keep` deleted per recorded visit (each visit adds at most one row). */
    public const PRUNE_BATCH = 50;

    public function __construct(private WikiTransactionRunner $runner)
    {
    }

    /**
     * Stars a page for the person, unless they already keep `$max` favourites.
     */
    public function addFavourite(WikiPage $page, string $userId, int $max, ?DateTimeImmutable $now = null): WikiFavouriteResult
    {
        $table = $this->table(WikiPageFavourite::class);
        try {
            // @igor-ignore - the closure is one unit of work, run and released by the runner within this call; nothing is kept on the service.
            return $this->runner->runOnConnection(function () use ($page, $userId, $max, $now, $table): WikiFavouriteResult {
                $connection = $this->connection();
                if ($this->hasRow($connection, $table, $userId, $page->getId())) {
                    return WikiFavouriteResult::Unchanged;
                }
                if ($this->count($connection, $table, $userId) >= $max) {
                    return WikiFavouriteResult::LimitReached;
                }
                $connection->insert($table, [
                    'id'         => Uuid::generate()->toString(),
                    'page_id'    => $page->getId(),
                    'user_id'    => $userId,
                    'created_at' => $now ?? new DateTimeImmutable(),
                ], ['created_at' => Types::DATETIME_IMMUTABLE]);

                return WikiFavouriteResult::Added;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent request of the same person starred it first: the outcome asked for holds.
            return WikiFavouriteResult::Unchanged;
        }
    }

    /**
     * Removes a page from the person's favourites.
     */
    public function removeFavourite(WikiPage $page, string $userId): WikiFavouriteResult
    {
        $table = $this->table(WikiPageFavourite::class);

        return $this->runner->runOnConnection(fn (): WikiFavouriteResult => $this->connection()->delete($table, ['user_id' => $userId, 'page_id' => $page->getId()]) > 0
            ? WikiFavouriteResult::Removed
            : WikiFavouriteResult::Unchanged);
    }

    /** Whether the page is one of the person's favourites. */
    public function isFavourite(WikiPage $page, string $userId): bool
    {
        return $this->hasRow($this->connection(), $this->table(WikiPageFavourite::class), $userId, $page->getId());
    }

    /**
     * The person's favourite pages in the given spaces, live pages only, the newest first, at most `$limit` rows read.
     *
     * @param list<WikiSpace> $spaces the spaces the person may read
     *
     * @return list<WikiPage>
     */
    public function favouritePages(string $userId, array $spaces, int $limit): array
    {
        return $this->pages(WikiPageFavourite::class, 'createdAt', $userId, $spaces, $limit);
    }

    /**
     * Records that the person opened the page now, then deletes their visits beyond `$keep` (at most
     * {@see self::PRUNE_BATCH} per call). A unique violation (two tabs opening the same page at once) only updates the
     * time of the row the other request wrote.
     */
    public function recordVisit(WikiPage $page, string $userId, int $keep, ?DateTimeImmutable $now = null): void
    {
        $table = $this->table(WikiPageVisit::class);
        $now ??= new DateTimeImmutable();
        try {
            // @igor-ignore - the closure is one unit of work, run and released by the runner within this call; nothing is kept on the service.
            $this->runner->runOnConnection(function () use ($page, $userId, $keep, $now, $table): void {
                $connection = $this->connection();
                if ($this->touch($connection, $table, $userId, $page->getId(), $now) === 0 && !$this->hasRow($connection, $table, $userId, $page->getId())) {
                    $connection->insert($table, [
                        'id'         => Uuid::generate()->toString(),
                        'page_id'    => $page->getId(),
                        'user_id'    => $userId,
                        'visited_at' => $now,
                    ], ['visited_at' => Types::DATETIME_IMMUTABLE]);
                }
                $this->prune($connection, $table, $userId, $keep);
            });
        } catch (UniqueConstraintViolationException) {
            $this->runner->runOnConnection(fn (): int => $this->touch($this->connection(), $table, $userId, $page->getId(), $now));
        }
    }

    /**
     * The person's recently opened pages in the given spaces, live pages only, the last opened first, at most `$limit`
     * rows read.
     *
     * @param list<WikiSpace> $spaces the spaces the person may read
     *
     * @return list<WikiPage>
     */
    public function recentPages(string $userId, array $spaces, int $limit): array
    {
        return $this->pages(WikiPageVisit::class, 'visitedAt', $userId, $spaces, $limit);
    }

    /**
     * Erases every favourite and visit of a person (their right to erasure).
     *
     * @param bool $favourites erase the favourites table (`favourites.enabled`)
     * @param bool $visits erase the visits table (`recents.enabled`)
     *
     * @return int rows deleted
     */
    public function eraseUser(string $userId, bool $favourites, bool $visits): int
    {
        $tables = [];
        if ($favourites) {
            $tables[] = $this->table(WikiPageFavourite::class);
        }
        if ($visits) {
            $tables[] = $this->table(WikiPageVisit::class);
        }

        // @igor-ignore - the closure is one unit of work, run and released by the runner within this call; nothing is kept on the service.
        return $this->runner->runOnConnection(function () use ($tables, $userId): int {
            $deleted = 0;
            foreach ($tables as $table) {
                $deleted += (int) $this->connection()->delete($table, ['user_id' => $userId]);
            }

            return $deleted;
        });
    }

    /** Whether a transaction is open on the wiki's connection (a caller's unit). */
    public function inTransaction(): bool
    {
        return $this->connection()->isTransactionActive();
    }

    /**
     * @param class-string<WikiPageFavourite|WikiPageVisit> $class
     * @param list<WikiSpace> $spaces
     *
     * @return list<WikiPage>
     */
    private function pages(string $class, string $order, string $userId, array $spaces, int $limit): array
    {
        if ($spaces === [] || $limit < 1 || $userId === '') {
            return [];
        }

        /** @var list<WikiPageFavourite|WikiPageVisit> $rows */
        $rows = $this->manager()->createQueryBuilder()
            ->select('x', 'p')
            ->from($class, 'x')
            ->join('x.page', 'p')
            ->where('x.userId = :user')
            ->andWhere('p.space IN (:spaces)')
            ->andWhere('p.archivedAt IS NULL')
            ->orderBy('x.' . $order, 'DESC')
            ->addOrderBy('x.id', 'DESC')
            ->setParameter('user', $userId)
            ->setParameter('spaces', array_map(static fn (WikiSpace $space): string => $space->getId(), $spaces))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $pages = [];
        foreach ($rows as $row) {
            if ($row->getUserId() === $userId) {
                $pages[] = $row->getPage();
            }
        }

        return $pages;
    }

    private function touch(Connection $connection, string $table, string $userId, string $pageId, DateTimeImmutable $now): int
    {
        return (int) $connection->update($table, ['visited_at' => $now], ['user_id' => $userId, 'page_id' => $pageId], ['visited_at' => Types::DATETIME_IMMUTABLE]);
    }

    private function prune(Connection $connection, string $table, string $userId, int $keep): void
    {
        /** @var list<string> $stale */
        $stale = $connection->createQueryBuilder()
            ->select('id')
            ->from($table)
            ->where('user_id = :user')
            ->orderBy('visited_at', 'DESC')
            ->addOrderBy('id', 'DESC')
            ->setParameter('user', $userId)
            ->setFirstResult($keep)
            ->setMaxResults(self::PRUNE_BATCH)
            ->executeQuery()
            ->fetchFirstColumn();
        if ($stale === []) {
            return;
        }

        $connection->executeStatement('DELETE FROM ' . $table . ' WHERE id IN (?)', [array_values($stale)], [ArrayParameterType::STRING]);
    }

    private function hasRow(Connection $connection, string $table, string $userId, string $pageId): bool
    {
        /** @var list<string> $users */
        $users = $connection->createQueryBuilder()
            ->select('user_id')
            ->from($table)
            ->where('user_id = :user')
            ->andWhere('page_id = :page')
            ->setParameter('user', $userId)
            ->setParameter('page', $pageId)
            ->executeQuery()
            ->fetchFirstColumn();
        foreach ($users as $user) {
            if ($user === $userId) {
                return true;
            }
        }

        return false;
    }

    private function count(Connection $connection, string $table, string $userId): int
    {
        /** @var list<string> $users */
        $users = $connection->createQueryBuilder()
            ->select('user_id')
            ->from($table)
            ->where('user_id = :user')
            ->setParameter('user', $userId)
            ->executeQuery()
            ->fetchFirstColumn();

        return count(array_filter($users, static fn (string $user): bool => $user === $userId));
    }

    /**
     * @param class-string $class
     */
    private function table(string $class): string
    {
        return $this->manager()->getClassMetadata($class)->getTableName();
    }

    private function manager(): EntityManagerInterface
    {
        return $this->runner->manager();
    }

    private function connection(): Connection
    {
        return $this->manager()->getConnection();
    }
}
