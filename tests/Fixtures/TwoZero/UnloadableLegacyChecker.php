<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Fixtures\TwoZero;

use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;

/**
 * 2.0: a 1.x host checker that still implements the removed interface; loading it fails.
 */
final class UnloadableLegacyChecker implements WikiAccessCheckerInterface
{
}
