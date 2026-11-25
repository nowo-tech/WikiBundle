<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\TwoZero\Fixtures\UnrelatedMissing;

use Nowo\WikiBundle\Security\WikiAccessCheckerPolicyInterface;

/**
 * Implements a missing interface whose name only starts like a removed one: it keeps Symfony's own lazy error (no
 * false positive), so the container still compiles.
 */
final class UnrelatedService implements WikiAccessCheckerPolicyInterface
{
}
