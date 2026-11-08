<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\App;

use Doctrine\DBAL\Exception\RetryableException;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\WikiPageBatchFinderInterface;
use Nowo\WikiBundle\Repository\WikiPageLockInterface;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use RuntimeException;

/**
 * The Doctrine page repository with races on demand: a slug check that misses a concurrent create (the insert then
 * hits the unique index, audit IF-I10), or a lock that cannot be taken (a deadlock or a lock wait timeout).
 */
final class ApiPageRepository implements WikiPageRepositoryInterface, WikiPageBatchFinderInterface, WikiPageLockInterface
{
    public static bool $missSlugs = false;

    public static bool $lockFails = false;

    private DoctrineOrmWikiPageRepository $inner;

    public function __construct(WikiEntityManagerProvider $entityManager)
    {
        $this->inner = new DoctrineOrmWikiPageRepository($entityManager);
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
        return self::$missSlugs ? 0 : $this->inner->countBySpaceAndSlug($space, $slug, $excludePageId);
    }

    public function findByIds(array $ids): array
    {
        return $this->inner->findByIds($ids);
    }

    public function beginPageTransaction(WikiPage $page): void
    {
        if (self::$lockFails) {
            throw new class('Lock wait timeout exceeded') extends RuntimeException implements RetryableException {
            };
        }
        $this->inner->beginPageTransaction($page);
    }

    public function commitPageTransaction(): void
    {
        $this->inner->commitPageTransaction();
    }

    public function rollbackPageTransaction(): void
    {
        $this->inner->rollbackPageTransaction();
    }
}
