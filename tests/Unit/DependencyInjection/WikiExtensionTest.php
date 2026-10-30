<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use LogicException;
use Nowo\WikiBundle\Ai\NullWikiAiAssistant;
use Nowo\WikiBundle\Ai\SymfonyAiWikiAssistant;
use Nowo\WikiBundle\Ai\Tool\WikiKnowledgeSearchTool;
use Nowo\WikiBundle\Ai\WikiAiAssistantInterface;
use Nowo\WikiBundle\DependencyInjection\Configuration;
use Nowo\WikiBundle\DependencyInjection\WikiExtension;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Security\NullWikiTeamMembershipResolver;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;
use Nowo\WikiBundle\Security\WikiTeamMembershipResolverInterface;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Ui\WikiPageFactory;
use Nowo\WikiBundle\Ui\WikiPages;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\DependencyInjection\TwigExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

use function array_diff_key;
use function array_fill_keys;
use function array_keys;
use function is_array;

use const E_USER_DEPRECATED;

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

    /**
     * services.yaml scans Security/; the explicit definitions registered by load() must win
     * over the autowired/autoconfigured ones from the resource scan regardless of order.
     */
    public function testExplicitSecurityDefinitionsWinOverResourceScan(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User']], $container);

        $resolver = $container->getDefinition(NullWikiTeamMembershipResolver::class);
        self::assertSame(NullWikiTeamMembershipResolver::class, $resolver->getClass());
        self::assertSame([], $resolver->getArguments());
        self::assertFalse($resolver->isAutowired());
        self::assertFalse($resolver->isAutoconfigured());
        self::assertSame(
            NullWikiTeamMembershipResolver::class,
            (string) $container->getAlias(WikiTeamMembershipResolverInterface::class),
        );

        $sanitizer = $container->getDefinition(WikiHtmlSanitizer::class);
        self::assertSame(WikiHtmlSanitizer::class, $sanitizer->getClass());
        self::assertSame([], $sanitizer->getArguments());
        self::assertFalse($sanitizer->isAutowired());
        self::assertFalse($sanitizer->isAutoconfigured());
        self::assertSame(
            WikiHtmlSanitizer::class,
            (string) $container->getAlias(WikiHtmlSanitizerInterface::class),
        );

        $checker = $container->getDefinition('nowo_wiki.access_checker.default');
        self::assertSame(['ROLE_ADMIN'], $checker->getArgument('$adminRoles'));
        self::assertSame(
            'nowo_wiki.access_checker.default',
            (string) $container->getAlias(WikiAccessCheckerInterface::class),
        );
    }

    public function testCustomTeamMembershipResolverAliasIsKept(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([[
            'user_class'               => 'App\\Entity\\User',
            'team_membership_resolver' => 'app.wiki_team_resolver',
        ]], $container);

        self::assertSame(
            'app.wiki_team_resolver',
            (string) $container->getAlias(WikiTeamMembershipResolverInterface::class),
        );
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

        $httpClient = $container->getDefinition('nowo_wiki.ai.http_client');
        self::assertSame([['timeout' => 30.0, 'max_duration' => 30.0]], $httpClient->getArguments());
    }

    public function testAiHttpClientUsesConfiguredTimeout(): void
    {
        if (!interface_exists(AgentInterface::class)) {
            self::markTestSkipped('symfony/ai-bundle is not installed in this environment.');
        }

        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([[
            'user_class' => 'App\\Entity\\User',
            'ai'         => ['enabled' => true, 'http_timeout' => 12],
        ]], $container);

        $factory = $container->getDefinition('nowo_wiki.ai.http_client')->getFactory();
        self::assertIsArray($factory);
        self::assertSame('http_client', (string) $factory[0]);
        self::assertSame('withOptions', $factory[1]);
        self::assertSame([['timeout' => 12.0, 'max_duration' => 12.0]], $container->getDefinition('nowo_wiki.ai.http_client')->getArguments());
    }

    /**
     * REQ-RUNTIME-001: a provider that stops sending data must fail with a transport timeout instead of
     * pinning the PHP (FrankenPHP worker) thread.
     */
    public function testAiHttpClientTimesOutOnStalledProvider(): void
    {
        if (!interface_exists(AgentInterface::class)) {
            self::markTestSkipped('symfony/ai-bundle is not installed in this environment.');
        }

        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([[
            'user_class' => 'App\\Entity\\User',
            'ai'         => ['enabled' => true, 'http_timeout' => 1],
        ]], $container);

        // Same factory call as the service definition, on a mock transport whose body stalls ('' chunk = idle).
        $options = $container->getDefinition('nowo_wiki.ai.http_client')->getArgument(0);
        self::assertIsArray($options);
        $client = (new MockHttpClient(static fn (): MockResponse => new MockResponse((static function (): iterable {
            yield '';
        })())))->withOptions($options);

        $started = microtime(true);
        try {
            $client->request('POST', 'https://llm.example.test/v1/chat')->getContent();
            self::fail('Expected a transport timeout.');
        } catch (TransportExceptionInterface $exception) {
            self::assertStringContainsString('timeout', strtolower($exception->getMessage()));
        }
        self::assertLessThan(5.0, microtime(true) - $started);
    }

    public function testAiAssistantLogsOnTheWikiChannel(): void
    {
        if (!interface_exists(AgentInterface::class)) {
            self::markTestSkipped('symfony/ai-bundle is not installed in this environment.');
        }

        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User', 'ai' => ['enabled' => true]]], $container);

        $definition = $container->getDefinition(SymfonyAiWikiAssistant::class);
        self::assertEquals(new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE), $definition->getArgument('$logger'));
        self::assertSame([['channel' => 'nowo_wiki']], $definition->getTag('monolog.logger'));
        self::assertSame(['spaces_per_page' => 24, 'revisions_per_page' => 25, 'max_tree_pages' => 500], $container->getParameter('nowo_wiki.pagination'));
    }

    public function testRepositoriesAndSearchResolveEntityManagerPerCall(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User']], $container);

        $provider = $container->getDefinition(WikiEntityManagerProvider::class);
        self::assertEquals(new Reference('doctrine'), $provider->getArgument('$registry'));
        self::assertSame('default', $provider->getArgument('$managerName'));
        self::assertEquals(new Reference(WikiEntityManagerProvider::class), $container->getDefinition(DoctrineOrmWikiPageRepository::class)->getArgument('$entityManager'));
        self::assertEquals(new Reference(WikiEntityManagerProvider::class), $container->getDefinition(WikiSearchService::class)->getArgument('$entityManager'));
        self::assertTrue($container->hasDefinition(WikiTokenGuard::class));
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

    public function testCanonicalWebUiKeysWinOverTheDeprecatedOnes(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        set_error_handler(static fn (): bool => true, E_USER_DEPRECATED);

        try {
            (new WikiExtension())->load([[
                'user_class'   => 'App\\Entity\\User',
                'route_prefix' => '/legacy',
                'templates'    => ['layout' => 'old.html.twig', 'index' => 'old/index.html.twig', 'search' => 'old/search.html.twig'],
                'web_ui'       => ['path_prefix' => '/admin', 'layout_template' => 'layouts/admin.html.twig', 'templates' => ['index' => 'new/index.html.twig']],
            ]], $container);
        } finally {
            restore_error_handler();
        }

        self::assertSame('/admin', $container->getParameter('nowo_wiki.route_prefix'));
        /** @var array<string, string|null> $templates */
        $templates = $container->getParameter('nowo_wiki.templates');
        self::assertSame('layouts/admin.html.twig', $templates['layout']);
        self::assertSame('new/index.html.twig', $templates['index']);
        self::assertSame('old/search.html.twig', $templates['search']);
        self::assertSame(WikiPages::TEMPLATES['space'], $templates['space']);
        self::assertSame('layouts/admin.html.twig', $container->getDefinition(WikiPages::class)->getArgument('$layoutTemplate'));
        self::assertArrayNotHasKey('layout', $container->getDefinition(WikiPages::class)->getArgument('$templates'));
        self::assertEquals(new Reference(WikiPages::class), $container->getDefinition(WikiPageFactory::class)->getArgument('$pages'));
    }

    public function testDeprecatedKeysStillApplyAndTheOldDefaultLayoutMeansUiKit(): void
    {
        self::assertSame('/legacy', WikiExtension::pathPrefix(['route_prefix' => '/legacy', 'web_ui' => ['path_prefix' => null]]));
        self::assertSame('', WikiExtension::pathPrefix([]));
        self::assertNull(WikiExtension::templates(['templates' => ['layout' => WikiPages::LEGACY_DEFAULT_LAYOUT]])['layout']);
        self::assertSame('base.html.twig', WikiExtension::templates(['templates' => ['layout' => 'base.html.twig']])['layout']);
        self::assertSame(WikiPages::TEMPLATES, array_diff_key(WikiExtension::templates([]), ['layout' => true]));
    }

    public function testRolesReachTheDefaultAccessChecker(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([[
            'user_class' => 'App\\Entity\\User',
            'security'   => ['roles' => ['edit' => ['ROLE_EDITOR'], 'admin' => []]],
        ]], $container);

        $checker = $container->getDefinition('nowo_wiki.access_checker.default');
        self::assertSame(['ROLE_EDITOR'], $checker->getArgument('$editRoles'));
        self::assertSame([], $checker->getArgument('$adminRoles'));
        self::assertSame(['ROLE_USER'], $checker->getArgument('$listRoles'));
        self::assertSame(['ROLE_USER'], $checker->getArgument('$accessRoles'));
    }

    public function testEveryEmptyRoleListFailsTheCompile(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        $roles = array_fill_keys(array_keys(Configuration::DEFAULT_ROLES), []);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('nobody can use the wiki');

        (new WikiExtension())->load([[
            'user_class' => 'App\\Entity\\User',
            'security'   => ['access_roles' => [], 'roles' => $roles],
        ]], $container);
    }

    public function testEmptyRoleListsAreFineWithAnAccessCheckerOrADisabledUi(): void
    {
        $roles = array_fill_keys(array_keys(Configuration::DEFAULT_ROLES), []);
        foreach ([['access_checker' => 'app.wiki_checker'], []] as $index => $security) {
            $container = new ContainerBuilder();
            $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
            (new WikiExtension())->load([[
                'user_class' => 'App\\Entity\\User',
                'security'   => $security + ['access_roles' => [], 'roles' => $roles],
                'web_ui'     => ['enabled' => $index === 0],
            ]], $container);
            self::assertSame($index === 0, $container->getParameter('nowo_wiki.web_ui.enabled'));
        }
    }

    public function testPrependDoesNotSeedUiKitWithoutTheDeprecatedFramework(): void
    {
        $container = new ContainerBuilder();
        $this->registerStubExtension($container, 'nowo_ui_kit');
        $extension = new WikiExtension();
        $container->registerExtension($extension);
        $container->prependExtensionConfig('nowo_wiki', ['user_class' => 'App\\Entity\\User']);

        $extension->prepend($container);

        self::assertSame([], $container->getExtensionConfig('nowo_ui_kit'));
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

    public function testPrependSkipsNonArrayUiKitConfigEntries(): void
    {
        // A container whose nowo_ui_kit config stack holds a malformed (non-array) entry next to a partial host config.
        $container = new class extends ContainerBuilder {
            public function getExtensionConfig(string $name): array
            {
                $configs = parent::getExtensionConfig($name);

                return $name === 'nowo_ui_kit' ? ['broken', ...$configs] : $configs;
            }
        };
        $this->registerStubExtension($container, 'nowo_ui_kit');
        $container->prependExtensionConfig('nowo_ui_kit', ['icon_set' => 'none']);
        $extension = new WikiExtension();
        $container->registerExtension($extension);
        $container->prependExtensionConfig('nowo_wiki', [
            'user_class' => 'App\\Entity\\User',
            'web_ui'     => ['css_framework' => 'tabler'],
        ]);

        $extension->prepend($container);

        $seeded = null;
        foreach ($container->getExtensionConfig('nowo_ui_kit') as $cfg) {
            if (is_array($cfg) && isset($cfg['css_framework'])) {
                $seeded = $cfg;
            }
        }
        self::assertSame(['css_framework' => 'tabler'], $seeded);
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
