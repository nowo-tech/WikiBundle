<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Trash;

use DateTimeImmutable;
use Nowo\WikiBundle\Attachment\WikiAttachmentService;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Entity\WikiTrashEntry;
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

use function array_map;
use function array_shift;
use function count;

/**
 * The trash of a space (1.14, T065, `trash.enabled`).
 *
 * - **Delete** moves a page and **its whole sub-tree** to the trash in one batch: every page of it is archived, so
 *   every read path (tree, search, links, mentions, backlinks, export, AI) treats it as gone; pages archived before
 *   stay archived after a restore. The page the user deleted is the batch root.
 * - **Restore** brings the batch back together; a root whose parent is gone meanwhile goes to the top level.
 * - **Purge** deletes the batch for good: pages, revisions, host-object links, attachments (and their files). Pages
 *   left under a purged page move to the top level. The retention (`trash.retention_days`) purges older batches
 *   through `wiki:trash:purge`.
 *
 * Deletions run under the space lock of the page tree, like moves.
 */
final readonly class WikiTrashService
{
    /** Pages read at most for one deletion (the space's pages, archived included). */
    private const MAX_PAGES = 100000;

    public function __construct(
        private WikiPageRepositoryInterface $pages,
        private WikiTrashRepositoryInterface $trash,
        private WikiPageTreeWriterInterface $writer,
        private EventDispatcherInterface $dispatcher,
        private ?WikiAttachmentService $attachments = null,
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
     * Restores a deletion of the space.
     *
     * @return WikiPage|null the page the user had deleted, or null when the batch is not in the space's trash
     */
    public function restore(WikiSpace $space, string $batchId, ?object $actor = null): ?WikiPage
    {
        $entries = $this->trash->findBatch($space, $batchId);
        if ($entries === []) {
            return null;
        }
        $root = $entries[0]->getPage();
        foreach ($entries as $entry) {
            if (!$entry->wasArchived()) {
                $entry->getPage()->unarchive();
            }
        }
        // A parent archived or trashed meanwhile would hide the restored branch: it goes to the top level.
        if ($root->getParent()?->isArchived() === true) {
            $root->moveTo(null, $root->getPosition());
        }
        // @igor-ignore - Repository write (Doctrine DELETE + flush); the store keeps no state of its own.
        $this->trash->removeBatch($batchId);

        $ids = array_map(static fn (WikiTrashEntry $e): string => $e->getPage()->getId(), $entries);
        $this->logger->info('Wiki trash restored.', ['bundle' => 'nowo_wiki', 'action' => 'trash_restore', 'space' => $space->getSlug(), 'page' => $root->getSlug(), 'pages' => count($ids)]);
        $this->dispatcher->dispatch(new WikiTrashEvent($space, $batchId, $root->getTitle(), $ids, $actor), WikiEvents::TRASH_RESTORED);

        return $root;
    }

    /**
     * Deletes a deletion of the space for good.
     *
     * @return int number of pages deleted (0: the batch is not in the space's trash)
     */
    public function purge(WikiSpace $space, string $batchId, ?object $actor = null): int
    {
        $entries = $this->trash->findBatch($space, $batchId);
        if ($entries === []) {
            return 0;
        }
        $title = $entries[0]->getPage()->getTitle();
        $pages = array_map(static fn (WikiTrashEntry $e): WikiPage => $e->getPage(), $entries);
        $ids   = array_map(static fn (WikiPage $p): string => $p->getId(), $pages);
        if ($this->attachments instanceof WikiAttachmentService && $this->attachmentRepository instanceof WikiAttachmentRepositoryInterface) {
            foreach ($pages as $page) {
                foreach ($this->attachmentRepository->findByPage($page) as $attachment) {
                    $this->attachments->delete($attachment, $actor);
                }
            }
        }
        $this->trash->deletePages($pages);

        $this->logger->info('Wiki trash purged.', ['bundle' => 'nowo_wiki', 'action' => 'trash_purge', 'space' => $space->getSlug(), 'pages' => count($ids)]);
        $this->dispatcher->dispatch(new WikiTrashEvent($space, $batchId, $title, $ids, $actor), WikiEvents::TRASH_PURGED);

        return count($ids);
    }

    /**
     * Purges the deletions older than the retention.
     *
     * @return array{batches: int, pages: int} what was (or, with `$dryRun`, would be) purged
     */
    public function purgeExpired(DateTimeImmutable $now, bool $dryRun = false): array
    {
        $before  = $now->modify('-' . $this->retentionDays . ' days');
        $batches = 0;
        $pages   = 0;
        do {
            $expired = $this->trash->findExpiredBatches($before);
            foreach ($expired as $item) {
                ++$batches;
                $pages += $dryRun ? count($this->trash->findBatch($item['space'], $item['batch'])) : $this->purge($item['space'], $item['batch']);
            }
        } while (!$dryRun && $expired !== []);

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
