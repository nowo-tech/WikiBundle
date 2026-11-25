<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\TwoZero\Fixtures\DecoratorIgnore;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A loadable class that decorates the removed access checker id with onInvalid IGNORE: Symfony would drop it silently.
 */
#[AsDecorator('Nowo\WikiBundle\Security\WikiAccessCheckerInterface', onInvalid: ContainerInterface::IGNORE_ON_INVALID_REFERENCE)]
final class LoadableDecorator
{
}
