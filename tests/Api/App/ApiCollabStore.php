<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\App;

use DateTimeImmutable;
use Nowo\WikiBundle\Collaboration\DoctrineWikiCollabStateStore;
use Nowo\WikiBundle\Collaboration\WikiCollabLockingStoreInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabState;
use Nowo\WikiBundle\Collaboration\WikiCollabStateStoreInterface;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use RuntimeException;

/**
 * The Doctrine co-editing store with a failure on demand: `reset()` throws while `$failReset`, after the save under the
 * lock wrote its revision (audit IF-L2).
 */
final class ApiCollabStore implements WikiCollabStateStoreInterface, WikiCollabLockingStoreInterface
{
    public static bool $failReset = false;

    private DoctrineWikiCollabStateStore $inner;

    public function __construct(WikiEntityManagerProvider $entityManager)
    {
        $this->inner = new DoctrineWikiCollabStateStore($entityManager);
    }

    public function load(string $pageId): WikiCollabState
    {
        return $this->inner->load($pageId);
    }

    public function info(string $pageId): WikiCollabState
    {
        return $this->inner->info($pageId);
    }

    public function append(string $pageId, string $update): int
    {
        return $this->inner->append($pageId, $update);
    }

    public function compact(string $pageId, string $snapshot, int $upToSeq): bool
    {
        return $this->inner->compact($pageId, $snapshot, $upToSeq);
    }

    public function claimSeed(string $pageId, DateTimeImmutable $staleBefore, string $claimant): bool
    {
        return $this->inner->claimSeed($pageId, $staleBefore, $claimant);
    }

    public function reset(string $pageId, ?string $baseRevisionId): void
    {
        if (self::$failReset) {
            throw new RuntimeException('The log could not be reset.');
        }
        $this->inner->reset($pageId, $baseRevisionId);
    }

    public function markSaved(string $pageId, string $revisionId): void
    {
        $this->inner->markSaved($pageId, $revisionId);
    }

    public function noteReset(string $pageId, DateTimeImmutable $windowStart, int $cap): bool
    {
        return $this->inner->noteReset($pageId, $windowStart, $cap);
    }

    public function purge(DateTimeImmutable $before): int
    {
        return $this->inner->purge($before);
    }

    public function whileLocked(string $pageId, callable $work): mixed
    {
        return $this->inner->whileLocked($pageId, $work);
    }
}
