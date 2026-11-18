<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection;

use Nowo\WikiBundle\DependencyInjection\WikiExtension;
use Nowo\WikiBundle\Search\SearchKit\WikiPageSearchKitProvider;
use Nowo\WikiBundle\Search\UserProviderWikiSearchViewerResolver;
use Nowo\WikiBundle\Search\WikiReadablePageFinder;
use Nowo\WikiBundle\Search\WikiSearchViewerResolverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;

require_once __DIR__ . '/../../Stub/SearchKit/SearchKit.php';

/**
 * 1.44 (Beacon KIT-85): `nowo_wiki.search_kit_provider` registers the SearchKit provider only when it is on (a literal
 * boolean) and SearchKit >= 1.8 is there (the test stubs stand in for it).
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

    public function testTrueRegistersTheProviderWithTheWikisRoutesAndTheSecurityUserProviders(): void
    {
        $container = $this->load(['search_kit_provider' => true]);

        $provider = $container->getDefinition(WikiPageSearchKitProvider::class);
        self::assertSame([['priority' => 40]], $provider->getTag('nowo_search_kit.provider'));
        self::assertSame([['priority' => 40]], $provider->getTag('nowo_search_kit.reference_resolver'));
        self::assertSame('nowo_wiki_page_view', $provider->getArgument('$pageViewRoute'));
        self::assertSame('nowo_wiki_search', $provider->getArgument('$searchRoute'));
        self::assertTrue($provider->getArgument('$globalSearchPage'));
        self::assertNull($provider->getArgument('$icon'));
        self::assertSame('router', (string) $provider->getArgument('$urls'));
        self::assertFalse($provider->isPublic());

        $finder = $container->getDefinition(WikiReadablePageFinder::class);
        self::assertSame(200, $finder->getArgument('$candidates'));
        self::assertSame(WikiReadablePageFinder::SCOPE_OWNER, $finder->getArgument('$scopeMode'));
        self::assertSame(UserProviderWikiSearchViewerResolver::class, (string) $container->getAlias(WikiSearchViewerResolverInterface::class));
        self::assertSame('security.user_providers', (string) $container->getDefinition(UserProviderWikiSearchViewerResolver::class)->getArgument('$users'));
    }

    public function testTheOptions(): void
    {
        $container = $this->load(['search' => ['scope' => 'space'], 'search_kit_provider' => [
            'enabled'         => true,
            'priority'        => 74,
            'candidates'      => 500,
            'scope'           => 'slug',
            'viewer_resolver' => 'app.wiki_viewers',
            'icon'            => 'tabler:notes',
        ]]);

        $provider = $container->getDefinition(WikiPageSearchKitProvider::class);
        self::assertSame([['priority' => 74]], $provider->getTag('nowo_search_kit.provider'));
        self::assertFalse($provider->getArgument('$globalSearchPage'));
        self::assertSame('tabler:notes', $provider->getArgument('$icon'));
        self::assertSame(500, $container->getDefinition(WikiReadablePageFinder::class)->getArgument('$candidates'));
        self::assertSame('slug', $container->getDefinition(WikiReadablePageFinder::class)->getArgument('$scopeMode'));
        self::assertSame('app.wiki_viewers', (string) $container->getAlias(WikiSearchViewerResolverInterface::class));
        self::assertFalse($container->hasDefinition(UserProviderWikiSearchViewerResolver::class));
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
