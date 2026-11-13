<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\DependencyInjection\Compiler;

use Nowo\WikiBundle\DependencyInjection\WikiDoctrineMapping;
use Nowo\WikiBundle\Doctrine\WikiExcludedEntityNamespaceDriver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

use function array_keys;
use function array_unshift;
use function is_array;
use function is_string;
use function sprintf;
use function str_starts_with;

/**
 * Keeps the wiki entities off the entity managers other than `nowo_wiki.database.entity_manager` (1.33.0, the
 * LegalKit 1.13.1 pattern for DoctrineBundle 3.4).
 *
 * On every other manager whose metadata driver chain has no driver of its own for the wiki namespace, a
 * {@see WikiExcludedEntityNamespaceDriver} is put first in the chain, so a default driver (DoctrineBundle 3.4
 * `auto_discover_entities`) cannot claim the wiki entities there: `doctrine:schema:update --em=<other>` no longer
 * creates the wiki tables a second time and `getManagerForClass()` answers the wiki's manager. A chain that maps the
 * wiki namespace explicitly is left as it is with the attribute mapping (as up to 1.31). With `database.mapping_type:
 * xml` (new in 1.33) the other managers lose any wiki driver too (e.g. `auto_mapping` mapping the bundle's attributes
 * on the default manager while the XML files are mapped on another one).
 *
 * Nothing happens when the configured manager is not a known Doctrine entity manager (DoctrineBundle reports it).
 */
final class WikiMappingManagerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $database = $container->hasParameter('nowo_wiki.database') ? $container->getParameter('nowo_wiki.database') : null;
        $managers = $container->hasParameter('doctrine.entity_managers') ? $container->getParameter('doctrine.entity_managers') : null;
        if (!is_array($database) || !is_array($managers)) {
            return;
        }
        $name = $database['entity_manager'] ?? 'default';
        if (!is_string($name) || !isset($managers[$name])) {
            return;
        }
        $xml = ($database['mapping_type'] ?? 'attribute') === 'xml';

        foreach (array_keys($managers) as $manager) {
            $chainId = sprintf('doctrine.orm.%s_metadata_driver', $manager);
            if ($manager === $name || !$container->hasDefinition($chainId)) {
                continue;
            }
            $chain    = $container->getDefinition($chainId);
            $calls    = [];
            $explicit = false;
            foreach ($chain->getMethodCalls() as $call) {
                $namespace = $call[0] === 'addDriver' ? ($call[1][1] ?? null) : null;
                if (is_string($namespace) && str_starts_with($namespace, WikiDoctrineMapping::ENTITY_NAMESPACE)) {
                    if ($xml) {
                        continue;
                    }
                    $explicit = true;
                }
                $calls[] = $call;
            }
            if (!$explicit) {
                // First in the chain: a chain asks its namespace drivers in order, then its default driver.
                array_unshift($calls, ['addDriver', [new Definition(WikiExcludedEntityNamespaceDriver::class), WikiDoctrineMapping::ENTITY_NAMESPACE]]);
            }
            $chain->setMethodCalls($calls);
        }
    }
}
