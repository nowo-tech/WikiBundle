<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use RuntimeException;

/**
 * A revision could not be written because another save got there first: a deadlock or a duplicate revision number
 * (audit HO-L4). Nothing was written, so the caller can offer to try again; the database error is the previous one.
 */
final class WikiRevisionCollisionException extends RuntimeException
{
}
