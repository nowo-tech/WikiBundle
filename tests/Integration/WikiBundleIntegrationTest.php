<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use LogicException;
use Nowo\WikiBundle\DependencyInjection\WikiExtension;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Routing\WikiRouteLoader;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;
use Nowo\WikiBundle\WikiBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
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
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User']], $container);

        self::assertTrue($container->hasAlias(WikiAccessCheckerInterface::class));
        self::assertTrue($container->hasDefinition(WikiRouteLoader::class));
        self::assertTrue($container->hasAlias(WikiSpaceRepositoryInterface::class));
        self::assertTrue($container->hasAlias(WikiPageRepositoryInterface::class));
        self::assertTrue($container->hasAlias(WikiPageRevisionRepositoryInterface::class));
        self::assertTrue($container->hasAlias(WikiHtmlSanitizerInterface::class));
        self::assertTrue($container->hasDefinition(WikiHtmlSanitizer::class));
        self::assertFalse($container->getParameter('nowo_wiki.security.allow_unauthenticated'));
        self::assertSame(
            WikiHtmlSanitizer::class,
            (string) $container->getAlias(WikiHtmlSanitizerInterface::class),
        );
    }

    public function testLoadThrowsWhenSecurityBundleMissingAndUnauthenticatedDisallowed(): void
    {
        $container = new ContainerBuilder();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('allow_unauthenticated');

        (new WikiExtension())->load([[
            'user_class' => 'App\\Entity\\User',
            'security'   => ['allow_unauthenticated' => false],
        ]], $container);
    }

    public function testLoadRegistersAllowAllAccessCheckerWhenUnauthenticatedAllowed(): void
    {
        $container = new ContainerBuilder();

        (new WikiExtension())->load([[
            'user_class' => 'App\\Entity\\User',
            'security'   => ['allow_unauthenticated' => true],
        ]], $container);

        self::assertTrue($container->hasDefinition('nowo_wiki.access_checker.allow_all'));
        self::assertSame(
            'nowo_wiki.access_checker.allow_all',
            (string) $container->getAlias(WikiAccessCheckerInterface::class),
        );
        self::assertTrue($container->getParameter('nowo_wiki.security.allow_unauthenticated'));
    }
}
