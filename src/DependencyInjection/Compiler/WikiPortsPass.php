<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\DependencyInjection\Compiler;

use LogicException;
use Nowo\WikiBundle\Link\WikiObjectLinkSourceInterface;
use Nowo\WikiBundle\Port\Internal\WikiObjectLinkResolverInterface;
use Nowo\WikiBundle\Port\WikiPorts;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function array_map;
use function is_a;
use function is_string;
use function sprintf;
use function str_starts_with;

/**
 * Wires the host ports (REQ-ARCH-001 rules 1 and 3; {@see WikiPorts}). Since 2.0 a host implements only the
 * framework-free twin; for every port the bundle asks through its bundle-side interface id (`Port\Internal\*`):
 *
 * - the configured service is one of the bundle's defaults (it implements the bundle-side interface): nothing changes;
 * - it implements the twin: the bundle-side id points to the port's gate around it;
 * - it implements neither: the build fails, naming the configuration key and the twin;
 * - its class cannot be known before compilation (a factory, a synthetic service): the gate is built by
 *   {@see WikiPorts::port()}, which fails closed when the service is built.
 *
 * A host may also alias the twin interface itself: it replaces the bundle's default; when the configuration key names
 * another host service the build fails (keep one).
 *
 * Tagged object link sources (`nowo_wiki.object_link_resolver`) must implement {@see WikiObjectLinkSourceInterface}.
 * The removed 1.x interfaces themselves are refused by {@see WikiRemovedPortsPass}.
 */
final class WikiPortsPass implements CompilerPassInterface
{
    public const GATE_PREFIX = 'nowo_wiki.port_gate.';

    public const OBJECT_LINK_TAG = 'nowo_wiki.object_link_resolver';

    public function process(ContainerBuilder $container): void
    {
        foreach (WikiPorts::PORTS as $name => [$internal, $twin, $gate, $dependencies, $option]) {
            if (!$container->hasAlias($internal) && !$container->hasDefinition($internal)) {
                continue;
            }
            $this->preferTwinAlias($container, $internal, $twin, $option);
            $this->wire($container, $name, $internal, $twin, $gate, $dependencies, $option);
        }

        foreach ($container->findTaggedServiceIds(self::OBJECT_LINK_TAG) as $id => $tags) {
            $class = $this->classOf($container, $id, $id);
            if ($class !== null && !is_a($class, WikiObjectLinkSourceInterface::class, true) && (!is_a($class, WikiObjectLinkResolverInterface::class, true) || !WikiPorts::isBundleClass($class))) {
                throw new LogicException(sprintf('nowo_wiki: the service "%s" (%s) tagged "%s" must implement "%s" (2.0 removed %s; see UPGRADING "To 2.0.0").', $id, $class, self::OBJECT_LINK_TAG, WikiObjectLinkSourceInterface::class, WikiPorts::REMOVED_OBJECT_LINK_RESOLVER));
            }
        }
    }

    /**
     * A host alias of the twin interface wins over the bundle's default service of the port.
     */
    private function preferTwinAlias(ContainerBuilder $container, string $internal, string $twin, string $option): void
    {
        if (!$container->hasAlias($twin) && !$container->hasDefinition($twin)) {
            return;
        }
        $twinTarget     = $this->resolve($container, $twin);
        $internalTarget = $this->resolve($container, $internal);
        if ($twinTarget === $internalTarget) {
            return;
        }
        $internalClass = $this->classOf($container, $internalTarget, $option);
        // A bundle default (a class of the bundle, not of its test suite) gives way; a host service does not.
        if ($internalClass === null || !WikiPorts::isBundleClass($internalClass) || str_starts_with($internalClass, 'Nowo\\WikiBundle\\Tests\\')) {
            throw new LogicException(sprintf('nowo_wiki: "%s" is served by "%s" and %s by "%s": keep only one of them.', $twin, $twinTarget, $option, $internalTarget));
        }

        $container->setAlias($internal, $twinTarget)->setPublic($container->hasAlias($internal) && $container->getAlias($internal)->isPublic());
    }

    /**
     * @param list<string> $dependencies
     */
    private function wire(ContainerBuilder $container, string $name, string $internal, string $twin, string $gate, array $dependencies, string $option): void
    {
        $target = $this->resolve($container, $internal);
        $class  = $this->classOf($container, $target, sprintf('the service "%s" of %s', $target, $option));
        if ($class !== null && is_a($class, $internal, true) && WikiPorts::isBundleClass($class)) {
            return;
        }
        if ($class !== null && !is_a($class, $twin, true)) {
            throw new LogicException(sprintf('nowo_wiki: the service "%s" (%s) of %s must implement "%s" (2.0 removed the 1.x interface; see UPGRADING "To 2.0.0").', $target, $class, $option, $twin));
        }

        $public = $container->hasAlias($internal) ? $container->getAlias($internal)->isPublic() : $container->getDefinition($internal)->isPublic();
        if ($target === $internal) {
            // The service is registered under the interface id itself: move it behind the gate.
            $target = self::GATE_PREFIX . $name . '.inner';
            $container->setDefinition($target, $container->getDefinition($internal));
            $container->removeDefinition($internal);
        }

        $references = array_map(static fn (string $id): Reference => new Reference($id), $dependencies);
        $definition = $class === null
            ? (new Definition($internal))->setFactory([WikiPorts::class, 'port'])->setArguments([$name, new Reference($target), ...$references])
            : new Definition($gate, [new Reference($target), ...$references]);
        $gateId = self::GATE_PREFIX . $name;
        $container->setDefinition($gateId, $definition);
        $container->setAlias($internal, $gateId)->setPublic($public);
    }

    private function resolve(ContainerBuilder $container, string $id): string
    {
        $seen = [];
        while ($container->hasAlias($id) && !isset($seen[$id])) {
            $seen[$id] = true;
            $id        = (string) $container->getAlias($id);
        }

        return $id;
    }

    /**
     * The class of a service when the build can know it (no factory, not synthetic, a loadable class).
     *
     * @throws LogicException when the class still implements a removed 1.x host interface
     */
    private function classOf(ContainerBuilder $container, string $id, string $owner): ?string
    {
        if (!$container->hasDefinition($id)) {
            return null;
        }
        $definition = $container->getDefinition($id);
        if ($definition->getFactory() !== null || $definition->isSynthetic()) {
            return null;
        }
        $class = $container->getParameterBag()->resolveValue($definition->getClass() ?? $id);

        return is_string($class) && WikiPorts::loadable($class, $owner) ? $class : null;
    }
}
