<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiCollabDocument;
use Nowo\WikiBundle\Entity\WikiCollabUpdate;

use function is_numeric;
use function max;

/**
 * Default {@see WikiCollabStateStoreInterface} (1.13) on the wiki entity manager: `<prefix>_page_collab_documents`
 * (one row per page) and `<prefix>_page_collab_updates` (the log; the auto-increment id is the sequence number).
 * Appends are single inserts, the seed claim is a conditional update (one winner), compaction inserts the snapshot
 * before deleting what it covers — no read-modify-write, so concurrent requests never lose an update.
 */
final readonly class DoctrineWikiCollabStateStore implements WikiCollabStateStoreInterface
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

        return new WikiCollabState($snapshot, $updates, $info->seq, $info->baseRevisionId, $info->seededAt, $info->seededBy, $info->updatedAt, $info->updateCount, $info->bytes, $info->snapshotSeq);
    }

    public function info(string $pageId): WikiCollabState
    {
        $em       = $this->em();
        $document = $em->createQueryBuilder()
            ->select('d')->from(WikiCollabDocument::class, 'd')
            ->where('d.pageId = :page')->setParameter('page', $pageId)
            ->getQuery()->setHint(Query::HINT_REFRESH, true)->getOneOrNullResult();
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

        $document = $document instanceof WikiCollabDocument ? $document : null;

        return new WikiCollabState(
            null,
            [],
            max($seq, $cover),
            $document?->getBaseRevisionId(),
            $document?->getSeededAt(),
            $document?->getSeededBy(),
            $document?->getUpdatedAt(),
            $count,
            $bytes,
            $cover,
        );
    }

    public function append(string $pageId, string $update): int
    {
        $em  = $this->em();
        $row = new WikiCollabUpdate($pageId, WikiCollabUpdate::KIND_UPDATE, $update);
        $em->persist($row);
        $em->flush();
        $em->detach($row);
        $this->touch($pageId);

        return (int) $row->getId();
    }

    public function compact(string $pageId, string $snapshot, int $upToSeq): void
    {
        $em  = $this->em();
        $row = new WikiCollabUpdate($pageId, WikiCollabUpdate::KIND_SNAPSHOT, $snapshot, $upToSeq);
        $em->persist($row);
        $em->flush();
        $em->detach($row);

        // CW-M1: the new snapshot is the only one; the updates it covers go.
        $em->createQueryBuilder()
            ->delete(WikiCollabUpdate::class, 'u')
            ->where('u.pageId = :page')
            ->andWhere("(u.kind = 'update' AND u.id <= :upTo) OR (u.kind = 'snapshot' AND u.id <> :snapshot)")
            ->setParameter('page', $pageId)
            ->setParameter('upTo', $upToSeq)
            ->setParameter('snapshot', $row->getId())
            ->getQuery()->execute();
        $this->touch($pageId);
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

    public function reset(string $pageId, ?string $baseRevisionId): void
    {
        $em = $this->em();
        $em->createQueryBuilder()
            ->delete(WikiCollabUpdate::class, 'u')
            ->where('u.pageId = :page')->setParameter('page', $pageId)
            ->getQuery()->execute();
        $this->ensureDocument($pageId);
        $em->createQueryBuilder()
            ->update(WikiCollabDocument::class, 'd')
            ->set('d.baseRevisionId', ':base')
            ->set('d.seededAt', 'NULL')
            ->set('d.seededBy', 'NULL')
            ->set('d.updatedAt', ':now')
            ->where('d.pageId = :page')
            ->setParameter('base', $baseRevisionId)
            ->setParameter('now', new DateTimeImmutable(), Types::DATETIME_IMMUTABLE)
            ->setParameter('page', $pageId)
            ->getQuery()->execute();
    }

    public function markSaved(string $pageId, string $revisionId): void
    {
        $this->ensureDocument($pageId);
        $this->em()->createQueryBuilder()
            ->update(WikiCollabDocument::class, 'd')
            ->set('d.baseRevisionId', ':base')
            ->where('d.pageId = :page')
            ->setParameter('base', $revisionId)
            ->setParameter('page', $pageId)
            ->getQuery()->execute();
    }

    public function noteCorruptReset(string $pageId, DateTimeImmutable $windowStart, int $cap): bool
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

    private function touch(string $pageId): void
    {
        $this->ensureDocument($pageId);
        $this->em()->createQueryBuilder()
            ->update(WikiCollabDocument::class, 'd')
            ->set('d.updatedAt', ':now')
            ->where('d.pageId = :page')
            ->setParameter('now', new DateTimeImmutable(), Types::DATETIME_IMMUTABLE)
            ->setParameter('page', $pageId)
            ->getQuery()->execute();
    }

    /**
     * Inserts the document row when missing; a concurrent insert of the same page is not an error.
     */
    private function ensureDocument(string $pageId): void
    {
        $em = $this->em();
        if ($em->find(WikiCollabDocument::class, $pageId) instanceof WikiCollabDocument) {
            return;
        }

        $table = $em->getClassMetadata(WikiCollabDocument::class)->getTableName();
        try {
            $em->getConnection()->insert($table, [
                'page_id'    => $pageId,
                'updated_at' => new DateTimeImmutable(),
            ], ['updated_at' => Types::DATETIME_IMMUTABLE]);
        } catch (UniqueConstraintViolationException) { // @codeCoverageIgnore
            // A concurrent request inserted it between find() and insert(): the row exists, which is all we need.
        }
    }

    private function em(): EntityManagerInterface
    {
        return $this->entityManager instanceof WikiEntityManagerProvider ? $this->entityManager->get() : $this->entityManager;
    }
}
