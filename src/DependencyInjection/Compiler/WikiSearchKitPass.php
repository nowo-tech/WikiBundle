<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\DependencyInjection\Compiler;

use Nowo\WikiBundle\Search\SearchKit\WikiPageSearchDefinition;
use Nowo\WikiBundle\Search\SearchKit\WikiPageSearchKitProvider;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * 1.46: hands the provider SearchKit built for {@see WikiPageSearchDefinition} (its EntitySearchPass, which runs before
 * this pass) to {@see WikiPageSearchKitProvider}, the one the engine and the recents call: the generated provider loses
 * its own provider and reference resolver tags, so the `wiki` group runs once. Without SearchKit's bundle registered
 * (no generated provider), nothing of the group stays. Runs only when `search_kit_provider` registered the group.
 */
final class WikiSearchKitPass implements CompilerPassInterface
{
    /** Priority of the pass: after SearchKit's EntitySearchPass (0), before the optimization passes. */
    public const PRIORITY = -10;

    /** SearchKit's EntitySearchPass::PROVIDER_PREFIX (literals: SearchKit is optional, this pass loads none of it). */
    public const PROVIDER_PREFIX = 'nowo_search_kit.entity_provider.';

    /** SearchKit's provider tag (SearchProviderInterface::TAG). */
    public const PROVIDER_TAG = 'nowo_search_kit.provider';

    /** SearchKit's reference resolver tag (SearchReferenceResolverInterface::REFERENCE_RESOLVER_TAG). */
    public const REFERENCE_RESOLVER_TAG = 'nowo_search_kit.reference_resolver';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(WikiPageSearchKitProvider::class)) {
            return;
        }
        $generated = self::PROVIDER_PREFIX . WikiPageSearchDefinition::class;
        if (!$container->hasDefinition($generated)) {
            $container->removeDefinition(WikiPageSearchKitProvider::class);
            $container->removeDefinition(WikiPageSearchDefinition::class);

            return;
        }
        $container->getDefinition($generated)
            ->clearTag(self::PROVIDER_TAG)
            ->clearTag(self::REFERENCE_RESOLVER_TAG);
        $container->getDefinition(WikiPageSearchKitProvider::class)->setArgument('$provider', new Reference($generated));
    }
}
