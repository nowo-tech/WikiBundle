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
        $em       = $this->em();
        $document = $em->createQueryBuilder()
            ->select('d')->from(WikiCollabDocument::class, 'd')
            ->where('d.pageId = :page')->setParameter('page', $pageId)
            ->getQuery()->setHint(Query::HINT_REFRESH, true)->getOneOrNullResult();
        /** @var list<WikiCollabUpdate> $rows */
        $rows = $em->createQueryBuilder()
            ->select('u')->from(WikiCollabUpdate::class, 'u')
            ->where('u.pageId = :page')->setParameter('page', $pageId)
            ->orderBy('u.id', 'ASC')
            ->getQuery()->setHint(Query::HINT_REFRESH, true)->getResult();

        $snapshot = null;
        $covers   = 0;
        $seq      = 0;
        foreach ($rows as $row) {
            $seq = max($seq, (int) $row->getId());
            if ($row->getKind() === WikiCollabUpdate::KIND_SNAPSHOT && (int) $row->getCoversSeq() >= $covers) {
                $snapshot = $row->getData();
                $covers   = (int) $row->getCoversSeq();
            }
        }

        $updates = [];
        foreach ($rows as $row) {
            if ($row->getKind() === WikiCollabUpdate::KIND_UPDATE && (int) $row->getId() > $covers) {
                $updates[] = $row->getData();
            }
        }

        return new WikiCollabState(
            $snapshot,
            $updates,
            max($seq, $covers),
            $document instanceof WikiCollabDocument ? $document->getBaseRevisionId() : null,
            $document instanceof WikiCollabDocument && $document->getSeededAt() instanceof DateTimeImmutable,
            $document instanceof WikiCollabDocument ? $document->getUpdatedAt() : null,
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

        $em->createQueryBuilder()
            ->delete(WikiCollabUpdate::class, 'u')
            ->where('u.pageId = :page')
            ->andWhere("(u.kind = 'update' AND u.id <= :upTo) OR (u.kind = 'snapshot' AND u.coversSeq <= :upTo AND u.id <> :snapshot)")
            ->setParameter('page', $pageId)
            ->setParameter('upTo', $upToSeq)
            ->setParameter('snapshot', $row->getId())
            ->getQuery()->execute();
        $this->touch($pageId);
    }

    public function claimSeed(string $pageId, DateTimeImmutable $staleBefore): bool
    {
        $this->ensureDocument($pageId);
        $claimed = $this->em()->createQueryBuilder()
            ->update(WikiCollabDocument::class, 'd')
            ->set('d.seededAt', ':now')
            ->set('d.updatedAt', ':now')
            ->where('d.pageId = :page')
            ->andWhere('d.seededAt IS NULL OR d.seededAt < :stale')
            ->setParameter('now', new DateTimeImmutable(), Types::DATETIME_IMMUTABLE)
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
