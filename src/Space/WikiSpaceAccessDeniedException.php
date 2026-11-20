<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Space;

use Nowo\WikiBundle\Event\WikiSpaceAccessCheckEvent;
use RuntimeException;

/**
 * 1.50: the person may open the space but not rename, archive, unarchive or purge it (the space checker's `Manage`, or a
 * {@see WikiSpaceAccessCheckEvent} listener, refused). An API answers 403. Nothing was written.
 */
final class WikiSpaceAccessDeniedException extends RuntimeException
{
    public function __construct(public readonly WikiSpaceLifecycleAction $action)
    {
        parent::__construct('Access denied.');
    }
}
