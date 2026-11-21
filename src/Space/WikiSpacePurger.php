<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Space;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Nowo\WikiBundle\Entity\WikiApiIdempotencyRecord;
use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\Entity\WikiCollabDocument;
use Nowo\WikiBundle\Entity\WikiCollabUpdate;
use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentReaction;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageFavourite;
use Nowo\WikiBundle\Entity\WikiPageLink;
use Nowo\WikiBundle\Entity\WikiPageReference;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiPageVisit;
use Nowo\WikiBundle\Entity\WikiRevisionLabel;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Entity\WikiTrashEntry;
use Nowo\WikiBundle\PageFile\WikiPageFile;
use Nowo\WikiBundle\PageFile\WikiPageFileExtractor;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Transaction\WikiFlushObserver;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;

use function array_fill_keys;
use function array_values;
use function count;
use function is_object;
use function spl_object_id;

/**
 * 1.50: deletes one batch of a space's pages with every wiki row that hangs on them, or — once no page is left — the
 * space row itself, in one transaction of the wiki's runner under the space lock. Internal to
 * {@see WikiSpaceLifecycle}.
 *
 * Every table is cleared explicitly (no reliance on foreign-key cascades, which SQLite does not enforce): comment
 * reactions, comments and threads, named versions, revisions, host-object links, references from and to the pages,
 * favourites, visits, trash entries, co-editing documents and log, the REST API's idempotency records of the pages,
 * attachment rows. Tables of features that are off do not exist and are skipped. The attachment files and the files the
 * revisions referred to are returned so the caller removes or reports them after the commit.
 *
 * After its deletes the batch forgets the rows it removed from the wiki's entity manager (and the rows that point at
 * them), so the next flush of that manager — the host's own, when the manager is shared — never meets a deleted row.
 * Stateless: one instance serves every request of a long-running worker.
 *
 * @internal
 */
final readonly class WikiSpacePurger
{
    /** Revisions read per round when the batch's files are listed. */
    private const REVISION_PAGE = 200;

    /**
     * @param WikiTransactionRunner $runner the wiki's runner (one transaction per batch)
     * @param array{comments: bool, trash: bool, history_timeline: bool, collaboration: bool, favourites: bool, recents: bool, backlinks: bool, api_idempotency: bool} $tables
     *                                                                                                                                                                         which optional tables exist
     * @param WikiPageFileExtractor|null $pageFiles the files of a revision (`page_files.purge_event`; null: not listed)
     * @param WikiPageRevisionRepositoryInterface|null $revisions reads the revisions whose files are listed
     * @param int $maxFiles files listed at most per batch (`page_files.max_purge_files`)
     */
    public function __construct(
        private WikiTransactionRunner $runner,
        private array $tables,
        private ?WikiPageFileExtractor $pageFiles = null,
        private ?WikiPageRevisionRepositoryInterface $revisions = null,
        private int $maxFiles = 5000,
    ) {
    }

    /**
     * One unit: under the space lock, the next `$batchSize` pages go (ordered by id); with none left, the space row goes.
     *
     * @param string $spaceId the space id, exactly
     * @param int $batchSize pages per batch (at least 1)
     */
    public function batch(string $spaceId, int $batchSize): WikiSpacePurgeBatch
    {
        // @igor-ignore - The closure runs at once inside the unit of work and is not kept: the runner holds no state.
        return $this->runner->run(function () use ($spaceId, $batchSize): WikiSpacePurgeBatch {
            if (!$this->runner->lockRow(WikiSpace::class, $spaceId)) {
                return new WikiSpacePurgeBatch([], [], [], false, true);
            }
            $manager = $this->runner->manager();
            $this->initializeProxies($manager);
            /** @var list<WikiPage> $pages */
            $pages = $manager->createQueryBuilder()
                ->select('p')
                ->from(WikiPage::class, 'p')
                ->where('IDENTITY(p.space) = :space')
                ->setParameter('space', $spaceId)
                ->orderBy('p.id', 'ASC')
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();
            $ids = [];
            foreach ($pages as $page) {
                // Exact match only: a folding collation could answer a look-alike id.
                if ($page->getSpace()->getId() === $spaceId) {
                    $ids[] = $page->getId();
                }
            }
            if ($ids === []) {
                $this->deleteSpace($manager, $spaceId);

                return new WikiSpacePurgeBatch([], [], [], false, true, null, true);
            }
            /** @var list<WikiAttachment> $attachments */
            $attachments = $manager->createQueryBuilder()
                ->select('a')
                ->from(WikiAttachment::class, 'a')
                ->where('a.page IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getResult();
            [$files, $truncated] = $this->files($pages);
            $space               = $pages[0]->getSpace();
            $this->deletePages($manager, $ids);
            $this->forget($manager, $ids, null);

            return new WikiSpacePurgeBatch($ids, $attachments, $files, $truncated, false, $space);
        });
    }

    /**
     * Audit XO: the wiki rows the manager holds only as uninitialized proxies are loaded before the deletes, so
     * {@see self::forget()} can follow their associations afterwards (a proxy of a deleted row could no longer be loaded).
     * Bounded by what the manager already holds.
     */
    private function initializeProxies(EntityManagerInterface $manager): void
    {
        $unitOfWork = $manager->getUnitOfWork();
        foreach ($unitOfWork->getIdentityMap() as $class => $entities) {
            if (!WikiFlushObserver::isWikiEntity($class)) {
                continue;
            }
            foreach ($entities as $entity) {
                if (!$unitOfWork->isUninitializedObject($entity)) {
                    continue;
                }
                try {
                    $manager->initializeObject($entity);
                } catch (EntityNotFoundException) {
                    // A proxy of a row that does not exist (any more): nothing to follow, nothing to keep.
                    $manager->detach($entity);
                }
            }
        }
    }

    /**
     * @param list<string> $ids page ids of the batch
     */
    private function deletePages(EntityManagerInterface $manager, array $ids): void
    {
        $revisions = 'SELECT xr.id FROM ' . WikiPageRevision::class . ' xr WHERE xr.page IN (:ids)';
        if ($this->tables['comments']) {
            $threads  = 'SELECT xt.id FROM ' . WikiCommentThread::class . ' xt WHERE xt.page IN (:ids)';
            $comments = 'SELECT xc.id FROM ' . WikiComment::class . ' xc WHERE xc.thread IN (' . $threads . ')';
            $this->delete($manager, WikiCommentReaction::class, 'x.comment IN (' . $comments . ')', $ids);
            $this->delete($manager, WikiComment::class, 'x.thread IN (' . $threads . ')', $ids);
            $this->delete($manager, WikiCommentThread::class, 'x.page IN (:ids)', $ids);
        }
        if ($this->tables['history_timeline']) {
            $this->delete($manager, WikiRevisionLabel::class, 'x.revision IN (' . $revisions . ')', $ids);
        }
        if ($this->tables['trash']) {
            $this->delete($manager, WikiTrashEntry::class, 'x.page IN (:ids)', $ids);
        }
        if ($this->tables['collaboration']) {
            $this->delete($manager, WikiCollabUpdate::class, 'x.pageId IN (:ids)', $ids);
            $this->delete($manager, WikiCollabDocument::class, 'x.pageId IN (:ids)', $ids);
        }
        if ($this->tables['favourites']) {
            $this->delete($manager, WikiPageFavourite::class, 'x.page IN (:ids)', $ids);
        }
        if ($this->tables['recents']) {
            $this->delete($manager, WikiPageVisit::class, 'x.page IN (:ids)', $ids);
        }
        if ($this->tables['backlinks']) {
            // References from these pages, and from other pages (other spaces too) to them.
            $this->delete($manager, WikiPageReference::class, 'x.source IN (:ids) OR x.targetPageId IN (:ids)', $ids);
        }
        if ($this->tables['api_idempotency']) {
            $this->delete($manager, WikiApiIdempotencyRecord::class, 'x.pageId IN (:ids)', $ids);
        }
        $this->delete($manager, WikiAttachment::class, 'x.page IN (:ids)', $ids);
        $this->delete($manager, WikiPageLink::class, 'x.page IN (:ids)', $ids);
        // The pointers first: a revision or a parent inside the batch would block the deletes on strict platforms.
        $manager->createQueryBuilder()->update(WikiPage::class, 'p')->set('p.currentRevision', ':none')->set('p.parent', ':none')
            ->where('p.id IN (:ids)')->setParameter('none', null)->setParameter('ids', $ids)->getQuery()->execute();
        // Pages of the space left for a later batch lose a parent that goes now.
        $manager->createQueryBuilder()->update(WikiPage::class, 'p')->set('p.parent', ':none')
            ->where('p.parent IN (:ids)')->setParameter('none', null)->setParameter('ids', $ids)->getQuery()->execute();
        $this->delete($manager, WikiPageRevision::class, 'x.page IN (:ids)', $ids);
        $this->delete($manager, WikiPage::class, 'x.id IN (:ids)', $ids);
    }

    /**
     * The last unit: what is left under the space (attachment rows of no page), then the space row.
     */
    private function deleteSpace(EntityManagerInterface $manager, string $spaceId): void
    {
        $manager->createQueryBuilder()->delete(WikiAttachment::class, 'x')->where('IDENTITY(x.space) = :space')->setParameter('space', $spaceId)->getQuery()->execute();
        $manager->createQueryBuilder()->delete(WikiSpace::class, 'x')->where('x.id = :space')->setParameter('space', $spaceId)->getQuery()->execute();
        $this->forget($manager, [], $spaceId);
    }

    /**
     * @param class-string $class
     * @param list<string> $ids
     */
    private function delete(EntityManagerInterface $manager, string $class, string $condition, array $ids): void
    {
        $manager->createQuery('DELETE FROM ' . $class . ' x WHERE ' . $condition)->setParameter('ids', $ids)->execute();
    }

    /**
     * Detaches every managed wiki row the deletes removed: the batch's pages (or the space), the rows that point at a
     * detached one (fixpoint: comment → thread → page, label → revision → page, a page whose parent went), and the rows
     * that name a removed page by id (co-editing, references to it, idempotency records).
     *
     * @param list<string> $ids page ids removed
     * @param string|null $spaceId the space removed, or null
     */
    private function forget(EntityManagerInterface $manager, array $ids, ?string $spaceId): void
    {
        $removed    = array_fill_keys($ids, true);
        $unitOfWork = $manager->getUnitOfWork();
        $gone       = [];
        do {
            $more = false;
            foreach ($unitOfWork->getIdentityMap() as $class => $entities) {
                if (!WikiFlushObserver::isWikiEntity($class)) {
                    continue;
                }
                $metadata = $manager->getClassMetadata($class);
                foreach ($entities as $entity) {
                    $oid = spl_object_id($entity);
                    if (isset($gone[$oid])) {
                        continue;
                    }
                    if ($this->namesRemoved($entity, $removed, $spaceId)) {
                        $gone[$oid] = $entity;
                        $more       = true;

                        continue;
                    }
                    foreach ($metadata->associationMappings as $field => $mapping) {
                        $target = $mapping->isToOne() ? $metadata->getFieldValue($entity, $field) : null;
                        if (is_object($target) && isset($gone[spl_object_id($target)])) {
                            $gone[$oid] = $entity;
                            $more       = true;

                            break;
                        }
                    }
                }
            }
        } while ($more);
        foreach ($gone as $entity) {
            $manager->detach($entity);
        }
    }

    /**
     * @param array<string, true> $removed
     */
    private function namesRemoved(object $entity, array $removed, ?string $spaceId): bool
    {
        return match (true) {
            $entity instanceof WikiSpace => $entity->getId() === $spaceId,
            $entity instanceof WikiPage  => isset($removed[$entity->getId()]),
            $entity instanceof WikiCollabDocument,
            $entity instanceof WikiCollabUpdate         => isset($removed[$entity->getPageId()]),
            $entity instanceof WikiPageReference        => isset($removed[$entity->getTargetPageId()]),
            $entity instanceof WikiApiIdempotencyRecord => $entity->pageId !== null && isset($removed[$entity->pageId]),
            default                                     => false,
        };
    }

    /**
     * The files every revision of the pages refers to, read before the rows go (`page_files.purge_event`), at most
     * `max_purge_files` per batch.
     *
     * @param list<WikiPage> $pages
     *
     * @return array{list<WikiPageFile>, bool} files, and whether the cap cut the list
     */
    private function files(array $pages): array
    {
        if (!$this->pageFiles instanceof WikiPageFileExtractor || !$this->revisions instanceof WikiPageRevisionRepositoryInterface) {
            return [[], false];
        }
        $files = [];
        foreach ($pages as $page) {
            $offset = 0;
            do {
                $revisions = $this->revisions->findByPage($page, self::REVISION_PAGE, $offset);
                $offset += self::REVISION_PAGE;
                foreach ($revisions as $revision) {
                    foreach ($this->pageFiles->files($revision->getContentMarkdown()) as $file) {
                        if (isset($files[$file->url])) {
                            continue;
                        }
                        if (count($files) >= $this->maxFiles) {
                            return [array_values($files), true];
                        }
                        $files[$file->url] = $file;
                    }
                }
            } while (count($revisions) === self::REVISION_PAGE);
        }

        return [array_values($files), false];
    }
}
