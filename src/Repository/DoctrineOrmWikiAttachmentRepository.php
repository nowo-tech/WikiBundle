<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Doctrine\WikiFlusher;
use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use SortDirection;

use function addcslashes;

/**
 * Doctrine ORM implementation of {@see WikiAttachmentRepositoryInterface} (1.9).
 */
final readonly class DoctrineOrmWikiAttachmentRepository implements WikiAttachmentRepositoryInterface
{
    /**
     * @param EntityManagerInterface|WikiEntityManagerProvider $entityManager entity manager or its provider
     */
    public function __construct(
        private EntityManagerInterface|WikiEntityManagerProvider $entityManager,
    ) {
    }

    public function save(WikiAttachment $attachment): void
    {
        $entityManager = $this->entityManager();
        $entityManager->persist($attachment);
        WikiFlusher::flush($entityManager);
    }

    public function remove(WikiAttachment $attachment): void
    {
        $entityManager = $this->entityManager();
        // @igor-ignore - Doctrine unit of work; the entity manager is reset by Doctrine between requests.
        $entityManager->remove($attachment);
        WikiFlusher::flush($entityManager);
    }

    public function findById(string $id): ?WikiAttachment
    {
        return $this->entityManager()->find(WikiAttachment::class, $id);
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /* @var list<WikiAttachment> */
        return $this->entityManager()->createQueryBuilder()
            ->select('a')
            ->from(WikiAttachment::class, 'a')
            ->where('a.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    public function findByPage(WikiPage $page): array
    {
        /* @var list<WikiAttachment> */
        return $this->entityManager()->createQueryBuilder()
            ->select('a')
            ->from(WikiAttachment::class, 'a')
            ->where('a.page = :page')
            ->setParameter('page', $page)
            ->orderBy('a.uploadedAt', SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    public function sumSizeBySpace(WikiSpace $space): int
    {
        return (int) $this->entityManager()->createQueryBuilder()
            ->select('COALESCE(SUM(a.size), 0)')
            ->from(WikiAttachment::class, 'a')
            ->where('a.space = :space')
            ->setParameter('space', $space)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * 1.37 (audit QN-L3): {@see self::sumSizeBySpace()} as a locking read on MySQL / MariaDB, inside a transaction:
     * it sees the latest committed uploads even when the caller's transaction (REPEATABLE READ) took its snapshot
     * earlier. PostgreSQL (no locking read with an aggregate) and SQLite read as usual: on PostgreSQL every statement of
     * a READ COMMITTED transaction sees the latest commits.
     */
    public function sumSizeBySpaceForUpdate(WikiSpace $space): int
    {
        $entityManager = $this->entityManager();
        $query         = $entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(a.size), 0)')
            ->from(WikiAttachment::class, 'a')
            ->where('a.space = :space')
            ->setParameter('space', $space)
            ->getQuery();

        return (int) $query
            ->setLockMode($entityManager->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform ? LockMode::PESSIMISTIC_READ : LockMode::NONE)
            ->getSingleScalarResult();
    }

    public function findUploadedBefore(DateTimeImmutable $before, int $limit = 500): array
    {
        /* @var list<WikiAttachment> */
        return $this->entityManager()->createQueryBuilder()
            ->select('a', 'p')
            ->from(WikiAttachment::class, 'a')
            ->innerJoin('a.page', 'p')
            ->where('a.uploadedAt < :before')
            ->setParameter('before', $before)
            ->orderBy('a.uploadedAt', SortDirection::Ascending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function existsByStorageKey(string $storageKey): bool
    {
        $count = (int) $this->entityManager()->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(WikiAttachment::class, 'a')
            ->where('a.storageKey = :key')
            ->setParameter('key', $storageKey)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function isReferencedByPageRevisions(WikiPage $page, string $needle): bool
    {
        $count = (int) $this->entityManager()->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(WikiPageRevision::class, 'r')
            ->where('r.page = :page')
            ->andWhere('r.contentHtml LIKE :needle')
            ->setParameter('page', $page)
            ->setParameter('needle', '%' . addcslashes($needle, '%_\\') . '%')
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->entityManager instanceof WikiEntityManagerProvider ? $this->entityManager->get() : $this->entityManager;
    }
}
