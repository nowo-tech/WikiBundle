<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\ValueObject;

use InvalidArgumentException;

/**
 * A page place ({@see WikiPagePlacement}: `child:<id>`, `above:<id>`, `below:<id>`) that is not one.
 *
 * 1.41: it extends {@see InvalidArgumentException}, so callers that catch that keep working, and the message is the one
 * thrown before. Catch this class, not {@see InvalidArgumentException} (the 1.40.2 rule, audit SH-L1).
 */
final class WikiInvalidPlacementException extends InvalidArgumentException
{
}
