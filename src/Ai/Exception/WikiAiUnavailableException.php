<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ai\Exception;

use RuntimeException;

/**
 * Thrown when the wiki AI assistant is not available (not configured or disabled).
 */
final class WikiAiUnavailableException extends RuntimeException
{
}
