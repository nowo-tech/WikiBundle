<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Space;

use Nowo\WikiBundle\Event\WikiSpaceAccessCheckEvent;

/**
 * 1.50: what {@see WikiSpaceLifecycleInterface} is asked to do to a space; the action of a
 * {@see WikiSpaceAccessCheckEvent}.
 */
enum WikiSpaceLifecycleAction: string
{
    /** Change the name and/or the slug. */
    case Rename = 'rename';

    /** Make the space read-only and hide it from navigation and search. */
    case Archive = 'archive';

    /** Bring an archived space back. */
    case Unarchive = 'unarchive';

    /** 1.56: set or clear the space icon. */
    case Icon = 'icon';

    /** Delete the space and everything in it, for good. */
    case Purge = 'purge';
}
