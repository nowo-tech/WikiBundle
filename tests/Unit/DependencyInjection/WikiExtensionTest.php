<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use LogicException;
use Nowo\WikiBundle\Ai\NullWikiAiAssistant;
use Nowo\WikiBundle\Ai\SymfonyAiWikiAssistant;
use Nowo\WikiBundle\Ai\Tool\WikiKnowledgeSearchTool;
use Nowo\WikiBundle\Ai\WikiAiAssistantInterface;
use Nowo\WikiBundle\DependencyInjection\WikiExtension;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\DependencyInjection\TwigExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

final class WikiExtensionTest extends TestCase
{
    public function testRegistersHtmlSanitizerAlias(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User']], $container);

        self::assertTrue($container->hasAlias(WikiHtmlSanitizerInterface::class));
        self::assertTrue($container->hasDefinition(WikiHtmlSanitizer::class));
    }

    public function testPrependsFrameworkAssets(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FrameworkExtension());

        (new WikiExtension())->prepend($container);

        $configs = $container->getExtensionConfig('framework');
        self::assertNotEmpty($configs);
    }

    public function testPrependsDoctrineMappingsWhenExtensionPresent(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineExtension());

        (new WikiExtension())->prepend($container);

        $configs = $container->getExtensionConfig('doctrine');
        self::assertNotEmpty($configs);
    }

    public function testRegistersNullAiAssistantByDefault(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User']], $container);

        self::assertTrue($container->hasAlias(WikiAiAssistantInterface::class));
        self::assertSame(NullWikiAiAssistant::class, (string) $container->getAlias(WikiAiAssistantInterface::class));
    }

    public function testAiEnabledWithoutBundleThrows(): void
    {
        if (interface_exists(AgentInterface::class)) {
            self::markTestSkipped('symfony/ai-bundle is installed in this environment.');
        }

        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('symfony/ai-bundle');

        (new WikiExtension())->load([[
            'user_class' => 'App\\Entity\\User',
            'ai'         => ['enabled' => true],
        ]], $container);
    }

    public function testGetAlias(): void
    {
        self::assertSame('nowo_wiki', (new WikiExtension())->getAlias());
    }

    public function testRegistersSymfonyAiAssistantWhenEnabled(): void
    {
        if (!interface_exists(AgentInterface::class)) {
            self::markTestSkipped('symfony/ai-bundle is not installed in this environment.');
        }

        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([[
            'user_class' => 'App\\Entity\\User',
            'ai'         => ['enabled' => true, 'agent' => 'wiki_assistant'],
        ]], $container);

        self::assertTrue($container->hasDefinition(SymfonyAiWikiAssistant::class));
        self::assertTrue($container->hasDefinition(WikiKnowledgeSearchTool::class));
        self::assertSame(SymfonyAiWikiAssistant::class, (string) $container->getAlias(WikiAiAssistantInterface::class));
    }

    public function testLayoutTemplateOverridesTemplatesLayoutParameter(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([[
            'user_class' => 'App\\Entity\\User',
            'web_ui'     => [
                'layout_template' => 'layouts/app.html.twig',
            ],
        ]], $container);

        /** @var array{layout: string} $templates */
        $templates = $container->getParameter('nowo_wiki.templates');
        self::assertSame('layouts/app.html.twig', $templates['layout']);
    }

    public function testPrependsTwigGlobalsWhenExtensionPresent(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new TwigExtension());

        (new WikiExtension())->prepend($container);

        $configs = $container->getExtensionConfig('twig');
        self::assertNotEmpty($configs);
        self::assertSame('%nowo_wiki.web_ui%', $configs[0]['globals']['nowo_wiki_web_ui'] ?? null);
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

    public function testLoadAcceptsSecurityBundleViaHasExtension(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new SecurityExtension());

        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User']], $container);

        self::assertTrue($container->hasDefinition('nowo_wiki.access_checker.default'));
        self::assertFalse($container->getParameter('nowo_wiki.security.allow_unauthenticated'));
    }

    public function testPrependSeedsFormKitWikiProfileWhenHostUnset(): void
    {
        $container = new ContainerBuilder();
        $this->registerStubExtension($container, 'nowo_form_kit');
        $extension = new WikiExtension();
        $container->registerExtension($extension);

        $extension->prepend($container);

        $found = false;
        foreach ($container->getExtensionConfig('nowo_form_kit') as $cfg) {
            if (($cfg['css_framework'] ?? null) === 'bootstrap'
                && isset($cfg['profiles']['wiki']['alias'])
                && $cfg['profiles']['wiki']['alias'] === 'wiki'
            ) {
                $found = true;
                self::assertSame('NowoWikiBundle', $cfg['profiles']['wiki']['translation_domain']);
                break;
            }
        }
        self::assertTrue($found);
    }

    public function testPrependDoesNotOverrideExplicitFormKitHostConfig(): void
    {
        $container = new ContainerBuilder();
        $this->registerStubExtension($container, 'nowo_form_kit');
        $container->prependExtensionConfig('nowo_form_kit', [
            'css_framework' => 'none',
            'profiles'      => [
                'wiki' => [
                    'alias'              => 'wiki',
                    'translation_domain' => 'HostDomain',
                ],
            ],
        ]);
        $extension = new WikiExtension();
        $container->registerExtension($extension);

        $extension->prepend($container);

        $bootstrapSeed = false;
        $wikiReseed    = false;
        foreach ($container->getExtensionConfig('nowo_form_kit') as $cfg) {
            if (($cfg['css_framework'] ?? null) === 'bootstrap') {
                $bootstrapSeed = true;
            }
            if (($cfg['profiles']['wiki']['translation_domain'] ?? null) === 'NowoWikiBundle') {
                $wikiReseed = true;
            }
        }
        self::assertFalse($bootstrapSeed);
        self::assertFalse($wikiReseed);
    }

    public function testPrependSeedsUiKitFromWebUiWhenHostUnset(): void
    {
        $container = new ContainerBuilder();
        $this->registerStubExtension($container, 'nowo_ui_kit');
        $extension = new WikiExtension();
        $container->registerExtension($extension);
        $container->prependExtensionConfig('nowo_wiki', [
            'user_class' => 'App\\Entity\\User',
            'web_ui'     => [
                'css_framework' => 'tabler',
            ],
        ]);

        $extension->prepend($container);

        $found = false;
        foreach ($container->getExtensionConfig('nowo_ui_kit') as $cfg) {
            if (($cfg['css_framework'] ?? null) === 'tabler'
                && ($cfg['icon_set'] ?? null) === 'tabler-icons'
            ) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found);
    }

    public function testPrependSeedsUiKitBootstrapFromWebUi(): void
    {
        $container = new ContainerBuilder();
        $this->registerStubExtension($container, 'nowo_ui_kit');
        $extension = new WikiExtension();
        $container->registerExtension($extension);
        $container->prependExtensionConfig('nowo_wiki', [
            'user_class' => 'App\\Entity\\User',
            'web_ui'     => [
                'css_framework' => 'bootstrap',
            ],
        ]);

        $extension->prepend($container);

        $found = false;
        foreach ($container->getExtensionConfig('nowo_ui_kit') as $cfg) {
            if (($cfg['css_framework'] ?? null) === 'bootstrap5'
                && ($cfg['icon_set'] ?? null) === 'bootstrap-icons'
            ) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found);
    }

    public function testPrependDoesNotOverrideExplicitUiKitHostConfig(): void
    {
        $container = new ContainerBuilder();
        $this->registerStubExtension($container, 'nowo_ui_kit');
        $container->prependExtensionConfig('nowo_ui_kit', [
            'css_framework' => 'none',
            'icon_set'      => 'none',
        ]);
        $extension = new WikiExtension();
        $container->registerExtension($extension);
        $container->prependExtensionConfig('nowo_wiki', [
            'user_class' => 'App\\Entity\\User',
        ]);

        $extension->prepend($container);

        $seeded = false;
        foreach ($container->getExtensionConfig('nowo_ui_kit') as $cfg) {
            if (($cfg['css_framework'] ?? null) === 'tabler'
                || ($cfg['css_framework'] ?? null) === 'bootstrap5'
            ) {
                $seeded = true;
            }
        }
        self::assertFalse($seeded);
    }

    private function registerStubExtension(ContainerBuilder $container, string $alias): void
    {
        $container->registerExtension(new class($alias) implements ExtensionInterface {
            public function __construct(private readonly string $extensionAlias)
            {
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getNamespace(): string
            {
                return '';
            }

            public function getXsdValidationBasePath(): string|false
            {
                return false;
            }

            public function getAlias(): string
            {
                return $this->extensionAlias;
            }
        });
    }
}
