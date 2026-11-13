<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\DependencyInjection\Compiler;

use LogicException;
use Nowo\WikiBundle\Link\WikiObjectLinkResolverInterface;
use Nowo\WikiBundle\Link\WikiObjectLinkSourceInterface;
use Nowo\WikiBundle\Port\WikiPorts;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function array_map;
use function class_exists;
use function is_a;
use function is_string;
use function sprintf;
use function str_starts_with;

/**
 * Wires the framework-free twins of the host ports (1.32.0, REQ-ARCH-001 rules 1 and 3; {@see WikiPorts}).
 *
 * For every port the bundle asks through its deprecated interface id:
 *
 * - the configured service implements the deprecated interface: nothing changes (also when it implements the twin);
 * - it implements only the twin: the interface id points to the port's gate around it;
 * - it implements neither: the build fails, naming the configuration key;
 * - its class cannot be known before compilation (a factory, a synthetic service): the gate is built by
 *   {@see WikiPorts::port()}, which fails closed when the service is built.
 *
 * A host may also alias the twin interface itself: it is used while the deprecated interface still points to the
 * bundle's default; when both name different host services the build fails (keep one).
 *
 * Tagged object link resolvers (`nowo_wiki.object_link_resolver`) must implement the deprecated interface or
 * {@see WikiObjectLinkSourceInterface}.
 */
final class WikiPortsPass implements CompilerPassInterface
{
    public const GATE_PREFIX = 'nowo_wiki.port_gate.';

    public const OBJECT_LINK_TAG = 'nowo_wiki.object_link_resolver';

    public function process(ContainerBuilder $container): void
    {
        foreach (WikiPorts::PORTS as $name => [$legacy, $twin, $gate, $dependencies, $option]) {
            if (!$container->hasAlias($legacy) && !$container->hasDefinition($legacy)) {
                continue;
            }
            $this->preferTwinAlias($container, $legacy, $twin, $option);
            $this->wire($container, $name, $legacy, $twin, $gate, $dependencies, $option);
        }

        foreach ($container->findTaggedServiceIds(self::OBJECT_LINK_TAG) as $id => $tags) {
            $class = $this->classOf($container, $id);
            if ($class !== null && !is_a($class, WikiObjectLinkResolverInterface::class, true) && !is_a($class, WikiObjectLinkSourceInterface::class, true)) {
                throw new LogicException(sprintf('nowo_wiki: the service "%s" (%s) tagged "%s" must implement "%s" (or the deprecated "%s").', $id, $class, self::OBJECT_LINK_TAG, WikiObjectLinkSourceInterface::class, WikiObjectLinkResolverInterface::class));
            }
        }
    }

    /**
     * A host alias of the twin interface wins over the bundle's default service of the deprecated one.
     */
    private function preferTwinAlias(ContainerBuilder $container, string $legacy, string $twin, string $option): void
    {
        if (!$container->hasAlias($twin) && !$container->hasDefinition($twin)) {
            return;
        }
        $twinTarget   = $this->resolve($container, $twin);
        $legacyTarget = $this->resolve($container, $legacy);
        if ($twinTarget === $legacyTarget) {
            return;
        }
        $legacyClass = $this->classOf($container, $legacyTarget);
        // A bundle default (a class of the bundle, not of its test suite) gives way; a host service does not.
        if ($legacyClass === null || !str_starts_with($legacyClass, 'Nowo\\WikiBundle\\') || str_starts_with($legacyClass, 'Nowo\\WikiBundle\\Tests\\')) {
            throw new LogicException(sprintf('nowo_wiki: "%s" is served by "%s" and the deprecated "%s" (%s) by "%s": keep only one of them.', $twin, $twinTarget, $legacy, $option, $legacyTarget));
        }

        $container->setAlias($legacy, $twinTarget)->setPublic($container->hasAlias($legacy) && $container->getAlias($legacy)->isPublic());
    }

    /**
     * @param list<string> $dependencies
     */
    private function wire(ContainerBuilder $container, string $name, string $legacy, string $twin, string $gate, array $dependencies, string $option): void
    {
        $target = $this->resolve($container, $legacy);
        $class  = $this->classOf($container, $target);
        if ($class !== null && is_a($class, $legacy, true)) {
            return;
        }
        if ($class !== null && !is_a($class, $twin, true)) {
            throw new LogicException(sprintf('nowo_wiki: the service "%s" (%s) of %s must implement "%s" (or the deprecated "%s").', $target, $class, $option, $twin, $legacy));
        }

        $public = $container->hasAlias($legacy) ? $container->getAlias($legacy)->isPublic() : $container->getDefinition($legacy)->isPublic();
        if ($target === $legacy) {
            // The service is registered under the interface id itself: move it behind the gate.
            $target = self::GATE_PREFIX . $name . '.inner';
            $container->setDefinition($target, $container->getDefinition($legacy));
            $container->removeDefinition($legacy);
        }

        $references = array_map(static fn (string $id): Reference => new Reference($id), $dependencies);
        $definition = $class === null
            ? (new Definition($legacy))->setFactory([WikiPorts::class, 'port'])->setArguments([$name, new Reference($target), ...$references])
            : new Definition($gate, [new Reference($target), ...$references]);
        $gateId = self::GATE_PREFIX . $name;
        $container->setDefinition($gateId, $definition);
        $container->setAlias($legacy, $gateId)->setPublic($public);
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
     * The class of a service when the build can know it (no factory, not synthetic, an existing class).
     */
    private function classOf(ContainerBuilder $container, string $id): ?string
    {
        if (!$container->hasDefinition($id)) {
            return null;
        }
        $definition = $container->getDefinition($id);
        if ($definition->getFactory() !== null || $definition->isSynthetic()) {
            return null;
        }
        $class = $container->getParameterBag()->resolveValue($definition->getClass() ?? $id);

        return is_string($class) && class_exists($class) ? $class : null;
    }
}
