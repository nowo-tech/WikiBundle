<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageLink;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Entity\WikiTrashEntry;
use SortDirection;
use Throwable;

use function array_fill_keys;
use function array_map;
use function array_values;

/**
 * Doctrine trash store (1.14, T065): the `<prefix>_page_trash` table, part of the schema only with `trash.enabled`.
 */
final readonly class DoctrineOrmWikiTrashRepository implements WikiTrashRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface|WikiEntityManagerProvider $entityManager,
    ) {
    }

    public function saveBatch(array $entries): void
    {
        $entityManager = $this->entityManager();
        foreach ($entries as $entry) {
            $entityManager->persist($entry);
        }
        $entityManager->flush();
    }

    public function findRoots(WikiSpace $space): array
    {
        /* @var list<WikiTrashEntry> */
        return $this->entityManager()->createQueryBuilder()
            ->select('t', 'p')
            ->from(WikiTrashEntry::class, 't')
            ->join('t.page', 'p')
            ->where('p.space = :space')
            ->andWhere('t.root = true')
            ->setParameter('space', $space)
            ->orderBy('t.deletedAt', SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }

    public function findBatch(WikiSpace $space, string $batchId): array
    {
        /* @var list<WikiTrashEntry> */
        return $this->entityManager()->createQueryBuilder()
            ->select('t', 'p')
            ->from(WikiTrashEntry::class, 't')
            ->join('t.page', 'p')
            ->where('p.space = :space')
            ->andWhere('t.batchId = :batch')
            ->setParameter('space', $space)
            ->setParameter('batch', $batchId)
            ->orderBy('t.root', SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }

    public function trashedPageIds(WikiSpace $space): array
    {
        /** @var list<string> $ids */
        $ids = $this->entityManager()->createQueryBuilder()
            ->select('IDENTITY(t.page)')
            ->from(WikiTrashEntry::class, 't')
            ->join('t.page', 'p')
            ->where('p.space = :space')
            ->setParameter('space', $space)
            ->getQuery()
            ->getSingleColumnResult();

        return array_fill_keys($ids, true);
    }

    public function findExpiredBatches(DateTimeImmutable $before, int $limit = 100): array
    {
        /** @var list<WikiTrashEntry> $roots */
        $roots = $this->entityManager()->createQueryBuilder()
            ->select('t', 'p', 's')
            ->from(WikiTrashEntry::class, 't')
            ->join('t.page', 'p')
            ->join('p.space', 's')
            ->where('t.root = true')
            ->andWhere('t.deletedAt < :before')
            ->setParameter('before', $before, Types::DATETIME_IMMUTABLE)
            ->orderBy('t.deletedAt', SortDirection::Ascending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(static fn (WikiTrashEntry $root): array => ['batch' => $root->getBatchId(), 'space' => $root->getPage()->getSpace()], $roots);
    }

    public function removeBatch(string $batchId): void
    {
        $entityManager = $this->entityManager();
        $entityManager->createQueryBuilder()->delete(WikiTrashEntry::class, 't')
            ->where('t.batchId = :batch')->setParameter('batch', $batchId)
            ->getQuery()->execute();
        $entityManager->flush();
    }

    public function deletePages(array $pages): void
    {
        if ($pages === []) {
            return;
        }
        $entityManager = $this->entityManager();
        $ids           = array_values(array_map(static fn (WikiPage $page): string => $page->getId(), $pages));
        $entityManager->beginTransaction();
        try {
            $entityManager->createQueryBuilder()->update(WikiPage::class, 'p')->set('p.currentRevision', ':none')
                ->where('p.id IN (:ids)')->setParameter('none', null)->setParameter('ids', $ids)->getQuery()->execute();
            // Pages under a purged page that are not purged with it (e.g. restored meanwhile) move to the top level.
            $entityManager->createQueryBuilder()->update(WikiPage::class, 'p')->set('p.parent', ':none')
                ->where('p.parent IN (:ids)')->andWhere('p.id NOT IN (:ids)')->setParameter('none', null)->setParameter('ids', $ids)->getQuery()->execute();
            foreach ([WikiPageRevision::class, WikiPageLink::class, WikiTrashEntry::class] as $class) {
                $entityManager->createQueryBuilder()->delete($class, 'x')->where('x.page IN (:ids)')->setParameter('ids', $ids)->getQuery()->execute();
            }
            // Children first (a parent pointer inside the batch would block the delete on strict platforms).
            $entityManager->createQueryBuilder()->update(WikiPage::class, 'p')->set('p.parent', ':none')
                ->where('p.id IN (:ids)')->setParameter('none', null)->setParameter('ids', $ids)->getQuery()->execute();
            $entityManager->createQueryBuilder()->delete(WikiPage::class, 'p')->where('p.id IN (:ids)')->setParameter('ids', $ids)->getQuery()->execute();
            $entityManager->commit();
        } catch (Throwable $exception) {
            $entityManager->rollback();

            throw $exception;
        }
        foreach ($pages as $page) {
            $entityManager->detach($page);
        }
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->entityManager instanceof WikiEntityManagerProvider ? $this->entityManager->get() : $this->entityManager;
    }
}
