<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Nowo\WikiBundle\DependencyInjection\WikiDoctrineMapping;
use Nowo\WikiBundle\DependencyInjection\WikiExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function is_dir;
use function realpath;

/**
 * 1.34.0: `database.mapping_type` picks the Doctrine mapping prepended for the wiki entities.
 */
final class WikiDoctrineMappingTest extends TestCase
{
    public function testTheAttributeMappingIsPrependedByDefaultAsBefore(): void
    {
        $container = $this->container(['user_class' => 'App\\Entity\\User']);

        (new WikiExtension())->prepend($container);

        self::assertSame([['orm' => ['mappings' => ['WikiBundle' => ['type' => 'attribute', 'is_bundle' => true]]]]], $container->getExtensionConfig('doctrine'));
        self::assertSame('attribute', $container->getParameter(WikiDoctrineMapping::PREPENDED_TYPE_PARAMETER));
        self::assertFalse($container->hasParameter(WikiDoctrineMapping::PREPENDED_MANAGER_PARAMETER));
    }

    public function testTheXmlMappingGoesOnTheDefaultManager(): void
    {
        $container = $this->container(['user_class' => 'App\\Entity\\User', 'database' => ['mapping_type' => 'xml']]);

        (new WikiExtension())->prepend($container);

        $mapping = $container->getExtensionConfig('doctrine')[0]['orm']['mappings']['WikiBundle'] ?? null;
        self::assertSame(['type' => 'xml', 'is_bundle' => false, 'dir' => WikiDoctrineMapping::xmlDirectory(), 'prefix' => 'Nowo\\WikiBundle\\Entity'], $mapping);
        self::assertArrayNotHasKey('alias', $mapping, 'DoctrineBundle 3 has no mapping alias');
        self::assertTrue(is_dir(WikiDoctrineMapping::xmlDirectory()));
        self::assertStringEndsWith('/src/Resources/config/doctrine-xml', (string) realpath(WikiDoctrineMapping::xmlDirectory()));
        self::assertSame('default', $container->getParameter(WikiDoctrineMapping::PREPENDED_MANAGER_PARAMETER));
    }

    public function testTheXmlMappingGoesOnTheConfiguredManagerTheLastConfigWins(): void
    {
        $container = $this->container(
            ['user_class' => 'App\\Entity\\User', 'database' => ['mapping_type' => 'attribute', 'entity_manager' => 'other']],
            ['database' => ['mapping_type' => 'xml', 'entity_manager' => 'wiki']],
            ['database' => 'not an array'],
            ['database' => ['entity_manager' => '%env(WIKI_EM)%']],
        );

        (new WikiExtension())->prepend($container);

        self::assertSame(
            ['orm' => ['entity_managers' => ['wiki' => ['mappings' => ['WikiBundle' => WikiDoctrineMapping::xmlMapping()]]]]],
            $container->getExtensionConfig('doctrine')[0],
        );
        self::assertSame('wiki', $container->getParameter(WikiDoctrineMapping::PREPENDED_MANAGER_PARAMETER));
    }

    public function testNothingIsPrependedWithoutDoctrineBundle(): void
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig('nowo_wiki', ['user_class' => 'App\\Entity\\User', 'database' => ['mapping_type' => 'xml']]);

        WikiDoctrineMapping::prepend($container);

        self::assertFalse($container->hasParameter(WikiDoctrineMapping::PREPENDED_TYPE_PARAMETER));
    }

    public function testLoadAcceptsWhatThePrependRead(): void
    {
        $config    = ['user_class' => 'App\\Entity\\User', 'database' => ['mapping_type' => 'xml', 'entity_manager' => 'wiki']];
        $container = $this->container($config);
        $extension = new WikiExtension();
        $extension->prepend($container);
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);

        $extension->load([$config], $container);

        self::assertSame('xml', $container->getParameter('nowo_wiki.database.mapping_type'));
    }

    public function testAPlaceholderMappingTypeIsRefused(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(WikiDoctrineMapping::PREPENDED_TYPE_PARAMETER, 'attribute');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('literal "attribute" or "xml"');

        WikiDoctrineMapping::check($container, ['mapping_type' => 'xml', 'entity_manager' => 'default']);
    }

    public function testAPlaceholderManagerIsRefusedWithTheXmlMapping(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(WikiDoctrineMapping::PREPENDED_TYPE_PARAMETER, 'xml');
        $container->setParameter(WikiDoctrineMapping::PREPENDED_MANAGER_PARAMETER, 'default');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('literal manager name');

        WikiDoctrineMapping::check($container, ['mapping_type' => 'xml', 'entity_manager' => '%env(WIKI_EM)%']);
    }

    public function testCheckIgnoresAManagerPlaceholderWithTheAttributes(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(WikiDoctrineMapping::PREPENDED_TYPE_PARAMETER, 'attribute');

        WikiDoctrineMapping::check($container, ['entity_manager' => '%env(WIKI_EM)%']);
        WikiDoctrineMapping::check(new ContainerBuilder(), ['mapping_type' => 'xml']);

        $this->addToAssertionCount(1);
    }

    /**
     * @param array<string, mixed> ...$configs
     */
    private function container(array ...$configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineExtension());
        $container->registerExtension(new WikiExtension());
        foreach ($configs as $config) {
            $container->loadFromExtension('nowo_wiki', $config);
        }

        return $container;
    }
}
