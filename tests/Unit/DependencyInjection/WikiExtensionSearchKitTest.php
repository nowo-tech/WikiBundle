<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection;

use Nowo\WikiBundle\DependencyInjection\Compiler\WikiSearchKitPass;
use Nowo\WikiBundle\DependencyInjection\WikiExtension;
use Nowo\WikiBundle\Search\SearchKit\WikiPageSearchDefinition;
use Nowo\WikiBundle\Search\SearchKit\WikiPageSearchKitProvider;
use Nowo\WikiBundle\Search\SearchKit\WikiSearchKitAvailability;
use Nowo\WikiBundle\Search\UserProviderWikiSearchViewerResolver;
use Nowo\WikiBundle\Search\WikiReadablePageFinder;
use Nowo\WikiBundle\Search\WikiSearchTenantResolverInterface;
use Nowo\WikiBundle\Search\WikiSearchTenantsResolverAdapter;
use Nowo\WikiBundle\Search\WikiSearchTenantsResolverInterface;
use Nowo\WikiBundle\Search\WikiSearchViewerResolverInterface;
use Nowo\WikiBundle\Search\WikiTenantPathsSearchTenantResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;

/**
 * 1.44 (Beacon KIT-85), 1.46: `nowo_wiki.search_kit_provider` registers the SearchKit group only when it is on (a literal
 * boolean) and SearchKit >= 1.10.1 is installed (otherwise nothing, as without the option).
 */
final class WikiExtensionSearchKitTest extends TestCase
{
    public function testOffByDefault(): void
    {
        $container = $this->load([]);

        self::assertFalse($container->hasDefinition(WikiPageSearchKitProvider::class));
        self::assertFalse($container->hasDefinition(WikiReadablePageFinder::class));
        self::assertFalse($container->hasAlias(WikiSearchViewerResolverInterface::class));
    }

    public function testTrueRegistersTheGroupWithTheWikisRoutesAndTheSecurityUserProviders(): void
    {
        $container = $this->load(['search_kit_provider' => true]);
        if (!WikiSearchKitAvailability::isAvailable()) {
            self::assertFalse($container->hasDefinition(WikiPageSearchKitProvider::class), 'Without SearchKit >= 1.10.1 nothing is registered.');
            self::assertFalse($container->hasDefinition(WikiPageSearchDefinition::class));

            return;
        }

        $provider = $container->getDefinition(WikiPageSearchKitProvider::class);
        self::assertSame([['priority' => 40]], $provider->getTag(WikiSearchKitPass::PROVIDER_TAG));
        self::assertSame([['priority' => 40]], $provider->getTag(WikiSearchKitPass::REFERENCE_RESOLVER_TAG));
        self::assertSame(WikiPageSearchDefinition::class, (string) $provider->getArgument('$definition'));
        self::assertFalse($provider->isPublic());

        $definition = $container->getDefinition(WikiPageSearchDefinition::class);
        self::assertSame([['priority' => 40]], $definition->getTag(WikiSearchKitAvailability::DEFINITION_TAG));
        self::assertSame('nowo_wiki_page_view', $definition->getArgument('$pageViewRoute'));
        self::assertSame([], $definition->getArgument('$pageViewRoutes'));
        self::assertSame('nowo_wiki_search', $definition->getArgument('$searchRoute'));
        self::assertTrue($definition->getArgument('$globalSearchPage'));
        self::assertSame([], $definition->getArgument('$excludedSearchSpaces'));
        self::assertSame(200, $definition->getArgument('$candidates'));
        self::assertSame(WikiReadablePageFinder::SCOPE_OWNER, $definition->getArgument('$scopeMode'));
        self::assertNull($definition->getArgument('$icon'));
        self::assertFalse($definition->getArgument('$sensitive'));
        self::assertSame('router', (string) $definition->getArgument('$urls'));
        self::assertSame('event_dispatcher', (string) $definition->getArgument('$dispatcher'));
        self::assertSame(WikiPageSearchDefinition::TENANT_CURRENT, $definition->getArgument('$tenantMode'));
        self::assertSame(WikiTenantPathsSearchTenantResolver::class, (string) $definition->getArgument('$currentTenants'));
        self::assertSame(WikiTenantPathsSearchTenantResolver::class, (string) $container->getAlias(WikiSearchTenantResolverInterface::class));
        self::assertSame(WikiSearchTenantsResolverAdapter::class, (string) $container->getAlias(WikiSearchTenantsResolverInterface::class));
        self::assertSame(WikiTenantPathsSearchTenantResolver::class, (string) $container->getDefinition(WikiSearchTenantsResolverAdapter::class)->getArgument('$inner'));

        $finder = $container->getDefinition(WikiReadablePageFinder::class);
        self::assertSame(200, $finder->getArgument('$candidates'));
        self::assertSame(WikiReadablePageFinder::SCOPE_OWNER, $finder->getArgument('$scopeMode'));
        self::assertSame(UserProviderWikiSearchViewerResolver::class, (string) $container->getAlias(WikiSearchViewerResolverInterface::class));
        self::assertSame('security.user_providers', (string) $container->getDefinition(UserProviderWikiSearchViewerResolver::class)->getArgument('$users'));
    }

    public function testTheOptions(): void
    {
        $container = $this->load(['search' => ['scope' => 'space', 'excluded_spaces' => ['hr']], 'tenant_paths' => [['path' => '/projects/{spaceSlug}/wiki', 'name_prefix' => 'project_', 'slug_requirement' => 'p-[a-z0-9]+']], 'search_kit_provider' => [
            'enabled'         => true,
            'priority'        => 74,
            'candidates'      => 500,
            'scope'           => 'slug',
            'viewer_resolver' => 'app.wiki_viewers',
            'icon'            => 'tabler:notes',
            'sensitive'       => true,
            'tenant'          => 'all',
            'tenant_resolver' => 'app.wiki_tenants',
        ]]);
        if (!WikiSearchKitAvailability::isAvailable()) {
            self::assertFalse($container->hasDefinition(WikiPageSearchKitProvider::class));

            return;
        }

        self::assertSame([['priority' => 74]], $container->getDefinition(WikiPageSearchKitProvider::class)->getTag(WikiSearchKitPass::PROVIDER_TAG));
        $definition = $container->getDefinition(WikiPageSearchDefinition::class);
        self::assertSame([['priority' => 74]], $definition->getTag(WikiSearchKitAvailability::DEFINITION_TAG));
        self::assertFalse($definition->getArgument('$globalSearchPage'));
        self::assertSame(['hr'], $definition->getArgument('$excludedSearchSpaces'));
        self::assertSame(['project_nowo_wiki_page_view'], $definition->getArgument('$pageViewRoutes'));
        self::assertSame('tabler:notes', $definition->getArgument('$icon'));
        self::assertTrue($definition->getArgument('$sensitive'));
        self::assertSame(WikiPageSearchDefinition::TENANT_ALL_MODE, $definition->getArgument('$tenantMode'));
        self::assertSame('app.wiki_tenants', (string) $container->getAlias(WikiSearchTenantResolverInterface::class));
        self::assertSame('app.wiki_tenants', (string) $definition->getArgument('$currentTenants'));
        self::assertFalse($container->hasDefinition(WikiTenantPathsSearchTenantResolver::class));
        self::assertSame(500, $definition->getArgument('$candidates'));
        self::assertSame('slug', $definition->getArgument('$scopeMode'));
        self::assertSame(500, $container->getDefinition(WikiReadablePageFinder::class)->getArgument('$candidates'));
        self::assertSame('app.wiki_viewers', (string) $container->getAlias(WikiSearchViewerResolverInterface::class));
        self::assertFalse($container->hasDefinition(UserProviderWikiSearchViewerResolver::class));
    }

    public function testSensitiveIsALiteralBoolean(): void
    {
        if (!WikiSearchKitAvailability::isAvailable()) {
            self::markTestSkipped('nowo-tech/search-kit-bundle >= 1.10.1 is not installed (PHP >= 8.3).');
        }
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag());
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        $container->registerExtension(new WikiExtension());
        $container->loadFromExtension('nowo_wiki', ['user_class' => 'App\\Entity\\User', 'search_kit_provider' => ['enabled' => true, 'sensitive' => '%env(bool:WIKI_SEARCH_SENSITIVE)%']]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('nowo_wiki.search_kit_provider.sensitive must be a literal boolean');
        (new MergeExtensionConfigurationPass())->process($container);
    }

    public function testTheSwitchIsALiteralBoolean(): void
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag());
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        $container->registerExtension(new WikiExtension());
        $container->loadFromExtension('nowo_wiki', ['user_class' => 'App\\Entity\\User', 'search_kit_provider' => ['enabled' => '%env(bool:WIKI_SEARCH_KIT)%']]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('nowo_wiki.search_kit_provider.enabled must be a literal boolean');
        (new MergeExtensionConfigurationPass())->process($container);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User'] + $config], $container);

        return $container;
    }
}
