<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection;

use Nowo\WikiBundle\Attachment\LocalWikiAttachmentStorage;
use Nowo\WikiBundle\Attachment\NullWikiAttachmentScanner;
use Nowo\WikiBundle\Attachment\WikiAttachmentScannerInterface;
use Nowo\WikiBundle\Attachment\WikiAttachmentStorageInterface;
use Nowo\WikiBundle\Avatar\WikiAvatars;
use Nowo\WikiBundle\DependencyInjection\WikiExtension;
use Nowo\WikiBundle\Link\WikiObjectLinkResolverInterface;
use Nowo\WikiBundle\Markdown\Milkdown\WikiFilePreviewFrames;
use Nowo\WikiBundle\Markdown\WikiRenderContext;
use Nowo\WikiBundle\Member\WikiMemberBadges;
use Nowo\WikiBundle\Routing\WikiTenantPaths;
use Nowo\WikiBundle\Routing\WikiTenantUrlGenerator;
use Nowo\WikiBundle\Security\LegacyWikiSpaceAccessChecker;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Service\AccessibleWikiSpaceLocator;
use Nowo\WikiBundle\Service\WikiEventDeferral;
use Nowo\WikiBundle\Service\WikiRequestBoundaryResetter;
use Nowo\WikiBundle\Service\WikiSpaceLocatorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

use function array_map;

final class WikiExtensionSpaceAccessTest extends TestCase
{
    public function testDefaultsKeepThe1xBehaviourAndRegisterNoRouterDecorator(): void
    {
        $container = $this->load([]);

        self::assertSame(LegacyWikiSpaceAccessChecker::class, (string) $container->getAlias(WikiSpaceAccessCheckerInterface::class));
        self::assertSame([['method' => 'reset']], $container->getDefinition(LegacyWikiSpaceAccessChecker::class)->getTag('kernel.reset'), 'CV-L1: the membership memo is cleared between requests');
        self::assertSame(AccessibleWikiSpaceLocator::class, (string) $container->getAlias(WikiSpaceLocatorInterface::class));
        self::assertSame(LocalWikiAttachmentStorage::class, (string) $container->getAlias(WikiAttachmentStorageInterface::class));
        self::assertSame(NullWikiAttachmentScanner::class, (string) $container->getAlias(WikiAttachmentScannerInterface::class));
        self::assertNull($container->getDefinition(WikiTenantUrlGenerator::class)->getDecoratedService());
        self::assertFalse($container->getParameter('nowo_wiki.attachments')['enabled']);
        self::assertArrayHasKey(WikiObjectLinkResolverInterface::class, $container->getAutoconfiguredInstanceof());
    }

    public function testTheRequestMemosAreEmptiedAtEveryBoundary(): void
    {
        // 1.29.1 (REQ-RUNTIME-002): not only by kernel.reset.
        $definition = $this->load([])->getDefinition(WikiRequestBoundaryResetter::class);

        self::assertTrue($definition->hasTag('kernel.event_subscriber'));
        $memos = $definition->getArgument('$memos');
        self::assertInstanceOf(IteratorArgument::class, $memos);
        $ids = array_map(static fn (mixed $reference): string => $reference instanceof Reference ? (string) $reference : '', $memos->getValues());
        self::assertSame([LegacyWikiSpaceAccessChecker::class, WikiAvatars::class, WikiFilePreviewFrames::class, WikiRenderContext::class, WikiEventDeferral::class, WikiMemberBadges::class], $ids);
        foreach ($memos->getValues() as $reference) {
            self::assertInstanceOf(Reference::class, $reference);
            self::assertSame(ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE, $reference->getInvalidBehavior(), 'only built memos are reset; missing ones are skipped');
        }
    }

    public function testTenantPathsDecorateTheRouterAndCustomStorageIsAliased(): void
    {
        $container = $this->load([
            'tenant_paths' => [['path' => '/projects/{spaceSlug}/wiki', 'name_prefix' => 'app_project_']],
            'tenant_only'  => true,
            'attachments'  => ['enabled' => true, 'storage' => 'app.storage', 'scanner' => 'app.scanner'],
        ]);

        self::assertSame(['router', null, 0], $container->getDefinition(WikiTenantUrlGenerator::class)->getDecoratedService());
        self::assertTrue($container->getDefinition(WikiTenantPaths::class)->getArgument('$tenantOnly'));
        self::assertSame('app.storage', (string) $container->getAlias(WikiAttachmentStorageInterface::class));
        self::assertSame('app.scanner', (string) $container->getAlias(WikiAttachmentScannerInterface::class));
    }

    public function testATenantPathWithoutTheSpaceParameterIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load(['tenant_paths' => [['path' => '/projects/wiki', 'name_prefix' => 'x_']]]);
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
