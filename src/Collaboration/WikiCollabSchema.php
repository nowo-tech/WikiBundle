<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use function bin2hex;
use function random_bytes;
use function str_starts_with;

/**
 * 1.40 (owner decision O7): the collaboration schema version folded into the log epoch. It follows
 * nowo-tech/milkdown-editor-bundle `CollabSchema::VERSION` (2 since Milkdown 1.18: heading nodes keep their own id; a
 * pre-1.18 editor rewrote every heading id from its text, on every peer, after each change).
 *
 * Every log named since 1.40 starts with {@see self::EPOCH_PREFIX}. A log named before (an older schema) is replaced at
 * the page's next session ({@see WikiCollabService::token()}: when the log is empty, or idle and fully saved), so the next
 * client re-seeds it from the Markdown, and tabs of an older editor get the existing `409 log_epoch` answer and reload.
 * A host store that implements {@see WikiCollabEpochStoreInterface} names its logs with {@see self::newEpoch()}.
 * Stateless.
 */
final class WikiCollabSchema
{
    /** The schema version (Milkdown `CollabSchema::VERSION`). */
    public const VERSION = 2;

    /** Start of every epoch of this schema (`s2-`). */
    public const EPOCH_PREFIX = 's' . self::VERSION . '-';

    /** A new log name of this schema (31 characters; the `log_epoch` column takes 32). */
    public static function newEpoch(): string
    {
        return self::EPOCH_PREFIX . bin2hex(random_bytes(14));
    }

    /**
     * Whether a log name belongs to this schema (null: a log not named yet, named with this schema when asked).
     */
    public static function isCurrent(?string $epoch): bool
    {
        return $epoch === null || str_starts_with($epoch, self::EPOCH_PREFIX);
    }
}
