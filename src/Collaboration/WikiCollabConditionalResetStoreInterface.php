<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

/**
 * 1.40 (audit RY-L3): a store that drops a page's log only if it is still the log the caller looked at. The check and
 * the drop run under the page document's row lock, so an update that lands after the caller's read is never deleted
 * with a log it did not belong to. Optional: with a store that does not implement it, the session start drops the log
 * with {@see WikiCollabStateStoreInterface::reset()} as before.
 */
interface WikiCollabConditionalResetStoreInterface
{
    /**
     * Drops the log like `reset()` when its name and its last number are still the expected ones.
     *
     * @param string $pageId the page
     * @param string|null $baseRevisionId the revision the new log starts from
     * @param string|null $expectedEpoch the log name the caller read (null: not named, or a store without names)
     * @param int $expectedSeq the log's last number the caller read ({@see WikiCollabState::$seq})
     *
     * @return bool whether the log was dropped
     */
    public function resetIf(string $pageId, ?string $baseRevisionId, ?string $expectedEpoch, int $expectedSeq): bool;
}
