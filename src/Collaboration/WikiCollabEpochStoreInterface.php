<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

/**
 * A store that names each log (audit HV-M1, Milkdown >= 1.12): every reset changes the name, so a client that was away
 * cannot post its old document into the new log, and its save asks. Optional: with a host's own store that does not
 * implement it, clients get today's behaviour.
 */
interface WikiCollabEpochStoreInterface extends WikiCollabNumberedStoreInterface, WikiCollabLockingStoreInterface
{
    /** The current log's name, given a name if it has none yet. */
    public function epoch(string $pageId): string;

    /** The current log's name without giving one; call it under {@see self::whileLocked()} to compare and write as one. */
    public function currentEpoch(string $pageId): ?string;

    /**
     * Appends like {@see WikiCollabNumberedStoreInterface::appendNumbered()}, comparing under the same row lock.
     *
     * @param string|null $epoch the log the update belongs to; null: a client that names none, not compared
     *
     * @return array{refused: string|null, s: int, p: int, e: string|null} `refused` is also `log_epoch` when that is not the current log
     */
    public function appendInEpoch(string $pageId, string $update, int $maxEntries, int $maxBytes, ?string $epoch): array;
}
