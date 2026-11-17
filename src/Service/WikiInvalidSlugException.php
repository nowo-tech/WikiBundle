<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use InvalidArgumentException;

/**
 * A page create refused because the slug is not a valid page slug (`Invalid page slug.`). Nothing was written.
 *
 * 1.40.2 (audit SH-L1): it extends {@see InvalidArgumentException}, so callers that catch that keep working, and the
 * message is the one thrown before. Catch this class, not {@see InvalidArgumentException}: Doctrine's
 * `ORMInvalidArgumentException` extends it too, and must not be read as this refusal.
 */
final class WikiInvalidSlugException extends InvalidArgumentException
{
}
