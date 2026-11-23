<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Space;

use RuntimeException;

/**
 * 1.55.1 (audit ZAP-2): the space has another owner than the one the caller expected (type and id compared byte for
 * byte under the space lock). Nothing was written.
 */
final class WikiSpaceOwnerMismatchException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The space has another owner.');
    }
}
