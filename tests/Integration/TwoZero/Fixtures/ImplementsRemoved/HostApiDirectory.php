<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\TwoZero\Fixtures\ImplementsRemoved;

use Nowo\WikiBundle\Api\Hook\WikiApiDirectoryInterface;

/**
 * An autodiscovered host port that still implements a removed 1.x interface (a host channel silently dropped otherwise).
 */
final class HostApiDirectory implements WikiApiDirectoryInterface
{
}
