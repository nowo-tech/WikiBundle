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
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use Nowo\WikiBundle\ValueObject\Uuid;

use function array_chunk;
use function array_keys;
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
 * Exact ids (audit VC-2, REQ-SEC-006): every row a write or read acts on is selected by the person's id, compared again
 * with `===` in PHP, and then written or deleted **by its primary key**. A case-folding or PAD SPACE collation (the
 * `utf8mb4_bin` fallback of old MySQL, where `alice` and `alice ` are equal) therefore never lets one person's write
 * touch another person's row. Reads are ORM queries narrowed in SQL to the person, the given spaces and live pages.
 * Stateless: nothing is kept between calls (worker mode).
 */
final readonly class WikiQuickAccessStore
{
    /** Stale visits deleted per recorded visit (beyond `keep`, and older than the retention). */
    public const PRUNE_BATCH = 50;

    /** Rows deleted per statement (erasure, purge). */
    public const DELETE_CHUNK = 500;

    public function __construct(private WikiTransactionRunner $runner)
    {
    }

    /**
     * Stars a page for the person (the caller checked the person's limit).
     */
    public function addFavourite(WikiPage $page, string $userId, ?DateTimeImmutable $now = null): WikiFavouriteResult
    {
        $table = $this->table(WikiPageFavourite::class);
        try {
            // @igor-ignore - the closure is one unit of work, run and released by the runner within this call; nothing is kept on the service.
            return $this->runner->runOnConnection(function () use ($page, $userId, $now, $table): WikiFavouriteResult {
                $connection = $this->connection();
                if ($this->rowIds($connection, $table, $userId, $page->getId()) !== []) {
                    return WikiFavouriteResult::Unchanged;
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

        // @igor-ignore - the closure is one unit of work, run and released by the runner within this call; nothing is kept on the service.
        return $this->runner->runOnConnection(function () use ($page, $userId, $table): WikiFavouriteResult {
            $connection = $this->connection();

            return $this->deleteIds($connection, $table, $this->rowIds($connection, $table, $userId, $page->getId())) > 0
                ? WikiFavouriteResult::Removed
                : WikiFavouriteResult::Unchanged;
        });
    }

    /**
     * Removes one of the person's favourites by its id, whatever its page (gone, archived, no longer readable): audit
     * VC-1. Another person's row and an unknown id are left alone, and the caller answers the same either way.
     *
     * @return bool whether a row was deleted
     */
    public function removeFavouriteById(string $favouriteId, string $userId): bool
    {
        $table = $this->table(WikiPageFavourite::class);

        // @igor-ignore - the closure is one unit of work, run and released by the runner within this call; nothing is kept on the service.
        return $this->runner->runOnConnection(function () use ($favouriteId, $userId, $table): bool {
            $connection = $this->connection();
            /** @var list<array{id: string, user_id: string}> $rows */
            $rows = $connection->createQueryBuilder()
                ->select('id', 'user_id')
                ->from($table)
                ->where('id = :id')
                ->setParameter('id', $favouriteId)
                ->executeQuery()
                ->fetchAllAssociative();
            $ids = [];
            foreach ($rows as $row) {
                if ($row['id'] === $favouriteId && $row['user_id'] === $userId) {
                    $ids[] = $row['id'];
                }
            }

            return $this->deleteIds($connection, $table, $ids) > 0;
        });
    }

    /** Whether the page is one of the person's favourites. */
    public function isFavourite(WikiPage $page, string $userId): bool
    {
        return $this->rowIds($this->connection(), $this->table(WikiPageFavourite::class), $userId, $page->getId()) !== [];
    }

    /**
     * The person's favourites in the given spaces, live pages only, the newest first, at most `$limit` rows read.
     *
     * @param list<string> $spaceIds the ids of the spaces the person may read
     *
     * @return list<WikiPageFavourite>
     */
    public function favourites(string $userId, array $spaceIds, int $limit): array
    {
        /** @var list<WikiPageFavourite> $rows */
        $rows = $this->rows(WikiPageFavourite::class, 'createdAt', $userId, $spaceIds, $limit, null);

        return $rows;
    }

    /**
     * Records that the person opened the page now, then deletes their visits beyond `$keep` and those older than
     * `$retentionDays` (at most {@see self::PRUNE_BATCH} per call). A unique violation (two tabs opening the same page
     * at once) only updates the time of the row the other request wrote.
     */
    public function recordVisit(WikiPage $page, string $userId, int $keep, int $retentionDays, ?DateTimeImmutable $now = null): void
    {
        $table = $this->table(WikiPageVisit::class);
        $now ??= new DateTimeImmutable();
        try {
            // @igor-ignore - the closure is one unit of work, run and released by the runner within this call; nothing is kept on the service.
            $this->runner->runOnConnection(function () use ($page, $userId, $keep, $retentionDays, $now, $table): void {
                $connection = $this->connection();
                if ($this->touch($connection, $table, $userId, $page->getId(), $now) === 0) {
                    $connection->insert($table, [
                        'id'         => Uuid::generate()->toString(),
                        'page_id'    => $page->getId(),
                        'user_id'    => $userId,
                        'visited_at' => $now,
                    ], ['visited_at' => Types::DATETIME_IMMUTABLE]);
                }
                $this->prune($connection, $table, $userId, $keep, $now->modify('-' . $retentionDays . ' days'));
            });
        } catch (UniqueConstraintViolationException) {
            // @igor-ignore - the closure is one unit of work, run and released by the runner within this call; nothing is kept on the service.
            $this->runner->runOnConnection(fn (): int => $this->touch($this->connection(), $table, $userId, $page->getId(), $now));
        }
    }

    /**
     * The person's recently opened pages in the given spaces, live pages only, opened after `$since`, the last first,
     * at most `$limit` rows read.
     *
     * @param list<string> $spaceIds the ids of the spaces the person may read
     *
     * @return list<WikiPageVisit>
     */
    public function visits(string $userId, array $spaceIds, int $limit, DateTimeImmutable $since): array
    {
        /** @var list<WikiPageVisit> $rows */
        $rows = $this->rows(WikiPageVisit::class, 'visitedAt', $userId, $spaceIds, $limit, $since);

        return $rows;
    }

    /**
     * Deletes the visits of everyone older than `$before`, at most `$batch` rows (bounded; call again for more).
     *
     * @return int rows deleted
     */
    public function purgeVisitsBefore(DateTimeImmutable $before, int $batch): int
    {
        if (!$this->tableExists(WikiPageVisit::class)) {
            return 0;
        }
        $table = $this->table(WikiPageVisit::class);

        // @igor-ignore - the closure is one unit of work, run and released by the runner within this call; nothing is kept on the service.
        return $this->runner->runOnConnection(function () use ($table, $before, $batch): int {
            $connection = $this->connection();
            /** @var list<string> $ids */
            $ids = $connection->createQueryBuilder()
                ->select('id')
                ->from($table)
                ->where('visited_at < :before')
                ->orderBy('visited_at', 'ASC')
                ->setParameter('before', $before, Types::DATETIME_IMMUTABLE)
                ->setMaxResults($batch)
                ->executeQuery()
                ->fetchFirstColumn();

            // Audit VC2-2: the time is checked again, so a visit refreshed meanwhile survives.
            return $this->deleteIds($connection, $table, $ids, $before);
        });
    }

    /**
     * Erases every favourite and visit of a person (their right to erasure), from each of the two tables that exists,
     * whether or not its feature is on now (audit VC-3).
     *
     * @return int rows deleted
     */
    public function eraseUser(string $userId): int
    {
        $tables = [];
        foreach ([WikiPageFavourite::class, WikiPageVisit::class] as $class) {
            if ($this->tableExists($class)) {
                $tables[] = $this->table($class);
            }
        }
        if ($tables === []) {
            return 0;
        }

        // @igor-ignore - the closure is one unit of work, run and released by the runner within this call; nothing is kept on the service.
        return $this->runner->runOnConnection(function () use ($tables, $userId): int {
            $connection = $this->connection();
            $deleted    = 0;
            foreach ($tables as $table) {
                $deleted += $this->deleteIds($connection, $table, $this->rowIds($connection, $table, $userId));
            }

            return $deleted;
        });
    }

    /**
     * Every favourite and visit of a person, for a privacy export (page ids and times only).
     *
     * @return array{favourites: list<array<string, string>>, visits: list<array<string, string>>}
     */
    public function exportUser(string $userId): array
    {
        $export = ['favourites' => [], 'visits' => []];
        foreach ([WikiPageFavourite::class => ['favourites', 'created_at'], WikiPageVisit::class => ['visits', 'visited_at']] as $class => [$key, $time]) {
            if (!$this->tableExists($class)) {
                continue;
            }
            /** @var list<array{user_id: string, page_id: string, at: string}> $rows */
            $rows = $this->connection()->createQueryBuilder()
                ->select('user_id', 'page_id', $time . ' AS at')
                ->from($this->table($class))
                ->where('user_id = :user')
                ->orderBy($time, 'ASC')
                ->setParameter('user', $userId)
                ->executeQuery()
                ->fetchAllAssociative();
            foreach ($rows as $row) {
                if ($row['user_id'] === $userId) {
                    $export[$key][] = ['page_id' => $row['page_id'], $time => (string) $row['at']];
                }
            }
        }

        return $export;
    }

    /** Whether a transaction is open on the wiki's connection (a caller's unit). */
    public function inTransaction(): bool
    {
        return $this->connection()->isTransactionActive();
    }

    /**
     * @param class-string<WikiPageFavourite|WikiPageVisit> $class
     * @param list<string> $spaceIds
     *
     * @return list<WikiPageFavourite|WikiPageVisit>
     */
    private function rows(string $class, string $order, string $userId, array $spaceIds, int $limit, ?DateTimeImmutable $since): array
    {
        if ($spaceIds === [] || $limit < 1 || $userId === '') {
            return [];
        }

        $query = $this->manager()->createQueryBuilder()
            ->select('x', 'p')
            ->from($class, 'x')
            ->join('x.page', 'p')
            ->where('x.userId = :user')
            ->andWhere('IDENTITY(p.space) IN (:spaces)')
            ->andWhere('p.archivedAt IS NULL')
            ->orderBy('x.' . $order, 'DESC')
            ->addOrderBy('x.id', 'DESC')
            ->setParameter('user', $userId)
            ->setParameter('spaces', $spaceIds)
            ->setMaxResults($limit);
        if ($since instanceof DateTimeImmutable) {
            $query->andWhere('x.' . $order . ' >= :since')->setParameter('since', $since, Types::DATETIME_IMMUTABLE);
        }
        /** @var list<WikiPageFavourite|WikiPageVisit> $rows */
        $rows = $query->getQuery()->getResult();

        $exact = [];
        foreach ($rows as $row) {
            if ($row->getUserId() === $userId) {
                $exact[] = $row;
            }
        }

        return $exact;
    }

    /** Sets the time of the person's own visit row (by primary key); the rows written. */
    private function touch(Connection $connection, string $table, string $userId, string $pageId, DateTimeImmutable $now): int
    {
        $written = 0;
        foreach ($this->rowIds($connection, $table, $userId, $pageId) as $id) {
            $written += (int) $connection->update($table, ['visited_at' => $now], ['id' => $id], ['visited_at' => Types::DATETIME_IMMUTABLE]);
        }

        // A row found counts as recorded even when its time did not change (MySQL reports changed rows only).
        return $written > 0 ? $written : count($this->rowIds($connection, $table, $userId, $pageId));
    }

    private function prune(Connection $connection, string $table, string $userId, int $keep, DateTimeImmutable $before): void
    {
        /** @var list<array{id: string, user_id: string, visited_at: string}> $rows */
        $rows = $connection->createQueryBuilder()
            ->select('id', 'user_id', 'visited_at')
            ->from($table)
            ->where('user_id = :user')
            ->orderBy('visited_at', 'DESC')
            ->addOrderBy('id', 'DESC')
            ->setParameter('user', $userId)
            ->executeQuery()
            ->fetchAllAssociative();
        $kept    = 0;
        $capped  = [];
        $expired = [];
        foreach ($rows as $row) {
            if ($row['user_id'] !== $userId) {
                continue;
            }
            if (new DateTimeImmutable((string) $row['visited_at']) < $before) {
                $expired[] = $row['id'];
            } elseif ($kept >= $keep) {
                $capped[] = $row['id'];
            } else {
                ++$kept;

                continue;
            }
            if (count($capped) + count($expired) >= self::PRUNE_BATCH) {
                break;
            }
        }
        $this->deleteIds($connection, $table, $capped);
        // Audit VC2-2: an expired visit is deleted only while it is still older than the retention.
        $this->deleteIds($connection, $table, $expired, $before);
    }

    /**
     * The ids of the person's rows (of one page, or all), compared exactly.
     *
     * @return list<string>
     */
    private function rowIds(Connection $connection, string $table, string $userId, ?string $pageId = null): array
    {
        $query = $connection->createQueryBuilder()
            ->select('id', 'user_id', 'page_id')
            ->from($table)
            ->where('user_id = :user')
            ->setParameter('user', $userId);
        if ($pageId !== null) {
            $query->andWhere('page_id = :page')->setParameter('page', $pageId);
        }
        /** @var list<array{id: string, user_id: string, page_id: string}> $rows */
        $rows = $query->executeQuery()->fetchAllAssociative();
        $ids  = [];
        foreach ($rows as $row) {
            if ($row['user_id'] === $userId && ($pageId === null || $row['page_id'] === $pageId)) {
                $ids[$row['id']] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * Deletes rows by primary key, in chunks; with `$visitedBefore`, only those still visited before it.
     *
     * @param list<string> $ids
     */
    private function deleteIds(Connection $connection, string $table, array $ids, ?DateTimeImmutable $visitedBefore = null): int
    {
        $deleted = 0;
        foreach (array_chunk(array_values($ids), self::DELETE_CHUNK) as $chunk) {
            $deleted += (int) ($visitedBefore instanceof DateTimeImmutable
                ? $connection->executeStatement('DELETE FROM ' . $table . ' WHERE id IN (?) AND visited_at < ?', [$chunk, $visitedBefore], [ArrayParameterType::STRING, Types::DATETIME_IMMUTABLE])
                : $connection->executeStatement('DELETE FROM ' . $table . ' WHERE id IN (?)', [$chunk], [ArrayParameterType::STRING]));
        }

        return $deleted;
    }

    /**
     * @param class-string $class
     */
    private function tableExists(string $class): bool
    {
        return $this->connection()->createSchemaManager()->tablesExist([$this->table($class)]);
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
