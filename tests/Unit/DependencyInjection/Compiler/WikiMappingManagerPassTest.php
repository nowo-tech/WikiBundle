<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection\Compiler;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\MappingException;
use Nowo\WikiBundle\DependencyInjection\Compiler\WikiMappingManagerPass;
use Nowo\WikiBundle\Doctrine\WikiExcludedEntityNamespaceDriver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

use function array_map;

/**
 * 1.32.0: the wiki entities stay off the managers other than `database.entity_manager` (DoctrineBundle 3.4).
 */
final class WikiMappingManagerPassTest extends TestCase
{
    public function testOtherManagersGetTheExcludingDriverFirstAndKeepAnExplicitAttributeMapping(): void
    {
        $container = $this->container('attribute');

        (new WikiMappingManagerPass())->process($container);

        self::assertSame(['addDriver:Nowo\\WikiBundle\\Entity'], $this->calls($container, 'default'), 'the configured manager is untouched');
        self::assertSame(['addDriver:excluded', 'addDriver:App\\Entity', 'setDefaultDriver:'], $this->calls($container, 'discovering'));
        self::assertSame(['addDriver:Nowo\\WikiBundle\\Entity'], $this->calls($container, 'mapped'), 'an explicit wiki driver stays with the attributes');
    }

    public function testWithTheXmlMappingOtherManagersLoseTheirWikiDriver(): void
    {
        $container = $this->container('xml');

        (new WikiMappingManagerPass())->process($container);

        self::assertSame(['addDriver:excluded'], $this->calls($container, 'mapped'));
        self::assertSame(['addDriver:excluded', 'addDriver:App\\Entity', 'setDefaultDriver:'], $this->calls($container, 'discovering'));
    }

    public function testNothingHappensWithoutDoctrineOrAnUnknownManager(): void
    {
        $container = $this->container('attribute');
        $container->setParameter('nowo_wiki.database', ['entity_manager' => 'missing']);
        (new WikiMappingManagerPass())->process($container);
        self::assertSame(['addDriver:App\\Entity', 'setDefaultDriver:'], $this->calls($container, 'discovering'));

        $container->setParameter('nowo_wiki.database', ['entity_manager' => 7]);
        (new WikiMappingManagerPass())->process($container);
        self::assertSame(['addDriver:App\\Entity', 'setDefaultDriver:'], $this->calls($container, 'discovering'));

        $empty = new ContainerBuilder();
        (new WikiMappingManagerPass())->process($empty);
        $empty->setParameter('nowo_wiki.database', []);
        (new WikiMappingManagerPass())->process($empty);
        self::assertFalse($empty->hasDefinition('doctrine.orm.default_metadata_driver'));
    }

    public function testTheExcludingDriverMapsNothing(): void
    {
        $driver = new WikiExcludedEntityNamespaceDriver();

        self::assertSame([], $driver->getAllClassNames());
        self::assertTrue($driver->isTransient('Nowo\\WikiBundle\\Entity\\WikiPage'));
        $this->expectException(MappingException::class);
        $driver->loadMetadataForClass('Nowo\\WikiBundle\\Entity\\WikiPage', $this->createMock(ClassMetadata::class));
    }

    private function container(string $type): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_wiki.database', ['entity_manager' => 'default', 'mapping_type' => $type]);
        $container->setParameter('doctrine.entity_managers', [
            'default'     => 'doctrine.orm.default_entity_manager',
            'discovering' => 'doctrine.orm.discovering_entity_manager',
            'mapped'      => 'doctrine.orm.mapped_entity_manager',
            'undeclared'  => 'doctrine.orm.undeclared_entity_manager',
        ]);
        $container->setDefinition('doctrine.orm.default_metadata_driver', (new Definition())->addMethodCall('addDriver', [new Definition('AttributeDriver'), 'Nowo\\WikiBundle\\Entity']));
        $container->setDefinition('doctrine.orm.discovering_metadata_driver', (new Definition())
            ->addMethodCall('addDriver', [new Definition('AttributeDriver'), 'App\\Entity'])
            ->addMethodCall('setDefaultDriver', [new Definition('AttributeDriver')]));
        $container->setDefinition('doctrine.orm.mapped_metadata_driver', (new Definition())->addMethodCall('addDriver', [new Definition('AttributeDriver'), 'Nowo\\WikiBundle\\Entity']));

        return $container;
    }

    /**
     * @return list<string>
     */
    private function calls(ContainerBuilder $container, string $manager): array
    {
        return array_map(static function (array $call): string {
            $driver = $call[1][0] ?? null;
            if ($driver instanceof Definition && $driver->getClass() === WikiExcludedEntityNamespaceDriver::class) {
                return $call[0] . ':excluded';
            }

            return $call[0] . ':' . ($call[0] === 'addDriver' ? $call[1][1] : '');
        }, $container->getDefinition('doctrine.orm.' . $manager . '_metadata_driver')->getMethodCalls());
    }
}
