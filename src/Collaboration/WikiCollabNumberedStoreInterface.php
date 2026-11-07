<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

/**
 * A store that tells the sequence numbers of its log (Milkdown >= 1.11 closes audit HO-L1 window 2 with them): a session
 * save says which updates its text includes, so one that landed while the save was in flight stays unsaved. Optional: a
 * host's own store without it keeps working as before, with the window open.
 */
interface WikiCollabNumberedStoreInterface extends WikiCollabStateStoreInterface
{
    /**
     * Appends like {@see WikiCollabCountingStoreInterface::appendWithin()}, under the page's row lock, and tells the
     * update's number and the one before it in this log, so a client can tell it has every update up to a number.
     *
     * @throws WikiCollabException 409 `log_reset` when the page's log was purged during the request
     *
     * @return array{refused: string|null, s: int, p: int} `refused`: null when stored, else `log_full` or `log_reset`, and then `s` equals `p`
     */
    public function appendNumbered(string $pageId, string $update, int $maxEntries, int $maxBytes): array;

    /**
     * A session save whose text includes every update up to `$includedSeq`: under the page's row lock, the base becomes the
     * revision and `savedSeq` becomes `max(savedSeq, min($includedSeq, lastSeq))`, so a number past the log's end
     * counts for nothing more, and a slower save never lowers what an earlier one recorded.
     */
    public function markSavedUpTo(string $pageId, string $revisionId, int $includedSeq): void;
}
