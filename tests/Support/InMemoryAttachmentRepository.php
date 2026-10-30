<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use DateTimeImmutable;
use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Repository\WikiAttachmentRepositoryInterface;
use RuntimeException;

use function in_array;

/**
 * In-memory repository for unit tests.
 */
final class InMemoryAttachmentRepository implements WikiAttachmentRepositoryInterface
{
    /** @var array<string, WikiAttachment> */
    public array $rows = [];

    /** @var list<string> */
    public array $referenced = [];

    public bool $failOnSave = false;

    public function save(WikiAttachment $attachment): void
    {
        if ($this->failOnSave) {
            throw new RuntimeException('database down');
        }

        $this->rows[$attachment->getId()] = $attachment;
    }

    public function remove(WikiAttachment $attachment): void
    {
        unset($this->rows[$attachment->getId()]);
    }

    public function findById(string $id): ?WikiAttachment
    {
        return $this->rows[$id] ?? null;
    }

    public function findByIds(array $ids): array
    {
        return array_values(array_filter($this->rows, static fn (WikiAttachment $a): bool => in_array($a->getId(), $ids, true)));
    }

    public function findByPage(WikiPage $page): array
    {
        return array_values($this->rows);
    }

    public function sumSizeBySpace(WikiSpace $space): int
    {
        return array_sum(array_map(static fn (WikiAttachment $a): int => $a->getSize(), $this->rows));
    }

    public function findUploadedBefore(DateTimeImmutable $before, int $limit = 500): array
    {
        return array_values(array_filter($this->rows, static fn (WikiAttachment $a): bool => $a->getUploadedAt() < $before));
    }

    public function existsByStorageKey(string $storageKey): bool
    {
        foreach ($this->rows as $row) {
            if ($row->getStorageKey() === $storageKey) {
                return true;
            }
        }

        return false;
    }

    public function isReferencedByPageRevisions(WikiPage $page, string $needle): bool
    {
        return in_array($needle, $this->referenced, true);
    }
}
