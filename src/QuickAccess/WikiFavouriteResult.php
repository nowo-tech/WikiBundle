<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\QuickAccess;

/**
 * 1.45: the outcome of adding or removing a favourite. Two people (or two tabs) starring the same page at once is the
 * expected "only one row" race of the unique key, never a conflict: the loser gets {@see self::Unchanged}.
 */
enum WikiFavouriteResult: string
{
    /** The page was added to the person's favourites. */
    case Added = 'added';

    /** The page was removed from the person's favourites. */
    case Removed = 'removed';

    /** Nothing to do: already a favourite (add) or not one (remove), possibly because a concurrent request did it. */
    case Unchanged = 'unchanged';

    /** Refused: the person already keeps `favourites.max` pages. */
    case LimitReached = 'limit_reached';

    /** Whether the page is a favourite after the call (for {@see self::Unchanged}: the state that was asked for). */
    public function isFavourite(bool $asked): bool
    {
        return match ($this) {
            self::Added        => true,
            self::Removed      => false,
            self::LimitReached => false,
            self::Unchanged    => $asked,
        };
    }
}
