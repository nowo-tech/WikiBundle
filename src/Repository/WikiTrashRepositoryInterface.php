<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use DateTimeImmutable;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Entity\WikiTrashEntry;

/**
 * Persistence of the trash (1.14, T065).
 */
interface WikiTrashRepositoryInterface
{
    /**
     * Stores the entries of one deletion and the archived pages, in one flush.
     *
     * @param list<WikiTrashEntry> $entries
     */
    public function saveBatch(array $entries): void;

    /**
     * Root entries of the space, newest first.
     *
     * @return list<WikiTrashEntry>
     */
    public function findRoots(WikiSpace $space): array;

    /**
     * Every entry of a batch of the space (root first), read from the database (inside the space lock: current).
     *
     * @return list<WikiTrashEntry>
     */
    public function findBatch(WikiSpace $space, string $batchId): array;

    /**
     * Ids of the trashed pages of the space.
     *
     * @return array<string, true>
     */
    public function trashedPageIds(WikiSpace $space): array;

    /**
     * Batch ids deleted before the date, with their space, oldest first: at most `$limit`, after `$offset`.
     *
     * @return list<array{batch: string, space: WikiSpace}>
     */
    public function findExpiredBatches(DateTimeImmutable $before, int $limit = 100, int $offset = 0): array;

    /**
     * Removes the entries of a batch (after a restore; the pages stay).
     */
    public function removeBatch(string $batchId): void;

    /**
     * Deletes the pages of a batch for good, with their revisions, host-object links and trash entries (attachments
     * are removed by the caller first, with their files). Pages left under them (not in the batch) move to the top.
     *
     * @param list<WikiPage> $pages
     */
    public function deletePages(array $pages): void;
}
