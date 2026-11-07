<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use Nowo\WikiBundle\Collaboration\WikiCollabEpochStoreInterface;

/**
 * {@see NumberedInMemoryCollabStateStore} that names its logs, as the bundled store does. `$locks` counts the lock taken.
 */
class EpochInMemoryCollabStateStore extends NumberedInMemoryCollabStateStore implements WikiCollabEpochStoreInterface
{
    /** @var array<string, string> */
    public array $epochs = [];

    public bool $locked = false;

    private int $names = 0;

    public function epoch(string $pageId): string
    {
        return $this->epochs[$pageId] ??= 'epoch-' . ++$this->names;
    }

    public function currentEpoch(string $pageId): ?string
    {
        return $this->epochs[$pageId] ?? null;
    }

    public function reset(string $pageId, ?string $baseRevisionId): void
    {
        parent::reset($pageId, $baseRevisionId);
        $this->epochs[$pageId] = 'epoch-' . ++$this->names;
    }

    public function whileLocked(string $pageId, callable $work): mixed
    {
        $this->locked = true;

        try {
            return $work();
        } finally {
            $this->locked = false;
        }
    }

    public function appendInEpoch(string $pageId, string $update, int $maxEntries, int $maxBytes, ?string $epoch): array
    {
        $current = $this->currentEpoch($pageId);
        if ($epoch !== null && $current !== $epoch) {
            return ['refused' => 'log_epoch', 's' => 0, 'p' => 0, 'e' => $current];
        }

        return $this->appendNumbered($pageId, $update, $maxEntries, $maxBytes) + ['e' => $current];
    }
}
