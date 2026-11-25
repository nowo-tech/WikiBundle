<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\DependencyInjection\Compiler;

use LogicException;
use Nowo\WikiBundle\Port\WikiPorts;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function sprintf;
use function str_contains;

/**
 * 2.0 (REQ-BC-001, lesson XR-WF-M1): refuses every trace of the 17 removed 1.x host port interfaces (deprecated since
 * 1.36, notices since 1.49) instead of letting Symfony drop it silently, which would quietly widen access (a host
 * restriction that no longer applies) or lose a host port:
 *
 * - a service or alias registered under a removed interface's id (the 1.x way to configure several ports);
 * - a service that decorates a removed id (`#[AsDecorator]`, `decorates:`), even with `onInvalid: IGNORE`;
 * - a service whose class could not be loaded because it still implements a removed interface (an autodiscovered
 *   `#[AsDecorator]` / `#[AsAlias]` class, or one that simply implements it): Symfony records "Class "X" not found
 *   while loading "Y"" on the definition and would drop it as unused.
 *
 * Only the exact names of the removed interfaces match, so an unrelated missing class keeps Symfony's own lazy error.
 * Runs before optimization twice: at priority 1000, while the loader's errors are still on the definitions, and at 99,
 * after Symfony applied `#[AsDecorator]`.
 */
final class WikiRemovedPortsPass implements CompilerPassInterface
{
    public const PRIORITY = 1000;

    /** The second run, right after Symfony's AutowireAsDecoratorPass (priority 100) set the decorated ids. */
    public const DECORATORS_PRIORITY = 99;

    public function process(ContainerBuilder $container): void
    {
        $removed = WikiPorts::removed();
        foreach ($removed as $legacy => $twin) {
            if ($container->hasAlias($legacy) || $container->hasDefinition($legacy)) {
                throw new LogicException(sprintf('nowo_wiki: a service is registered as "%s", which 2.0 removed (deprecated since 1.36, notices since 1.49): implement %s and configure it with the same key, or alias %s to it. See UPGRADING "To 2.0.0".', $legacy, $twin, $twin));
            }
        }

        foreach ($container->getDefinitions() as $id => $definition) {
            $decorated = $definition->getDecoratedService()[0] ?? null;
            if ($decorated !== null && isset($removed[$decorated])) {
                throw new LogicException(sprintf('nowo_wiki: the service "%s" decorates "%s", which 2.0 removed (deprecated since 1.36, notices since 1.49); there is nothing left to decorate. Implement %s (configure it with the port\'s key or alias it). See UPGRADING "To 2.0.0".', $id, $decorated, $removed[$decorated]));
            }
            foreach ($definition->getErrors() as $error) {
                foreach ($removed as $legacy => $twin) {
                    if (str_contains($error, '"' . $legacy . '"')) {
                        throw new LogicException(sprintf('nowo_wiki: the service "%s" cannot be loaded: it uses %s, which 2.0 removed (deprecated since 1.36, notices since 1.49). Implement %s instead. See UPGRADING "To 2.0.0".', $id, $legacy, $twin));
                    }
                }
            }
        }
    }
}
