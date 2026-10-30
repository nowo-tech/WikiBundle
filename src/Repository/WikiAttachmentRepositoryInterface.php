<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use DateTimeImmutable;
use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;

/**
 * Persistence of {@see WikiAttachment} (1.9).
 */
interface WikiAttachmentRepositoryInterface
{
    /** Persists and flushes an attachment. */
    public function save(WikiAttachment $attachment): void;

    /** Removes and flushes an attachment row (the file is the storage's job). */
    public function remove(WikiAttachment $attachment): void;

    /** Returns the attachment with the id, or null. */
    public function findById(string $id): ?WikiAttachment;

    /**
     * Attachments with one of the ids, in one query.
     *
     * @param list<string> $ids
     *
     * @return list<WikiAttachment>
     */
    public function findByIds(array $ids): array;

    /**
     * Attachments of a page, oldest first.
     *
     * @return list<WikiAttachment>
     */
    public function findByPage(WikiPage $page): array;

    /** Total bytes stored in a space. */
    public function sumSizeBySpace(WikiSpace $space): int;

    /**
     * Attachments uploaded before the date (orphan candidates), oldest first.
     *
     * @return list<WikiAttachment>
     */
    public function findUploadedBefore(DateTimeImmutable $before, int $limit = 500): array;

    /** Tells whether an attachment row uses the storage key. */
    public function existsByStorageKey(string $storageKey): bool;

    /** Tells whether any revision of the page mentions the text (the attachment id) in its body. */
    public function isReferencedByPageRevisions(WikiPage $page, string $needle): bool;
}
