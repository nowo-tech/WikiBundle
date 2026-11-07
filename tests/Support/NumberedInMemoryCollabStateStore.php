<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use Nowo\WikiBundle\Collaboration\WikiCollabNumberedStoreInterface;

use function count;
use function max;
use function min;
use function strlen;

/**
 * {@see InMemoryCollabStateStore} that tells its numbers, as the bundled store does.
 */
final class NumberedInMemoryCollabStateStore extends InMemoryCollabStateStore implements WikiCollabNumberedStoreInterface
{
    /** @var array<string, int> */
    private array $last = [];

    public function appendNumbered(string $pageId, string $update, int $maxEntries, int $maxBytes): array
    {
        $previous = $this->last[$pageId] ?? 0;
        $refused  = match (true) {
            count($this->load($pageId)->updates) >= $maxEntries       => 'log_full',
            $this->info($pageId)->bytes + strlen($update) > $maxBytes => 'log_reset',
            default                                                   => null,
        };
        if ($refused !== null) {
            return ['refused' => $refused, 's' => $previous, 'p' => $previous];
        }

        $this->last[$pageId] = $this->append($pageId, $update);

        return ['refused' => null, 's' => $this->last[$pageId], 'p' => $previous];
    }

    public function markSavedUpTo(string $pageId, string $revisionId, int $includedSeq): void
    {
        $saved = $this->savedSeq[$pageId] ?? 0;
        $this->markSaved($pageId, $revisionId);
        $this->savedSeq[$pageId] = max($saved, min($includedSeq, $this->last[$pageId] ?? 0));
    }
}
