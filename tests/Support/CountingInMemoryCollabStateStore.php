<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use Nowo\WikiBundle\Collaboration\WikiCollabCountingStoreInterface;

use function strlen;

/**
 * In-memory store with the 1.14.1 one-write update path ({@see WikiCollabCountingStoreInterface}); counts the calls of
 * appendWithin() in {@see self::$checkedAppends}.
 */
final class CountingInMemoryCollabStateStore extends InMemoryCollabStateStore implements WikiCollabCountingStoreInterface
{
    public int $checkedAppends = 0;

    public function appendWithin(string $pageId, string $update, int $maxEntries, int $maxBytes): ?string
    {
        ++$this->checkedAppends;
        $info = parent::info($pageId);
        if ($info->updateCount >= $maxEntries) {
            return 'log_full';
        }
        if ($info->bytes + strlen($update) > $maxBytes) {
            return 'log_reset';
        }
        $this->append($pageId, $update);

        return null;
    }
}
