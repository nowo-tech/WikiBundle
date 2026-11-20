<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Doctrine\WikiFlusher;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageLink;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Entity\WikiTrashEntry;
use Nowo\WikiBundle\Transaction\WikiFlushObserver;
use SortDirection;
use Throwable;

use function array_fill_keys;
use function array_map;
use function array_values;
use function is_object;
use function spl_object_id;

/**
 * Doctrine trash store (1.14, T065): the `<prefix>_page_trash` table, part of the schema only with `trash.enabled`.
 */
final readonly class DoctrineOrmWikiTrashRepository implements WikiTrashRepositoryInterface
{
    /**
     * @param WikiCommentRepositoryInterface|null $comments 1.16: the comment store (with comments.enabled), whose rows
     *                                                      of purged pages are deleted explicitly (no reliance on FKs)
     */
    public function __construct(
        private EntityManagerInterface|WikiEntityManagerProvider $entityManager,
        private ?WikiCommentRepositoryInterface $comments = null,
    ) {
    }

    public function saveBatch(array $entries): void
    {
        $entityManager = $this->entityManager();
        foreach ($entries as $entry) {
            $entityManager->persist($entry);
        }
        WikiFlusher::flush($entityManager, $this->entityManager);
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
            ->setHint(Query::HINT_REFRESH, true)
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

    public function findExpiredBatches(DateTimeImmutable $before, int $limit = 100, int $offset = 0): array
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
            ->addOrderBy('t.batchId', SortDirection::Ascending)
            ->setFirstResult($offset)
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
        // 1.40.2 (REQ-RUNTIME-002): the DQL delete left the entries of the batch managed; a manager kept across
        // requests (no services resetter) would carry them forever.
        foreach ($entityManager->getUnitOfWork()->getIdentityMap()[WikiTrashEntry::class] ?? [] as $entry) {
            if ($entry instanceof WikiTrashEntry && $entry->getBatchId() === $batchId) {
                $entityManager->detach($entry);
            }
        }
        WikiFlusher::flush($entityManager, $this->entityManager);
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
            // 1.16 (audit EI-L5): comments go with their pages even where foreign keys are not enforced (SQLite).
            $this->comments?->deleteForPages($ids);
            foreach ([WikiPageRevision::class, WikiPageLink::class, WikiTrashEntry::class] as $class) {
                $entityManager->createQueryBuilder()->delete($class, 'x')->where('x.page IN (:ids)')->setParameter('ids', $ids)->getQuery()->execute();
            }
            // Children first (a parent pointer inside the batch would block the delete on strict platforms).
            $entityManager->createQueryBuilder()->update(WikiPage::class, 'p')->set('p.parent', ':none')
                ->where('p.id IN (:ids)')->setParameter('none', null)->setParameter('ids', $ids)->getQuery()->execute();
            $entityManager->createQueryBuilder()->delete(WikiPage::class, 'p')->where('p.id IN (:ids)')->setParameter('ids', $ids)->getQuery()->execute();
            $entityManager->commit();
        } catch (Throwable $exception) {
            WikiFlusher::abandon($entityManager);

            throw $exception;
        }
        $this->forgetPurged($entityManager, $ids);
    }

    /**
     * 1.40.2 (REQ-RUNTIME-002): after the DQL deletes, the manager forgets every wiki row they removed, not only the
     * purged pages. Up to 1.40.1 the trash entries, revisions, links and comments of the purged pages stayed managed
     * and pointing at detached pages, and the sub-pages left in place kept their old parent: the next flush on that
     * manager (the next wiki write in the same request or command, or of a worker whose manager is never cleared)
     * failed with "new entity found through WikiTrashEntry#page". A wiki row that points at a forgotten one is
     * forgotten too (comment → thread → page, label → revision → page). A page that survives the purge gets its pointer
     * to a purged page set to null in memory and in the unit of work's original data, as the DQL did in the database
     * (1.40.2, audit SH-I1: not read again, which dropped that page's other pending changes). The application's
     * entities are left as they are.
     *
     * @param list<string> $ids the purged page ids
     */
    private function forgetPurged(EntityManagerInterface $entityManager, array $ids): void
    {
        $purged     = array_fill_keys($ids, true);
        $unitOfWork = $entityManager->getUnitOfWork();
        $gone       = [];
        // Surviving pages: object id => [page, metadata, fields that point at a forgotten row].
        $unlink = [];
        do {
            $more = false;
            foreach ($unitOfWork->getIdentityMap() as $class => $entities) {
                if (!WikiFlushObserver::isWikiEntity($class)) {
                    continue;
                }
                $metadata = $entityManager->getClassMetadata($class);
                foreach ($entities as $entity) {
                    $oid = spl_object_id($entity);
                    if (isset($gone[$oid]) || isset($unlink[$oid])) {
                        continue;
                    }
                    if ($entity instanceof WikiPage && isset($purged[$entity->getId()])) {
                        $gone[$oid] = $entity;
                        $more       = true;

                        continue;
                    }
                    $fields = [];
                    foreach ($metadata->associationMappings as $field => $mapping) {
                        $target = $mapping->isToOne() ? $metadata->getFieldValue($entity, $field) : null;
                        if (is_object($target) && (isset($gone[spl_object_id($target)]) || ($target instanceof WikiPage && isset($purged[$target->getId()])))) {
                            $fields[] = $field;
                        }
                    }
                    if ($fields === []) {
                        continue;
                    }
                    if ($entity instanceof WikiPage) {
                        $unlink[$oid] = [$entity, $metadata, $fields];
                    } else {
                        $gone[$oid] = $entity;
                        $more       = true;
                    }
                }
            }
        } while ($more);
        foreach ($gone as $entity) {
            $entityManager->detach($entity);
        }
        foreach ($unlink as $oid => [$page, $metadata, $fields]) {
            foreach ($fields as $field) {
                $metadata->setFieldValue($page, $field, null);
                $unitOfWork->setOriginalEntityProperty($oid, $field, null);
            }
        }
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->entityManager instanceof WikiEntityManagerProvider ? $this->entityManager->get() : $this->entityManager;
    }
}
