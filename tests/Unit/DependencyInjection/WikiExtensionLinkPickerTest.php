<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection;

use LogicException;
use Nowo\WikiBundle\Controller\WikiLinkPickerController;
use Nowo\WikiBundle\DependencyInjection\WikiExtension;
use Nowo\WikiBundle\LinkPicker\ConfiguredWikiLinkPickerSettings;
use Nowo\WikiBundle\LinkPicker\WikiLinkPickerEditorOptions;
use Nowo\WikiBundle\LinkPicker\WikiLinkPickerService;
use Nowo\WikiBundle\LinkPicker\WikiLinkPickerSettingsInterface;
use Nowo\WikiBundle\LinkPicker\WikiLinkTenantResolverInterface;
use Nowo\WikiBundle\LinkPicker\WikiTenantPathsLinkTenantResolver;
use Nowo\WikiBundle\Markdown\Milkdown\MilkdownWikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownSectionResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * 1.40 (W2): `editor.milkdown.link_picker` and `stable_heading_ids` — services and routes only with Milkdown, a required
 * rate limiter, and the section resolver of the renderer.
 */
final class WikiExtensionLinkPickerTest extends TestCase
{
    public function testOffByDefaultWithoutRoutesOrServices(): void
    {
        $container = $this->load([]);

        /** @var array<string, mixed> $routes */
        $routes = $container->getParameter('nowo_wiki.routes');
        self::assertArrayNotHasKey('link_candidates', $routes);
        self::assertArrayNotHasKey('link_sections', $routes);
        self::assertFalse($container->hasDefinition(WikiLinkPickerService::class));
        self::assertFalse($container->hasDefinition(WikiLinkPickerEditorOptions::class));
        self::assertNull($container->getDefinition(WikiLinkPickerController::class)->getArgument('$picker'));
    }

    public function testMilkdownGetsStableHeadingIdsAndSectionLabels(): void
    {
        $container = $this->load(['editor' => ['type' => 'milkdown']]);

        $options = $container->getDefinition(WikiLinkPickerEditorOptions::class);
        self::assertFalse($options->getArgument('$linkPicker'));
        self::assertTrue($options->getArgument('$stableHeadingIds'));
        self::assertFalse($container->hasDefinition(WikiLinkPickerService::class));
        self::assertSame(WikiMilkdownSectionResolver::class, (string) $container->getDefinition(MilkdownWikiMarkdownRenderer::class)->getArgument('$sections'));

        $plain = $this->load(['editor' => ['type' => 'milkdown', 'milkdown' => ['stable_heading_ids' => false]]]);
        self::assertFalse($plain->hasDefinition(WikiLinkPickerEditorOptions::class));
    }

    public function testThePickerWiresItsServicesAndRoutes(): void
    {
        $container = $this->load(['editor' => ['type' => 'milkdown', 'milkdown' => ['link_picker' => ['enabled' => true, 'cross_wiki' => false, 'rate_limiter' => 'limiter.wiki_link_picker']]], 'search' => ['scope' => 'space', 'excluded_spaces' => ['hr']]]);

        /** @var array<string, array{path: string, name: string}> $routes */
        $routes = $container->getParameter('nowo_wiki.routes');
        self::assertSame('nowo_wiki_link_candidates', $routes['link_candidates']['name']);
        self::assertSame('/tools/wiki/spaces/{spaceSlug}/link-candidates/{pageId}/sections', $routes['link_sections']['path']);
        self::assertTrue($container->getDefinition(WikiLinkPickerEditorOptions::class)->getArgument('$linkPicker'));
        self::assertFalse($container->getDefinition(ConfiguredWikiLinkPickerSettings::class)->getArgument('$crossWiki'));
        self::assertSame(ConfiguredWikiLinkPickerSettings::class, (string) $container->getAlias(WikiLinkPickerSettingsInterface::class));
        self::assertSame(WikiTenantPathsLinkTenantResolver::class, (string) $container->getAlias(WikiLinkTenantResolverInterface::class));
        $service = $container->getDefinition(WikiLinkPickerService::class);
        self::assertSame('space', $service->getArgument('$searchScope'));
        self::assertSame(['hr'], $service->getArgument('$excludedSpaces'));
        $controller = $container->getDefinition(WikiLinkPickerController::class);
        self::assertSame(WikiLinkPickerService::class, (string) $controller->getArgument('$picker'));
        self::assertSame('limiter.wiki_link_picker', (string) $controller->getArgument('$rateLimiter'));
    }

    public function testThePickerNeedsMilkdown(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('editor.type "milkdown"');
        $this->load(['editor' => ['milkdown' => ['link_picker' => ['enabled' => true, 'rate_limiter' => 'limiter.x']]]]);
    }

    public function testThePickerNeedsARateLimiter(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('needs a rate limiter');
        $this->load(['editor' => ['type' => 'milkdown', 'milkdown' => ['link_picker' => ['enabled' => true]]]]);
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
