<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Nowo\WikiBundle\DependencyInjection\WikiExtension;
use Nowo\WikiBundle\Doctrine\Type\WikiUserRefType;
use Nowo\WikiBundle\Doctrine\WikiMetadataListener;
use Nowo\WikiBundle\Doctrine\WikiSpaceCollationListener;
use Nowo\WikiBundle\History\WikiAuthorLabel;
use Nowo\WikiBundle\Service\WikiRequestBoundaryResetter;
use Nowo\WikiBundle\User\WikiUserDirectoryInterface;
use Nowo\WikiBundle\User\WikiUsers;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use function array_map;

/**
 * 1.34.0 (`database.user_reference: id`): the column type, the directory and the per-unit-of-work memo.
 */
final class WikiExtensionUserReferenceTest extends TestCase
{
    public function testTheColumnTypeIsRegisteredOnlyInTheIdMode(): void
    {
        self::assertSame([], $this->dbalTypes([['user_class' => 'App\\User']]));
        self::assertSame([], $this->dbalTypes([['user_class' => 'App\\User', 'database' => ['user_reference' => 'id']], ['database' => ['user_reference' => 'entity']]]), 'the last value wins');
        self::assertSame([WikiUserRefType::NAME => WikiUserRefType::class], $this->dbalTypes([['user_class' => 'App\\User', 'database' => ['user_reference' => 'id']]]));
    }

    public function testTheModeAndTheDirectoryAreWired(): void
    {
        $container = $this->load(['user_class' => 'App\\User', 'database' => ['user_reference' => 'id'], 'user_directory' => 'app.users']);

        self::assertSame('id', $container->getParameter('nowo_wiki.database.user_reference'));
        self::assertSame('app.users', (string) $container->getAlias(WikiUserDirectoryInterface::class));
        self::assertSame('id', $container->getDefinition(WikiMetadataListener::class)->getArgument('$userReference'));
        self::assertEquals(new Reference(WikiUsers::class), $container->getDefinition(WikiAuthorLabel::class)->getArgument('$users'));
        self::assertTrue($container->getDefinition(WikiSpaceCollationListener::class)->hasTag('doctrine.event_listener'));
        $memos = $container->getDefinition(WikiRequestBoundaryResetter::class)->getArgument('$memos');
        self::assertInstanceOf(IteratorArgument::class, $memos);
        self::assertContains(WikiUsers::class, array_map('strval', $memos->getValues()), 'emptied at every boundary');

        $default = $this->load(['user_class' => 'App\\User']);
        self::assertSame('entity', $default->getDefinition(WikiMetadataListener::class)->getArgument('$userReference'));
        self::assertFalse($default->hasAlias(WikiUserDirectoryInterface::class));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([$config], $container);

        return $container;
    }

    /**
     * @param list<array<string, mixed>> $configs
     *
     * @return array<string, string>
     */
    private function dbalTypes(array $configs): array
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineExtension());
        $container->registerExtension(new WikiExtension());
        foreach ($configs as $config) {
            $container->loadFromExtension('nowo_wiki', $config);
        }
        (new WikiExtension())->prepend($container);
        $types = [];
        foreach ($container->getExtensionConfig('doctrine') as $config) {
            foreach ($config['dbal']['types'] ?? [] as $name => $class) {
                $types[$name] = $class;
            }
        }

        return $types;
    }
}
