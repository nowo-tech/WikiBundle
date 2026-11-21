<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Space;

use RuntimeException;

/**
 * 1.50: the space is not in a state that allows the change: a purge of a space that is not archived (without `force`),
 * a rename of an archived space, or a rename or unarchive of a space being purged. Nothing was written. An API answers 409.
 */
final class WikiSpaceStateException extends RuntimeException
{
    /** A purge needs an archived space (or `force`). */
    public const NOT_ARCHIVED = 'not_archived';

    /** A purge started: the space only goes on to be deleted. */
    public const PURGING = 'purging';

    /** A rename of an archived space (as the REST API refuses it): bring it back first. */
    public const ARCHIVED = 'archived';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            self::NOT_ARCHIVED => 'Archive the space before purging it.',
            self::ARCHIVED     => 'Bring the space back before renaming it.',
            default            => 'The space is being purged.',
        });
    }
}
