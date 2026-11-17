<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange;

use InvalidArgumentException;

/**
 * An import or export refused for its input: an archive that is missing, unreadable, unsafe or over a limit, a source
 * that is neither a directory nor a ZIP archive, a document with an invalid or taken slug or a missing parent.
 *
 * 1.41: it extends {@see InvalidArgumentException}, so callers that catch that keep working, and the message is the one
 * thrown before. Catch this class, not {@see InvalidArgumentException}: Doctrine's `ORMInvalidArgumentException`
 * extends it too, and must not be read as a refused archive (the 1.40.2 rule, audit SH-L1).
 */
final class WikiInterchangeException extends InvalidArgumentException
{
}
