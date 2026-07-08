<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit;

use Nowo\WikiBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\WikiBundle\WikiBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class WikiBundleTest extends TestCase
{
    public function testTranslationDomain(): void
    {
        self::assertSame('NowoWikiBundle', WikiBundle::TRANSLATION_DOMAIN);
    }

    public function testExtensionAlias(): void
    {
        $bundle = new WikiBundle();
        self::assertSame('nowo_wiki', $bundle->getContainerExtension()->getAlias());
    }

    public function testBuildRegistersTwigPathsPass(): void
    {
        $container = new ContainerBuilder();
        (new WikiBundle())->build($container);

        $passes = $container->getCompilerPassConfig()->getPasses();
        self::assertTrue(
            (bool) array_filter(
                $passes,
                static fn (CompilerPassInterface $pass): bool => $pass instanceof TwigPathsPass,
            ),
        );
    }
}
