<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
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
        $entityManager->flush();
    }

    public function remove(WikiAttachment $attachment): void
    {
        $entityManager = $this->entityManager();
        // @igor-ignore - Doctrine unit of work; the entity manager is reset by Doctrine between requests.
        $entityManager->remove($attachment);
        $entityManager->flush();
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
