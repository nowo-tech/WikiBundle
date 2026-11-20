<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Space;

use RuntimeException;

/**
 * 1.50: the space does not exist, or the person may not open it. Both answer the same (an API answers 404), so a space
 * of another tenant cannot be told from a missing one. Nothing was written.
 */
final class WikiSpaceNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Space not found.');
    }
}
