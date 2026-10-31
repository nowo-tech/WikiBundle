<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\WikiBundle\DependencyInjection\Compiler\TwigPathsPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Twig\Loader\FilesystemLoader;

use function dirname;

final class TwigPathsPassTest extends TestCase
{
    public function testAddsBundleViewsPathToNativeLoader(): void
    {
        $loader    = new Definition(FilesystemLoader::class);
        $container = new ContainerBuilder();
        $container->setDefinition('twig.loader.native_filesystem', $loader);

        (new TwigPathsPass())->process($container);

        $calls = $loader->getMethodCalls();
        self::assertSame('addPath', $calls[0][0]);
        self::assertSame('NowoWikiBundle', $calls[0][1][1]);
        self::assertSame(['addPath', '!NowoWikiBundle'], [$calls[1][0], $calls[1][1][1]]);
        self::assertSame($calls[0][1][0], $calls[1][1][0]);
    }

    public function testOriginalNamespaceResolvesTheBundleViewWhenTheAppOverridesIt(): void
    {
        $projectDir   = sys_get_temp_dir() . '/wiki-twig-' . uniqid();
        $overridePath = $projectDir . '/templates/bundles/NowoWikiBundle/manage';
        mkdir($overridePath, 0777, true);
        file_put_contents($overridePath . '/_page_tree.html.twig', 'app-override-marker');

        $loader    = new Definition(FilesystemLoader::class);
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $projectDir);
        $container->setDefinition('twig.loader.native', $loader);
        (new TwigPathsPass())->process($container);

        $twigLoader = new FilesystemLoader();
        foreach ($loader->getMethodCalls() as [$method, $arguments]) {
            $twigLoader->{$method}(...$arguments);
        }

        self::assertSame('app-override-marker', $twigLoader->getSourceContext('@NowoWikiBundle/manage/_page_tree.html.twig')->getCode());
        self::assertStringNotContainsString('app-override-marker', $twigLoader->getSourceContext('@!NowoWikiBundle/manage/_page_tree.html.twig')->getCode());

        unlink($overridePath . '/_page_tree.html.twig');
        rmdir($overridePath);
        rmdir(dirname($overridePath));
        rmdir(dirname($overridePath, 2));
        rmdir(dirname($overridePath, 3));
        rmdir($projectDir);
    }

    public function testPrependsApplicationOverridePathWhenPresent(): void
    {
        $projectDir   = sys_get_temp_dir() . '/wiki-twig-' . uniqid();
        $overridePath = $projectDir . '/templates/bundles/NowoWikiBundle';
        mkdir($overridePath, 0777, true);

        $loader    = new Definition(FilesystemLoader::class);
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $projectDir);
        $container->setDefinition('twig.loader.native', $loader);

        (new TwigPathsPass())->process($container);

        self::assertSame('prependPath', $loader->getMethodCalls()[0][0]);

        rmdir($overridePath);
        rmdir(dirname($overridePath));
        rmdir(dirname($overridePath, 2));
        rmdir($projectDir);
    }

    public function testNoOpWhenTwigLoaderMissing(): void
    {
        $container = new ContainerBuilder();
        (new TwigPathsPass())->process($container);
        self::assertFalse($container->hasDefinition('twig.loader.native_filesystem'));
    }

    public function testUsesNativeLoaderDefinitionWhenRegisteredDirectly(): void
    {
        $loader    = new Definition(FilesystemLoader::class);
        $container = new ContainerBuilder();
        $container->setDefinition('twig.loader.native', $loader);

        (new TwigPathsPass())->process($container);

        self::assertNotEmpty($loader->getMethodCalls());
    }

    public function testResolvesNativeLoaderAliasChain(): void
    {
        $loader    = new Definition(FilesystemLoader::class);
        $container = new ContainerBuilder();
        $container->setDefinition('custom.native.loader', $loader);
        $container->setAlias('twig.loader.native', 'custom.native.loader');

        (new TwigPathsPass())->process($container);

        self::assertNotEmpty($loader->getMethodCalls());
    }

    public function testReturnsNullWhenAliasDoesNotResolveToDefinition(): void
    {
        $container = new ContainerBuilder();
        $container->setAlias('twig.loader.native', 'missing.loader');

        (new TwigPathsPass())->process($container);

        self::assertFalse($container->hasDefinition('missing.loader'));
    }

    public function testResolvesMultiHopNativeLoaderAliasChain(): void
    {
        $loader    = new Definition(FilesystemLoader::class);
        $container = new ContainerBuilder();
        $container->setDefinition('custom.native.loader', $loader);
        $container->setAlias('intermediate.loader', 'custom.native.loader');
        $container->setAlias('twig.loader.native', 'intermediate.loader');

        (new TwigPathsPass())->process($container);

        self::assertNotEmpty($loader->getMethodCalls());
    }
}
