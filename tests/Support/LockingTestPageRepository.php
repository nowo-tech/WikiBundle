<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\WikiPageLockInterface;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Throwable;

/**
 * The Doctrine page repository with failures injected into its page lock (1.24 tests), counting rollbacks.
 */
final class LockingTestPageRepository implements WikiPageRepositoryInterface, WikiPageLockInterface
{
    public int $rollbacks = 0;

    public function __construct(
        private readonly DoctrineOrmWikiPageRepository $inner,
        private readonly ?Throwable $beginFails = null,
        private readonly ?Throwable $commitFails = null,
    ) {
    }

    public function beginPageTransaction(WikiPage $page): void
    {
        if ($this->beginFails instanceof Throwable) {
            throw $this->beginFails;
        }
        $this->inner->beginPageTransaction($page);
    }

    public function commitPageTransaction(): void
    {
        if ($this->commitFails instanceof Throwable) {
            throw $this->commitFails;
        }
        $this->inner->commitPageTransaction();
    }

    public function rollbackPageTransaction(): void
    {
        ++$this->rollbacks;
        $this->inner->rollbackPageTransaction();
    }

    public function save(WikiPage $page): void
    {
        $this->inner->save($page);
    }

    public function findById(string $id): ?WikiPage
    {
        return $this->inner->findById($id);
    }

    public function findBySlug(WikiSpace $space, string $slug): ?WikiPage
    {
        return $this->inner->findBySlug($space, $slug);
    }

    public function findActiveBySpace(WikiSpace $space, ?int $limit = null): array
    {
        return $this->inner->findActiveBySpace($space, $limit);
    }

    public function findArchivedBySpace(WikiSpace $space, int $limit = 100): array
    {
        return $this->inner->findArchivedBySpace($space, $limit);
    }

    public function countBySpaceAndSlug(WikiSpace $space, string $slug, ?string $excludePageId = null): int
    {
        return $this->inner->countBySpaceAndSlug($space, $slug, $excludePageId);
    }
}
