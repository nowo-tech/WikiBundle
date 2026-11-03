<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

/**
 * A store that keeps the size of each log next to it (1.14.1): the update request checks the caps and appends in one
 * locked write, instead of reading the log's size before and after. {@see DoctrineWikiCollabStateStore} implements
 * it; a host store that does not is used through {@see WikiCollabStateStoreInterface} as before.
 */
interface WikiCollabCountingStoreInterface extends WikiCollabStateStoreInterface
{
    /**
     * Appends the update when the log holds fewer than `$maxEntries` updates and stays within `$maxBytes`.
     *
     * @throws WikiCollabException 409 `log_reset` when the page's log was purged during the request
     *
     * @return string|null null when stored; `log_full` (too many updates: compact first) or `log_reset` (over the
     *                     byte budget: the caller drops the log) when refused
     */
    public function appendWithin(string $pageId, string $update, int $maxEntries, int $maxBytes): ?string;
}
