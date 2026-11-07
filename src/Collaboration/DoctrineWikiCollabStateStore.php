<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiCollabDocument;
use Nowo\WikiBundle\Entity\WikiCollabUpdate;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function count;
use function is_numeric;
use function max;
use function strlen;

use const PHP_INT_MAX;

/**
 * Default {@see WikiCollabStateStoreInterface} (1.13; {@see WikiCollabCountingStoreInterface} since 1.14.1: the
 * document row keeps the log's update count, bytes and sequence numbers) on the wiki entity manager: `<prefix>_page_collab_documents`
 * (one row per page) and `<prefix>_page_collab_updates` (the log; the auto-increment id is the sequence number).
 * Appends are single inserts, the seed claim is a conditional update (one winner), compaction inserts the snapshot
 * before deleting what it covers — no read-modify-write, so concurrent requests never lose an update.
 */
final readonly class DoctrineWikiCollabStateStore implements WikiCollabCountingStoreInterface, WikiCollabPagePurgeInterface, WikiCollabLockingStoreInterface
{
    /**
     * @param EntityManagerInterface|WikiEntityManagerProvider $entityManager the wiki entity manager (or its provider)
     */
    public function __construct(
        private EntityManagerInterface|WikiEntityManagerProvider $entityManager,
    ) {
    }

    public function load(string $pageId): WikiCollabState
    {
        $info = $this->info($pageId);
        if ($info->isEmpty()) {
            return $info;
        }

        /** @var list<WikiCollabUpdate> $rows */
        $rows = $this->em()->createQueryBuilder()
            ->select('u')->from(WikiCollabUpdate::class, 'u')
            ->where('u.pageId = :page')->setParameter('page', $pageId)
            ->orderBy('u.id', 'ASC')
            ->getQuery()->setHint(Query::HINT_REFRESH, true)->getResult();

        $snapshot = null;
        $updates  = [];
        foreach ($rows as $row) {
            if ($row->getKind() === WikiCollabUpdate::KIND_SNAPSHOT) {
                $snapshot = $row->getData();
            } elseif ((int) $row->getId() > $info->snapshotSeq) {
                $updates[] = $row->getData();
            }

            $this->em()->detach($row);
        }

        return new WikiCollabState($snapshot, $updates, $info->seq, $info->baseRevisionId, $info->seededAt, $info->seededBy, $info->updatedAt, $info->updateCount, $info->bytes, $info->snapshotSeq, $info->savedSeq);
    }

    public function whileLocked(string $pageId, callable $work): mixed
    {
        $em         = $this->em();
        $connection = $em->getConnection();
        $connection->beginTransaction();
        try {
            $em->createQueryBuilder()
                ->select('d.pageId')->from(WikiCollabDocument::class, 'd')
                ->where('d.pageId = :page')->setParameter('page', $pageId)
                ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->getArrayResult();
            $result = $work();
            $connection->commit();

            return $result;
        } catch (Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }
    }

    public function info(string $pageId): WikiCollabState
    {
        // 1.14.1: one read of the document row; its counters are kept by every write (no SUM over the log).
        $document = $this->em()->createQueryBuilder()
            ->select('d')->from(WikiCollabDocument::class, 'd')
            ->where('d.pageId = :page')->setParameter('page', $pageId)
            ->getQuery()->setHint(Query::HINT_REFRESH, true)->getOneOrNullResult();
        $document = $document instanceof WikiCollabDocument ? $document : null;

        return new WikiCollabState(
            null,
            [],
            max($document?->getLastSeq() ?? 0, $document?->getSnapshotSeq() ?? 0),
            $document?->getBaseRevisionId(),
            $document?->getSeededAt(),
            $document?->getSeededBy(),
            $document?->getUpdatedAt(),
            $document?->getUpdateCount() ?? 0,
            $document?->getLogBytes() ?? 0,
            $document?->getSnapshotSeq() ?? 0,
            $document?->getSavedSeq() ?? 0,
        );
    }

    /**
     * Appends under the page document's row lock (1.13.1, audit DA-L1): a purge running at the same time either sees
     * the touched document (and keeps it) or has deleted it first. In that case nothing is stored and the client gets
     * 409 `log_reset` (it reloads from the saved Markdown): replaying onto a purged log would revive an old session.
     *
     * @throws WikiCollabException 409 `log_reset` when the page's log was purged during the request
     */
    public function append(string $pageId, string $update): int
    {
        $this->appendWithin($pageId, $update, PHP_INT_MAX, PHP_INT_MAX);

        return $this->info($pageId)->seq;
    }

    /**
     * 1.14.1: the caps and the append in one transaction under the document's row lock — three statements (the
     * locked counters, the insert, the counter update) instead of two SUMs over the log per request.
     */
    public function appendWithin(string $pageId, string $update, int $maxEntries, int $maxBytes): ?string
    {
        $em         = $this->em();
        $connection = $em->getConnection();
        $connection->beginTransaction();
        try {
            // No ensureDocument() here: the token request created the document; a missing one was purged (409).
            /** @var list<array{updateCount: int|string, logBytes: int|string}> $locked */
            $locked = $em->createQueryBuilder()
                ->select('d.updateCount AS updateCount', 'd.logBytes AS logBytes')->from(WikiCollabDocument::class, 'd')
                ->where('d.pageId = :page')->setParameter('page', $pageId)
                ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->getArrayResult();
            if ($locked === []) {
                throw new WikiCollabException(Response::HTTP_CONFLICT, 'log_reset');
            }
            $refused = match (true) {
                (int) $locked[0]['updateCount'] >= $maxEntries             => 'log_full',
                (int) $locked[0]['logBytes'] + strlen($update) > $maxBytes => 'log_reset',
                default                                                    => null,
            };
            if ($refused === null) {
                $row = new WikiCollabUpdate($pageId, WikiCollabUpdate::KIND_UPDATE, $update);
                $em->persist($row);
                $em->flush();
                $em->detach($row);
                $em->createQueryBuilder()
                    ->update(WikiCollabDocument::class, 'd')
                    ->set('d.updateCount', 'd.updateCount + 1')
                    ->set('d.logBytes', 'd.logBytes + :bytes')
                    ->set('d.lastSeq', ':seq')
                    ->set('d.updatedAt', ':now')
                    ->where('d.pageId = :page')
                    ->setParameter('bytes', strlen($update))
                    ->setParameter('seq', (int) $row->getId())
                    ->setParameter('now', new DateTimeImmutable(), Types::DATETIME_IMMUTABLE)
                    ->setParameter('page', $pageId)
                    ->getQuery()->execute();
            }
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        return $refused;
    }

    /**
     * @throws WikiCollabException 409 `log_reset` when the page's log was purged meanwhile (1.14, DA-I9: as append())
     */
    public function compact(string $pageId, string $snapshot, int $upToSeq): bool
    {
        $em = $this->em();

        // CW-L4: one compaction of a page at a time (row lock on the page's document; a database lock on SQLite).
        $connection = $em->getConnection();
        $connection->beginTransaction();
        try {
            $locked = $em->createQueryBuilder()
                ->select('d.pageId')->from(WikiCollabDocument::class, 'd')
                ->where('d.pageId = :page')->setParameter('page', $pageId)
                ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->getArrayResult();
            if ($locked === []) {
                throw new WikiCollabException(Response::HTTP_CONFLICT, 'log_reset');
            }
            $before    = $this->info($pageId);
            $compacted = $upToSeq >= $before->snapshotSeq;
            if ($compacted) {
                $row = new WikiCollabUpdate($pageId, WikiCollabUpdate::KIND_SNAPSHOT, $snapshot, $upToSeq);
                $em->persist($row);
                $em->flush();
                $em->detach($row);
                $em->createQueryBuilder()
                    ->delete(WikiCollabUpdate::class, 'u')
                    ->where('u.pageId = :page')
                    ->andWhere("(u.kind = 'update' AND u.id <= :upTo) OR (u.kind = 'snapshot' AND u.id < :snapshot)")
                    ->setParameter('page', $pageId)
                    ->setParameter('upTo', $upToSeq)
                    ->setParameter('snapshot', $row->getId())
                    ->getQuery()->execute();
                $this->recount($pageId);
                // The snapshot row moves the last sequence number but adds no text: a saved log stays saved.
                if (!$before->hasUnsavedUpdates()) {
                    $em->createQueryBuilder()
                        ->update(WikiCollabDocument::class, 'd')
                        ->set('d.savedSeq', 'd.lastSeq')
                        ->where('d.pageId = :page')
                        ->setParameter('page', $pageId)
                        ->getQuery()->execute();
                }
            }

            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        return $compacted;
    }

    public function purge(DateTimeImmutable $before): int
    {
        return count($this->purgePages($before));
    }

    /**
     * Selects the stale documents with a write lock inside a transaction (1.13.1, CW-I10): an append or a compaction
     * that touches one of them waits for the purge, and a document touched before the lock is no longer selected.
     *
     * @return list<string>
     */
    public function purgePages(DateTimeImmutable $before): array
    {
        $em = $this->em();

        $em->beginTransaction();
        try {
            /** @var list<string> $pages */
            $pages = $em->createQueryBuilder()
                ->select('d.pageId')->from(WikiCollabDocument::class, 'd')
                ->where('d.updatedAt < :before')
                ->setParameter('before', $before, Types::DATETIME_IMMUTABLE)
                ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->getSingleColumnResult();
            if ($pages !== []) {
                $em->createQueryBuilder()->delete(WikiCollabUpdate::class, 'u')->where('u.pageId IN (:pages)')->setParameter('pages', $pages)->getQuery()->execute();
                $em->createQueryBuilder()->delete(WikiCollabDocument::class, 'd')->where('d.pageId IN (:pages)')->setParameter('pages', $pages)->getQuery()->execute();
            }
            // DA-L1: updates left without a document (by 1.13.0, or a race it could lose) are purged too.
            $em->createQueryBuilder()->delete(WikiCollabUpdate::class, 'u')
                ->where('u.pageId NOT IN (' . $em->createQueryBuilder()->select('d2.pageId')->from(WikiCollabDocument::class, 'd2')->getDQL() . ')')
                ->getQuery()->execute();
            $em->commit();
        } catch (Throwable $e) {
            $em->rollback();

            throw $e;
        }

        return $pages;
    }

    public function claimSeed(string $pageId, DateTimeImmutable $staleBefore, string $claimant): bool
    {
        $this->ensureDocument($pageId);
        $claimed = $this->em()->createQueryBuilder()
            ->update(WikiCollabDocument::class, 'd')
            ->set('d.seededAt', ':now')
            ->set('d.seededBy', ':claimant')
            ->set('d.updatedAt', ':now')
            ->where('d.pageId = :page')
            ->andWhere('d.seededAt IS NULL OR d.seededAt < :stale')
            ->setParameter('now', new DateTimeImmutable(), Types::DATETIME_IMMUTABLE)
            ->setParameter('claimant', $claimant)
            ->setParameter('stale', $staleBefore, Types::DATETIME_IMMUTABLE)
            ->setParameter('page', $pageId)
            ->getQuery()->execute();

        return is_numeric($claimed) && (int) $claimed === 1;
    }

    /**
     * Drops the log (audit DQ-L1): in one transaction that locks the document row first, in the same order as
     * appendWithin() and compact() (document, then updates), so an append can neither slip between the delete and the
     * counter reset nor deadlock with it.
     */
    public function reset(string $pageId, ?string $baseRevisionId): void
    {
        $this->ensureDocument($pageId);
        $em         = $this->em();
        $connection = $em->getConnection();
        $connection->beginTransaction();
        try {
            $em->createQueryBuilder()
                ->select('d.pageId')->from(WikiCollabDocument::class, 'd')
                ->where('d.pageId = :page')->setParameter('page', $pageId)
                ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->getArrayResult();
            $em->createQueryBuilder()
                ->delete(WikiCollabUpdate::class, 'u')
                ->where('u.pageId = :page')->setParameter('page', $pageId)
                ->getQuery()->execute();
            $em->createQueryBuilder()
                ->update(WikiCollabDocument::class, 'd')
                ->set('d.baseRevisionId', ':base')
                ->set('d.seededAt', 'NULL')
                ->set('d.seededBy', 'NULL')
                ->set('d.updateCount', 0)
                ->set('d.logBytes', 0)
                ->set('d.lastSeq', 0)
                ->set('d.snapshotSeq', 0)
                ->set('d.savedSeq', 0)
                ->set('d.updatedAt', ':now')
                ->where('d.pageId = :page')
                ->setParameter('base', $baseRevisionId)
                ->setParameter('now', new DateTimeImmutable(), Types::DATETIME_IMMUTABLE)
                ->setParameter('page', $pageId)
                ->getQuery()->execute();
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }
    }

    public function markSaved(string $pageId, string $revisionId): void
    {
        $this->ensureDocument($pageId);
        $this->em()->createQueryBuilder()
            ->update(WikiCollabDocument::class, 'd')
            ->set('d.baseRevisionId', ':base')
            ->set('d.savedSeq', 'd.lastSeq')
            ->where('d.pageId = :page')
            ->setParameter('base', $revisionId)
            ->setParameter('page', $pageId)
            ->getQuery()->execute();
    }

    public function noteReset(string $pageId, DateTimeImmutable $windowStart, int $cap): bool
    {
        $this->ensureDocument($pageId);
        $now = new DateTimeImmutable();
        // A new window: the first reset of it.
        $fresh = $this->em()->createQueryBuilder()
            ->update(WikiCollabDocument::class, 'd')
            ->set('d.recentResets', 1)
            ->set('d.lastResetAt', ':now')
            ->where('d.pageId = :page')
            ->andWhere('d.lastResetAt IS NULL OR d.lastResetAt < :start')
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
            ->setParameter('start', $windowStart, Types::DATETIME_IMMUTABLE)
            ->setParameter('page', $pageId)
            ->getQuery()->execute();
        if (is_numeric($fresh) && (int) $fresh === 1) {
            return true;
        }

        // The same window: allowed while under the cap.
        $counted = $this->em()->createQueryBuilder()
            ->update(WikiCollabDocument::class, 'd')
            ->set('d.recentResets', 'd.recentResets + 1')
            ->set('d.lastResetAt', ':now')
            ->where('d.pageId = :page')
            ->andWhere('d.lastResetAt >= :start')
            ->andWhere('d.recentResets < :cap')
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
            ->setParameter('start', $windowStart, Types::DATETIME_IMMUTABLE)
            ->setParameter('cap', $cap)
            ->setParameter('page', $pageId)
            ->getQuery()->execute();

        return is_numeric($counted) && (int) $counted === 1;
    }

    /**
     * Counts the page's log again after a compaction (rare): updates after the snapshot, bytes, last and covered
     * sequence numbers, written to the document row (1.14.1).
     */
    private function recount(string $pageId): void
    {
        $em = $this->em();
        /** @var list<array{kind: string, n: int|string, b: int|string|null, m: int|string|null, c: int|string|null}> $rows */
        $rows = $em->createQueryBuilder()
            ->select('u.kind AS kind', 'COUNT(u.id) AS n', 'SUM(LENGTH(u.data)) AS b', 'MAX(u.id) AS m', 'MAX(u.coversSeq) AS c')
            ->from(WikiCollabUpdate::class, 'u')
            ->where('u.pageId = :page')->setParameter('page', $pageId)
            ->groupBy('u.kind')
            ->getQuery()->getArrayResult();
        $count = 0;
        $bytes = 0;
        $seq   = 0;
        $cover = 0;
        foreach ($rows as $row) {
            $bytes += (int) $row['b'];
            $seq = max($seq, (int) $row['m']);
            if ($row['kind'] === WikiCollabUpdate::KIND_SNAPSHOT) {
                $cover = (int) $row['c'];
            } else {
                $count = (int) $row['n'];
            }
        }
        $em->createQueryBuilder()
            ->update(WikiCollabDocument::class, 'd')
            ->set('d.updateCount', ':count')
            ->set('d.logBytes', ':bytes')
            ->set('d.lastSeq', ':seq')
            ->set('d.snapshotSeq', ':cover')
            ->set('d.updatedAt', ':now')
            ->where('d.pageId = :page')
            ->setParameter('count', $count)
            ->setParameter('bytes', $bytes)
            ->setParameter('seq', $seq)
            ->setParameter('cover', $cover)
            ->setParameter('now', new DateTimeImmutable(), Types::DATETIME_IMMUTABLE)
            ->setParameter('page', $pageId)
            ->getQuery()->execute();
    }

    /**
     * Inserts the document row when missing, race-safe without a failing statement (1.13.1, DA-L1): a failed INSERT
     * would abort an enclosing PostgreSQL transaction, so the platform's "insert or ignore" form is used (no identity
     * map lookup either: a purged row may still be cached there).
     */
    private function ensureDocument(string $pageId): void
    {
        $em         = $this->em();
        $connection = $em->getConnection();
        $table      = $em->getClassMetadata(WikiCollabDocument::class)->getTableName();
        $sql        = self::insertIgnoreSql($connection->getDatabasePlatform(), $connection->quoteIdentifier($table));
        if ($sql !== null) {
            $connection->executeStatement($sql, [$pageId, new DateTimeImmutable()], [Types::STRING, Types::DATETIME_IMMUTABLE]);

            return;
        }

        // @codeCoverageIgnoreStart
        try {
            $connection->insert($table, ['page_id' => $pageId, 'updated_at' => new DateTimeImmutable()], ['updated_at' => Types::DATETIME_IMMUTABLE]);
        } catch (UniqueConstraintViolationException) {
            // Another platform: a concurrent insert of the same page is not an error.
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * `INSERT … ON CONFLICT DO NOTHING` (PostgreSQL), `INSERT IGNORE` (MySQL / MariaDB), `INSERT OR IGNORE` (SQLite) for
     * a document row, or null for another platform.
     */
    public static function insertIgnoreSql(AbstractPlatform $platform, string $table): ?string
    {
        $columns = ' (page_id, updated_at) VALUES (?, ?)';

        return match (true) {
            $platform instanceof PostgreSQLPlatform    => 'INSERT INTO ' . $table . $columns . ' ON CONFLICT (page_id) DO NOTHING',
            $platform instanceof AbstractMySQLPlatform => 'INSERT IGNORE INTO ' . $table . $columns,
            $platform instanceof SQLitePlatform        => 'INSERT OR IGNORE INTO ' . $table . $columns,
            default                                    => null,
        };
    }

    private function em(): EntityManagerInterface
    {
        return $this->entityManager instanceof WikiEntityManagerProvider ? $this->entityManager->get() : $this->entityManager;
    }
}
