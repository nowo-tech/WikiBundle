<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Fixtures\TwoZero;

use App\Missing\SomethingInterface;

/**
 * 2.0: a class whose load fails for a reason that has nothing to do with the removed interfaces.
 */
final class UnloadableUnrelated implements SomethingInterface
{
}
