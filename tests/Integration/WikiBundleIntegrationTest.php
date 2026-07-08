<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use Nowo\WikiBundle\DependencyInjection\WikiExtension;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Routing\WikiRouteLoader;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;
use Nowo\WikiBundle\WikiBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class WikiBundleIntegrationTest extends TestCase
{
    public function testExtensionAliasMatchesBundleConfiguration(): void
    {
        $bundle = new WikiBundle();
        self::assertSame('nowo_wiki', $bundle->getContainerExtension()->getAlias());
    }

    public function testContainerBuildsCoreServicesFromMinimalConfig(): void
    {
        $container = new ContainerBuilder();
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User']], $container);

        self::assertTrue($container->hasAlias(WikiAccessCheckerInterface::class));
        self::assertTrue($container->hasDefinition(WikiRouteLoader::class));
        self::assertTrue($container->hasAlias(WikiSpaceRepositoryInterface::class));
        self::assertTrue($container->hasAlias(WikiPageRepositoryInterface::class));
        self::assertTrue($container->hasAlias(WikiPageRevisionRepositoryInterface::class));
        self::assertTrue($container->hasAlias(WikiHtmlSanitizerInterface::class));
        self::assertTrue($container->hasDefinition(\Nowo\WikiBundle\Security\WikiHtmlSanitizer::class));
        self::assertSame(
            \Nowo\WikiBundle\Security\WikiHtmlSanitizer::class,
            (string) $container->getAlias(WikiHtmlSanitizerInterface::class),
        );
    }
}
