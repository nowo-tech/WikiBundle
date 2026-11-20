<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Space;

use RuntimeException;

/**
 * 1.50: the space is not in a state that allows the change: a purge of a space that is not archived (without `force`),
 * or a rename or unarchive of a space being purged. Nothing was written. An API answers 409.
 */
final class WikiSpaceStateException extends RuntimeException
{
    /** A purge needs an archived space (or `force`). */
    public const NOT_ARCHIVED = 'not_archived';

    /** A purge started: the space only goes on to be deleted. */
    public const PURGING = 'purging';

    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason === self::NOT_ARCHIVED ? 'Archive the space before purging it.' : 'The space is being purged.');
    }
}
