<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\WikiBundle\DependencyInjection\Compiler\WikiSearchKitPass;
use Nowo\WikiBundle\Search\SearchKit\WikiPageSearchDefinition;
use Nowo\WikiBundle\Search\SearchKit\WikiPageSearchKitProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

use function array_keys;

/**
 * 1.46: the SearchKit group runs once — through the wiki's provider, around the provider SearchKit built — or not at all.
 */
final class WikiSearchKitPassTest extends TestCase
{
    public function testNothingWithoutTheGroup(): void
    {
        $container = new ContainerBuilder();
        (new WikiSearchKitPass())->process($container);

        self::assertFalse($container->hasDefinition(WikiPageSearchKitProvider::class));
    }

    public function testTheGeneratedProviderIsHandedToTheWikisProviderAndLosesItsTags(): void
    {
        $container = $this->container();
        $generated = WikiSearchKitPass::PROVIDER_PREFIX . WikiPageSearchDefinition::class;
        $container->setDefinition($generated, (new Definition(stdClass::class))
            ->addTag(WikiSearchKitPass::PROVIDER_TAG, ['priority' => 40])
            ->addTag(WikiSearchKitPass::REFERENCE_RESOLVER_TAG, ['priority' => 40])
            ->addTag('other'));

        (new WikiSearchKitPass())->process($container);

        self::assertSame(['other'], array_keys($container->getDefinition($generated)->getTags()));
        self::assertSame($generated, (string) $container->getDefinition(WikiPageSearchKitProvider::class)->getArgument('$provider'));
        self::assertTrue($container->hasDefinition(WikiPageSearchDefinition::class));
    }

    public function testWithoutSearchKitsBundleNothingOfTheGroupStays(): void
    {
        $container = $this->container();

        (new WikiSearchKitPass())->process($container);

        self::assertFalse($container->hasDefinition(WikiPageSearchKitProvider::class));
        self::assertFalse($container->hasDefinition(WikiPageSearchDefinition::class));
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(WikiPageSearchDefinition::class, new Definition(WikiPageSearchDefinition::class));
        $container->setDefinition(WikiPageSearchKitProvider::class, (new Definition(WikiPageSearchKitProvider::class))->setArguments(['$provider' => null]));

        return $container;
    }
}
