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

    /** A rename (1.56: or an icon change) of an archived space (as the REST API refuses it): bring it back first. */
    public const ARCHIVED = 'archived';

    /**
     * @param string $reason one of the constants (the code callers compare)
     * @param WikiSpaceLifecycleAction|null $action 1.56: the refused change, for the message only (the reason stays the
     *                                              same: `Icon` says "changing its icon", anything else "renaming it")
     */
    public function __construct(public readonly string $reason, public readonly ?WikiSpaceLifecycleAction $action = null)
    {
        parent::__construct(match ($reason) {
            self::NOT_ARCHIVED => 'Archive the space before purging it.',
            self::ARCHIVED     => $action === WikiSpaceLifecycleAction::Icon ? 'Bring the space back before changing its icon.' : 'Bring the space back before renaming it.',
            default            => 'The space is being purged.',
        });
    }
}
