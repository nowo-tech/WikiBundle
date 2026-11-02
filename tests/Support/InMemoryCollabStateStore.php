<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use DateTimeImmutable;
use Nowo\WikiBundle\Collaboration\WikiCollabState;
use Nowo\WikiBundle\Collaboration\WikiCollabStateStoreInterface;

use function array_values;
use function count;
use function strlen;

/**
 * In-memory Yjs log store for unit tests (counts the data reads in {@see self::$loads}).
 */
final class InMemoryCollabStateStore implements WikiCollabStateStoreInterface
{
    /** @var array<string, array{snapshot: string|null, covers: int, updates: array<int, string>, base: string|null, seededAt: DateTimeImmutable|null, seededBy: string|null, updated: DateTimeImmutable|null}> */
    public array $pages = [];

    public int $loads = 0;

    private int $seq = 0;

    public function load(string $pageId): WikiCollabState
    {
        ++$this->loads;
        $page = $this->page($pageId);
        $info = $this->info($pageId);

        return new WikiCollabState($page['snapshot'], array_values($page['updates']), $info->seq, $info->baseRevisionId, $info->seededAt, $info->seededBy, $info->updatedAt, $info->updateCount, $info->bytes, $info->snapshotSeq);
    }

    public function info(string $pageId): WikiCollabState
    {
        $page  = $this->page($pageId);
        $bytes = strlen((string) $page['snapshot']);
        foreach ($page['updates'] as $update) {
            $bytes += strlen($update);
        }

        return new WikiCollabState(null, [], $this->seq, $page['base'], $page['seededAt'], $page['seededBy'], $page['updated'], count($page['updates']), $bytes, $page['covers']);
    }

    public function append(string $pageId, string $update): int
    {
        $this->page($pageId);
        $this->pages[$pageId]['updates'][++$this->seq] = $update;
        $this->pages[$pageId]['updated']               = new DateTimeImmutable();

        return $this->seq;
    }

    public function compact(string $pageId, string $snapshot, int $upToSeq): void
    {
        $this->page($pageId);
        $this->pages[$pageId]['snapshot'] = $snapshot;
        $this->pages[$pageId]['covers']   = $upToSeq;
        foreach ($this->pages[$pageId]['updates'] as $seq => $update) {
            if ($seq <= $upToSeq) {
                unset($this->pages[$pageId]['updates'][$seq]);
            }
        }
    }

    public function claimSeed(string $pageId, DateTimeImmutable $staleBefore, string $claimant): bool
    {
        $this->page($pageId);
        $seeded = $this->pages[$pageId]['seededAt'];
        if ($seeded instanceof DateTimeImmutable && $seeded >= $staleBefore) {
            return false;
        }

        $this->pages[$pageId]['seededAt'] = new DateTimeImmutable();
        $this->pages[$pageId]['seededBy'] = $claimant;
        $this->pages[$pageId]['updated']  = new DateTimeImmutable();

        return true;
    }

    public function reset(string $pageId, ?string $baseRevisionId): void
    {
        $this->pages[$pageId] = ['snapshot' => null, 'covers' => 0, 'updates' => [], 'base' => $baseRevisionId, 'seededAt' => null, 'seededBy' => null, 'updated' => new DateTimeImmutable()];
    }

    public function markSaved(string $pageId, string $revisionId): void
    {
        $this->page($pageId);
        $this->pages[$pageId]['base'] = $revisionId;
    }

    /** @var array<string, array{0: int, 1: DateTimeImmutable}> page id => recent corrupt resets, last one */
    public array $corruptResets = [];

    public function noteCorruptReset(string $pageId, DateTimeImmutable $windowStart, int $cap): bool
    {
        [$count, $last] = $this->corruptResets[$pageId] ?? [0, null];
        $count          = $last instanceof DateTimeImmutable && $last >= $windowStart ? $count : 0;
        if ($count >= $cap) {
            return false;
        }

        $this->corruptResets[$pageId] = [$count + 1, new DateTimeImmutable()];

        return true;
    }

    /**
     * @return array{snapshot: string|null, covers: int, updates: array<int, string>, base: string|null, seededAt: DateTimeImmutable|null, seededBy: string|null, updated: DateTimeImmutable|null}
     */
    private function page(string $pageId): array
    {
        return $this->pages[$pageId] ??= ['snapshot' => null, 'covers' => 0, 'updates' => [], 'base' => null, 'seededAt' => null, 'seededBy' => null, 'updated' => null];
    }
}
