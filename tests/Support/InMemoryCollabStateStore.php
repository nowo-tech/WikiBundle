<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use DateTimeImmutable;
use Nowo\WikiBundle\Collaboration\WikiCollabState;
use Nowo\WikiBundle\Collaboration\WikiCollabStateStoreInterface;

use function array_values;

/**
 * In-memory Yjs log store for unit tests.
 */
final class InMemoryCollabStateStore implements WikiCollabStateStoreInterface
{
    /** @var array<string, array{snapshot: string|null, updates: array<int, string>, base: string|null, seeded: DateTimeImmutable|null, updated: DateTimeImmutable|null}> */
    public array $pages = [];

    private int $seq = 0;

    public function load(string $pageId): WikiCollabState
    {
        $page = $this->page($pageId);

        return new WikiCollabState($page['snapshot'], array_values($page['updates']), $this->seq, $page['base'], $page['seeded'] instanceof DateTimeImmutable, $page['updated']);
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
        foreach ($this->pages[$pageId]['updates'] as $seq => $update) {
            if ($seq <= $upToSeq) {
                unset($this->pages[$pageId]['updates'][$seq]);
            }
        }
    }

    public function claimSeed(string $pageId, DateTimeImmutable $staleBefore): bool
    {
        $this->page($pageId);
        $seeded = $this->pages[$pageId]['seeded'];
        if ($seeded instanceof DateTimeImmutable && $seeded >= $staleBefore) {
            return false;
        }

        $this->pages[$pageId]['seeded'] = new DateTimeImmutable();

        return true;
    }

    public function reset(string $pageId, ?string $baseRevisionId): void
    {
        $this->pages[$pageId] = ['snapshot' => null, 'updates' => [], 'base' => $baseRevisionId, 'seeded' => null, 'updated' => new DateTimeImmutable()];
    }

    public function markSaved(string $pageId, string $revisionId): void
    {
        $this->page($pageId);
        $this->pages[$pageId]['base'] = $revisionId;
    }

    /**
     * @return array{snapshot: string|null, updates: array<int, string>, base: string|null, seeded: DateTimeImmutable|null, updated: DateTimeImmutable|null}
     */
    private function page(string $pageId): array
    {
        return $this->pages[$pageId] ??= ['snapshot' => null, 'updates' => [], 'base' => null, 'seeded' => null, 'updated' => null];
    }
}
