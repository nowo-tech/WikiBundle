<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\TwoZero\Fixtures\AsDecoratorRemoved;

use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * A 1.x host decorator that restricted the space checker: it still implements the removed interface (so it cannot be
 * loaded) and decorates its removed id. 2.0 must fail the build instead of dropping it (which would widen access).
 */
#[AsDecorator(WikiSpaceAccessCheckerInterface::class)]
final class RestrictingSpaceChecker implements WikiSpaceAccessCheckerInterface
{
}
