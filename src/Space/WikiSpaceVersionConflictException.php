<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Space;

use RuntimeException;

use function sprintf;

/**
 * 1.50: optimistic lock: the space changed since the caller read it (its version is not the expected one). Nothing was
 * written; read the space again and decide. Not a lock conflict: an API answers 409 without `Retry-After`.
 */
final class WikiSpaceVersionConflictException extends RuntimeException
{
    public function __construct(public readonly int $expected, public readonly int $actual)
    {
        parent::__construct(sprintf('The space changed meanwhile (version %d, expected %d).', $actual, $expected));
    }
}
