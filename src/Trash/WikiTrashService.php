<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Trash;

use DateTimeImmutable;
use Nowo\WikiBundle\Attachment\WikiAttachmentStorageInterface;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Entity\WikiTrashEntry;
use Nowo\WikiBundle\Event\WikiAttachmentEvent;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiTrashEvent;
use Nowo\WikiBundle\Repository\WikiAttachmentRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageTreeWriterInterface;
use Nowo\WikiBundle\Repository\WikiTrashRepositoryInterface;
use Nowo\WikiBundle\ValueObject\Uuid;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

use function array_filter;
use function array_map;
use function array_shift;
use function array_values;
use function count;

/**
 * The trash of a space (1.14, T065, `trash.enabled`).
 *
 * - **Delete** moves a page and **its whole sub-tree** to the trash in one batch: every page of it is archived, so
 *   every read path (tree, search, links, mentions, backlinks, export, AI) treats it as gone; pages archived before
 *   stay archived after a restore. The page the user deleted is the batch root.
 * - **Restore** brings the batch back together; a root whose parent is gone meanwhile goes to the top level.
 * - **Purge** deletes the batch for good: pages, revisions, host-object links, attachment rows (their files after the
 *   commit). Pages
 *   left under a purged page move to the top level. The retention (`trash.retention_days`) purges older batches
 *   through `wiki:trash:purge`.
 *
 * Deletions, restores and purges run under the space lock of the page tree, like moves (audit DO-M1).
 */
final readonly class WikiTrashService
{
    /** Expired deletions read per round of the retention. */
    private const EXPIRED_PAGE = 100;

    /** Pages read at most for one deletion (the space's pages, archived included). */
    private const MAX_PAGES = 100000;

    public function __construct(
        private WikiPageRepositoryInterface $pages,
        private WikiTrashRepositoryInterface $trash,
        private WikiPageTreeWriterInterface $writer,
        private EventDispatcherInterface $dispatcher,
        private ?WikiAttachmentStorageInterface $storage = null,
        private ?WikiAttachmentRepositoryInterface $attachmentRepository = null,
        private int $retentionDays = 30,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /** Days a deletion stays in the trash before `wiki:trash:purge` removes it. */
    public function retentionDays(): int
    {
        return $this->retentionDays;
    }

    /**
     * Moves the page and its sub-pages to the trash.
     *
     * @return string the batch id
     */
    public function delete(WikiPage $page, ?object $actor = null): string
    {
        $space = $page->getSpace();
        $batch = Uuid::generate()->toString();
        $this->writer->beginSpaceTransaction($space);
        try {
            $trashed  = $this->trash->trashedPageIds($space);
            $children = [];
            foreach ([...$this->pages->findActiveBySpace($space), ...$this->pages->findArchivedBySpace($space, self::MAX_PAGES)] as $candidate) {
                if (($parentId = $candidate->getParent()?->getId()) !== null && !isset($trashed[$candidate->getId()])) {
                    $children[$parentId][] = $candidate;
                }
            }
            $now     = new DateTimeImmutable();
            $entries = [];
            $queue   = [$page];
            $seen    = [];
            while ($queue !== []) {
                $node = array_shift($queue);
                if (isset($seen[$node->getId()])) {
                    continue;
                }
                $seen[$node->getId()] = true;
                $entries[]            = new WikiTrashEntry($node, $batch, $node === $page, $node->isArchived(), $now);
                if (!$node->isArchived()) {
                    $node->archive();
                }
                foreach ($children[$node->getId()] ?? [] as $child) {
                    $queue[] = $child;
                }
            }
            $this->trash->saveBatch($entries);
            $this->writer->commitSpaceTransaction();
        } catch (Throwable $exception) {
            $this->writer->rollbackSpaceTransaction();

            throw $exception;
        }

        $ids = array_map(static fn (WikiTrashEntry $e): string => $e->getPage()->getId(), $entries);
        $this->logger->info('Wiki page moved to the trash.', ['bundle' => 'nowo_wiki', 'action' => 'trash', 'space' => $space->getSlug(), 'page' => $page->getSlug(), 'pages' => count($ids)]);
        $this->dispatcher->dispatch(new WikiTrashEvent($space, $batch, $page->getTitle(), $ids, $actor), WikiEvents::PAGE_TRASHED);

        return $batch;
    }

    /**
     * Restores a deletion of the space, under the space lock (audit DO-M1 / DO-I5): the batch is read again after the
     * lock, so a purge or another restore that ran meanwhile wins cleanly.
     *
     * @return WikiPage|null the page the user had deleted, or null when the batch is not in the space's trash
     */
    public function restore(WikiSpace $space, string $batchId, ?object $actor = null): ?WikiPage
    {
        $this->writer->beginSpaceTransaction($space);
        try {
            $entries = $this->trash->findBatch($space, $batchId);
            if ($entries === []) {
                $this->writer->commitSpaceTransaction();

                return null;
            }
            $root = $entries[0]->getPage();
            foreach ($entries as $entry) {
                if (!$entry->wasArchived()) {
                    $entry->getPage()->unarchive();
                }
            }
            // A parent archived or trashed meanwhile would hide the restored branch: it goes to the top level (D-W31).
            if ($root->getParent()?->isArchived() === true) {
                $root->moveTo(null, $root->getPosition());
            }
            // @igor-ignore - Repository write (Doctrine DELETE + flush); the store keeps no state of its own.
            $this->trash->removeBatch($batchId);
            $this->writer->commitSpaceTransaction();
        } catch (Throwable $exception) {
            $this->writer->rollbackSpaceTransaction();

            throw $exception;
        }

        $ids = array_map(static fn (WikiTrashEntry $e): string => $e->getPage()->getId(), $entries);
        $this->logger->info('Wiki trash restored.', ['bundle' => 'nowo_wiki', 'action' => 'trash_restore', 'space' => $space->getSlug(), 'page' => $root->getSlug(), 'pages' => count($ids)]);
        $this->dispatcher->dispatch(new WikiTrashEvent($space, $batchId, $root->getTitle(), $ids, $actor), WikiEvents::TRASH_RESTORED);

        return $root;
    }

    /**
     * Deletes a deletion of the space for good, under the space lock (audit DO-M1): the batch is read again after the
     * lock and only its pages that are still archived are deleted, so a restore that won the race keeps its pages.
     * The attachment rows go in the same transaction; their files are unlinked after the commit (DO-L1; a failed
     * unlink is logged and leaves an orphan file for the attachment maintenance).
     *
     * @return int number of pages deleted (0: the batch is not, or no longer, in the space's trash)
     */
    public function purge(WikiSpace $space, string $batchId, ?object $actor = null): int
    {
        $removed = [];
        $this->writer->beginSpaceTransaction($space);
        try {
            $entries = $this->trash->findBatch($space, $batchId);
            $pages   = array_values(array_filter(array_map(static fn (WikiTrashEntry $e): WikiPage => $e->getPage(), $entries), static fn (WikiPage $p): bool => $p->isArchived()));
            if ($pages === []) {
                // Nothing left to purge (restored meanwhile, or entries without archived pages): drop stale entries.
                if ($entries !== []) {
                    // @igor-ignore - Repository write (Doctrine DELETE + flush); the store keeps no state of its own.
                    $this->trash->removeBatch($batchId);
                }
                $this->writer->commitSpaceTransaction();

                return 0;
            }
            $title = $entries[0]->getPage()->getTitle();
            if ($this->attachmentRepository instanceof WikiAttachmentRepositoryInterface) {
                foreach ($pages as $page) {
                    foreach ($this->attachmentRepository->findByPage($page) as $attachment) {
                        // @igor-ignore - Repository write inside the space transaction; no service state.
                        $this->attachmentRepository->remove($attachment);
                        $removed[] = $attachment;
                    }
                }
            }
            $ids = array_map(static fn (WikiPage $p): string => $p->getId(), $pages);
            $this->trash->deletePages($pages);
            $this->writer->commitSpaceTransaction();
        } catch (Throwable $exception) {
            $this->writer->rollbackSpaceTransaction();

            throw $exception;
        }

        foreach ($removed as $attachment) {
            try {
                $this->storage?->delete($attachment->getStorageKey());
            } catch (Throwable $exception) {
                $this->logger->warning('Wiki trash: an attachment file could not be deleted.', ['bundle' => 'nowo_wiki', 'action' => 'trash_purge', 'exception_class' => $exception::class]);
            }
            $this->dispatcher->dispatch(new WikiAttachmentEvent($attachment, $actor), WikiEvents::ATTACHMENT_DELETED);
        }
        $this->logger->info('Wiki trash purged.', ['bundle' => 'nowo_wiki', 'action' => 'trash_purge', 'space' => $space->getSlug(), 'pages' => count($ids)]);
        $this->dispatcher->dispatch(new WikiTrashEvent($space, $batchId, $title, $ids, $actor), WikiEvents::TRASH_PURGED);

        return count($ids);
    }

    /**
     * Purges the deletions older than the retention; a dry run counts every expired deletion (DO-I3).
     *
     * @return array{batches: int, pages: int} what was (or, with `$dryRun`, would be) purged
     */
    public function purgeExpired(DateTimeImmutable $now, bool $dryRun = false): array
    {
        $before  = $now->modify('-' . $this->retentionDays . ' days');
        $batches = 0;
        $pages   = 0;
        $offset  = 0;
        do {
            $expired = $this->trash->findExpiredBatches($before, self::EXPIRED_PAGE, $dryRun ? $offset : 0);
            $offset += self::EXPIRED_PAGE;
            foreach ($expired as $item) {
                ++$batches;
                $pages += $dryRun ? count($this->trash->findBatch($item['space'], $item['batch'])) : $this->purge($item['space'], $item['batch']);
            }
        } while (count($expired) === self::EXPIRED_PAGE || (!$dryRun && $expired !== []));

        return ['batches' => $batches, 'pages' => $pages];
    }

    /**
     * Root entries of the space's trash, newest first.
     *
     * @return list<WikiTrashEntry>
     */
    public function roots(WikiSpace $space): array
    {
        return $this->trash->findRoots($space);
    }

    /**
     * Pages of a deletion of the space (root first).
     *
     * @return list<WikiTrashEntry>
     */
    public function batch(WikiSpace $space, string $batchId): array
    {
        return $this->trash->findBatch($space, $batchId);
    }

    /**
     * Ids of the trashed pages of the space (left out of the archived list and its unarchive action).
     *
     * @return array<string, true>
     */
    public function trashedIds(WikiSpace $space): array
    {
        return $this->trash->trashedPageIds($space);
    }
}
