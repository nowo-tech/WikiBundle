<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\DependencyInjection;

use LogicException;
use Nowo\WikiBundle\Ai\NullWikiAiAssistant;
use Nowo\WikiBundle\Ai\SymfonyAiWikiAssistant;
use Nowo\WikiBundle\Ai\Tool\WikiKnowledgeSearchTool;
use Nowo\WikiBundle\Ai\WikiAiAssistantInterface;
use Nowo\WikiBundle\Ai\WikiContextRetriever;
use Nowo\WikiBundle\Attachment\ConfiguredWikiAttachmentLimitsProvider;
use Nowo\WikiBundle\Attachment\LocalWikiAttachmentStorage;
use Nowo\WikiBundle\Attachment\NullWikiAttachmentScanner;
use Nowo\WikiBundle\Attachment\WikiAttachmentLimitsProviderInterface;
use Nowo\WikiBundle\Attachment\WikiAttachmentScannerInterface;
use Nowo\WikiBundle\Attachment\WikiAttachmentStorageInterface;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Doctrine\WikiMetadataListener;
use Nowo\WikiBundle\Link\WikiObjectLinkResolverInterface;
use Nowo\WikiBundle\Markdown\WikiMarkdownExtensionInterface;
use Nowo\WikiBundle\Markdown\WikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiAttachmentRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageLinkRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiSpaceRepository;
use Nowo\WikiBundle\Repository\WikiAttachmentRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageLinkRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Routing\WikiTenantPaths;
use Nowo\WikiBundle\Routing\WikiTenantUrlGenerator;
use Nowo\WikiBundle\Security\AllowAllWikiAccessChecker;
use Nowo\WikiBundle\Security\ConfigurableWikiAccessChecker;
use Nowo\WikiBundle\Security\LegacyWikiSpaceAccessChecker;
use Nowo\WikiBundle\Security\NullWikiTeamMembershipResolver;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiTeamMembershipResolverInterface;
use Nowo\WikiBundle\Service\AccessibleWikiSpaceLocator;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolver;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Service\WikiSpaceLocatorInterface;
use Nowo\WikiBundle\Ui\WikiPageFactory;
use Nowo\WikiBundle\Ui\WikiPages;
use Symfony\AI\Agent\AgentInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_diff_key;
use function array_filter;
use function array_key_exists;
use function array_values;
use function is_array;
use function is_bool;
use function is_string;
use function rtrim;
use function sprintf;
use function str_starts_with;

/**
 * Loads bundle configuration and registers services.
 */
final class WikiExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Processes the {@code nowo_wiki} configuration, sets the bundle parameters and registers the services and aliases.
     *
     * Registers the null AI assistant when AI is disabled, or the Symfony AI assistant otherwise.
     *
     * @param array<int, array<string, mixed>> $configs raw configuration arrays
     * @param ContainerBuilder $container container being built
     *
     * @throws LogicException when the manage UI needs symfony/security-bundle, or AI is enabled without symfony/ai-bundle
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);
        $roles         = Configuration::resolveRoles($config['security']);

        // BB L1: the switches that open the wiki must be literal booleans (an env placeholder string would be truthy).
        $config['web_ui']['enabled']                 = $this->literalBool($config['web_ui']['enabled'], 'web_ui.enabled');
        $config['security']['allow_unauthenticated'] = $this->literalBool($config['security']['allow_unauthenticated'], 'security.allow_unauthenticated');
        if ($config['security']['allow_unauthenticated']) {
            $this->refuseAllowUnauthenticatedInProduction($container);
        }

        // P1: with every role list empty and no access checker, the wiki would deny everyone silently.
        if (
            ($config['web_ui']['enabled'] ?? true)
            && !$config['security']['allow_unauthenticated']
            && !is_string($config['security']['access_checker'] ?? null)
            && $config['security']['access_roles'] === []
            && array_filter($roles) === []
        ) {
            throw new LogicException('nowo_wiki.security: access_roles and every security.roles.<action> list are empty, so nobody can use the wiki. Configure at least one role, an access_checker or (trusted demos only) allow_unauthenticated.');
        }

        if (
            ($config['web_ui']['enabled'] ?? true)
            && !$config['security']['allow_unauthenticated']
            && !$this->isSecurityBundleAvailable($container)
        ) {
            throw new LogicException('WikiBundle manage UI requires symfony/security-bundle when security.allow_unauthenticated is false.');
        }

        $prefix         = rtrim((string) $config['table_prefix'], '_');
        $spacesTable    = $prefix . '_spaces';
        $pagesTable     = $prefix . '_pages';
        $revisionsTable = $prefix . '_page_revisions';
        $database       = $config['database'];
        $emName         = (string) $database['entity_manager'];

        $container->setParameter('nowo_wiki.user_class', $config['user_class']);
        $container->setParameter('nowo_wiki.spaces_table', $spacesTable);
        $container->setParameter('nowo_wiki.pages_table', $pagesTable);
        $container->setParameter('nowo_wiki.revisions_table', $revisionsTable);
        $container->setParameter('nowo_wiki.database', $database);
        $container->setParameter('nowo_wiki.space_scope', $config['space_scope']);
        $container->setParameter('nowo_wiki.route_prefix', self::pathPrefix($config));
        $container->setParameter('nowo_wiki.dashboard_route', $config['dashboard_route']);
        $container->setParameter('nowo_wiki.routes', $config['routes']);
        $container->setParameter('nowo_wiki.templates', self::templates($config));
        $container->setParameter('nowo_wiki.firewall', $config['firewall']);
        $container->setParameter('nowo_wiki.security.allow_unauthenticated', $config['security']['allow_unauthenticated']);
        $container->setParameter('nowo_wiki.editor', $config['editor']);
        $container->setParameter('nowo_wiki.editor.type', $config['editor']['type']);
        $container->setParameter('nowo_wiki.ai', $config['ai']);
        $container->setParameter('nowo_wiki.import_export', $config['import_export']);
        $container->setParameter('nowo_wiki.pagination', $config['pagination']);
        $container->setParameter('nowo_wiki.attachments', $config['attachments']);
        $container->setParameter('nowo_wiki.tenant_paths', $config['tenant_paths']);
        $container->setParameter('nowo_wiki.import_export.enabled', (bool) $config['import_export']['enabled']);
        $templates = self::templates($config);
        $container->setParameter('nowo_wiki.web_ui', [
            'enabled'         => (bool) $config['web_ui']['enabled'],
            'css_framework'   => $config['web_ui']['css_framework'],
            'layout_template' => $templates['layout'],
            'path_prefix'     => self::pathPrefix($config),
            'templates'       => array_diff_key($templates, ['layout' => true]),
        ]);
        $container->setParameter('nowo_wiki.web_ui.enabled', (bool) $config['web_ui']['enabled']);
        $container->setParameter('nowo_wiki.web_ui.css_framework', $config['web_ui']['css_framework']);
        $container->setParameter('nowo_wiki.security.roles', $roles);

        // Load the resource scan first: every explicit definition below must win over the
        // autowired one that services.yaml registers for the same class (e.g. Security/).
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $teamResolverId = $config['team_membership_resolver'] ?? null;
        if (!is_string($teamResolverId) || $teamResolverId === '') {
            $teamResolverId = NullWikiTeamMembershipResolver::class;
            $container->setDefinition(NullWikiTeamMembershipResolver::class, new Definition(NullWikiTeamMembershipResolver::class));
        }
        $container->setAlias(WikiTeamMembershipResolverInterface::class, $teamResolverId);

        $accessCheckerId = $config['security']['access_checker'] ?? null;
        if ($config['security']['allow_unauthenticated']) {
            $accessCheckerId = 'nowo_wiki.access_checker.allow_all';
            $container->setDefinition($accessCheckerId, new Definition(AllowAllWikiAccessChecker::class));
        } elseif (!is_string($accessCheckerId) || $accessCheckerId === '') {
            $accessCheckerId = 'nowo_wiki.access_checker.default';
            $security        = $config['security'];
            $container->setDefinition($accessCheckerId, (new Definition(ConfigurableWikiAccessChecker::class))
                ->setAutowired(true)
                ->setArgument('$adminRoles', $roles['admin'])
                ->setArgument('$accessRoles', array_values($security['access_roles']))
                ->setArgument('$listRoles', $roles['list'])
                ->setArgument('$createRoles', $roles['create'])
                ->setArgument('$editRoles', $roles['edit'])
                ->setArgument('$historyRoles', $roles['history'])
                ->setArgument('$archiveRoles', $roles['archive'])
                ->setArgument('$aiRoles', $roles['ai'])
                ->setArgument('$importRoles', $roles['import'])
                ->setArgument('$exportRoles', $roles['export']));
        }
        $container->setAlias(WikiAccessCheckerInterface::class, $accessCheckerId);
        $container->setAlias(WikiHtmlSanitizerInterface::class, WikiHtmlSanitizer::class);
        // 1.11: Markdown revisions — the default renderer takes every nowo_wiki.markdown_extension (none by default).
        $container->registerForAutoconfiguration(WikiMarkdownExtensionInterface::class)->addTag('nowo_wiki.markdown_extension');
        $container->setAlias(WikiMarkdownRendererInterface::class, WikiMarkdownRenderer::class);
        $container->setDefinition(WikiHtmlSanitizer::class, new Definition(WikiHtmlSanitizer::class));

        $container->setDefinition(WikiEntityManagerProvider::class, (new Definition(WikiEntityManagerProvider::class))
            ->setArgument('$registry', new Reference('doctrine'))
            ->setArgument('$managerName', $emName));
        $emProviderRef = new Reference(WikiEntityManagerProvider::class);

        foreach ([
            DoctrineOrmWikiSpaceRepository::class        => WikiSpaceRepositoryInterface::class,
            DoctrineOrmWikiPageRepository::class         => WikiPageRepositoryInterface::class,
            DoctrineOrmWikiPageRevisionRepository::class => WikiPageRevisionRepositoryInterface::class,
            DoctrineOrmWikiPageLinkRepository::class     => WikiPageLinkRepositoryInterface::class,
            DoctrineOrmWikiAttachmentRepository::class   => WikiAttachmentRepositoryInterface::class,
        ] as $repoClass => $interface) {
            $container->setDefinition($repoClass, (new Definition($repoClass))
                ->setAutowired(false)
                ->setArgument('$entityManager', $emProviderRef));
            $container->setAlias($interface, $repoClass);
        }

        $container->setDefinition(WikiMetadataListener::class, (new Definition(WikiMetadataListener::class))
            ->setArgument('$spacesTableName', $spacesTable)
            ->setArgument('$pagesTableName', $pagesTable)
            ->setArgument('$revisionsTableName', $revisionsTable)
            ->setArgument('$userClass', $config['user_class'])
            ->setArgument('$linksTableName', $prefix . '_page_links')
            ->setArgument('$attachmentsTableName', $prefix . '_attachments')
            ->addTag('doctrine.event_listener', ['event' => 'loadClassMetadata']));

        $container->setDefinition(WikiSpaceAccessResolver::class, (new Definition(WikiSpaceAccessResolver::class))
            ->setAutowired(true)
            ->setArgument('$defaultOwnerScope', $config['space_scope']));
        $container->setAlias(WikiSpaceAccessResolverInterface::class, WikiSpaceAccessResolver::class);

        $this->registerSpaceAccess($container, $config);
        $this->registerPages($container, $config);
        $this->registerAttachments($container, $config['attachments']);

        $container->getDefinition(WikiSearchService::class)->setArgument('$entityManager', $emProviderRef);

        $this->registerAiServices($container, $config['ai']);
    }

    /**
     * 1.9: space-aware access (host overrides the aliases), link resolvers and tenant routes.
     *
     * @param array<string, mixed> $config processed configuration
     */
    private function registerSpaceAccess(ContainerBuilder $container, array $config): void
    {
        $container->setDefinition(LegacyWikiSpaceAccessChecker::class, (new Definition(LegacyWikiSpaceAccessChecker::class))->setAutowired(true));
        $container->setAlias(WikiSpaceAccessCheckerInterface::class, LegacyWikiSpaceAccessChecker::class);
        $container->setDefinition(AccessibleWikiSpaceLocator::class, (new Definition(AccessibleWikiSpaceLocator::class))->setAutowired(true));
        $container->setAlias(WikiSpaceLocatorInterface::class, AccessibleWikiSpaceLocator::class);
        $container->registerForAutoconfiguration(WikiObjectLinkResolverInterface::class)->addTag('nowo_wiki.object_link_resolver');

        $container->setDefinition(WikiTenantPaths::class, (new Definition(WikiTenantPaths::class))
            ->setArgument('$tenants', array_values($config['tenant_paths']))
            ->setArgument('$routes', $config['routes'])
            ->setArgument('$tenantOnly', (bool) $config['tenant_only']));

        if ($config['tenant_paths'] !== []) {
            $container->setDefinition(WikiTenantUrlGenerator::class, (new Definition(WikiTenantUrlGenerator::class))
                ->setDecoratedService('router')
                ->setArgument('$inner', new Reference(WikiTenantUrlGenerator::class . '.inner'))
                ->setArgument('$tenantPaths', new Reference(WikiTenantPaths::class)));
        }
    }

    /**
     * 1.10: UiKit page contract (REQ-UI-003): page plumbing (layout, per-page templates) and the view-model factory.
     *
     * @param array<string, mixed> $config processed configuration
     */
    private function registerPages(ContainerBuilder $container, array $config): void
    {
        $templates = self::templates($config);
        $container->setDefinition(WikiPages::class, (new Definition(WikiPages::class))
            ->setArgument('$layoutTemplate', $templates['layout'])
            ->setArgument('$templates', array_diff_key($templates, ['layout' => true]))
            ->setArgument('$twig', new Reference('twig', ContainerInterface::NULL_ON_INVALID_REFERENCE)));
        $container->setDefinition(WikiPageFactory::class, (new Definition(WikiPageFactory::class))
            ->setArgument('$urlGenerator', new Reference('router'))
            ->setArgument('$routes', $config['routes'])
            ->setArgument('$pages', new Reference(WikiPages::class))
            ->setArgument('$dashboardRoute', $config['dashboard_route']));
    }

    /**
     * REQ-SEC-004: a security switch must be a literal boolean. An env placeholder (e.g. "%env(bool:WIKI_PUBLIC)%")
     * reaches load() as a placeholder string, which a cast would turn into true whatever the env value.
     *
     * @throws InvalidConfigurationException when the value is not a boolean
     */
    private function literalBool(mixed $value, string $path): bool
    {
        if (!is_bool($value)) {
            throw new InvalidConfigurationException(sprintf('nowo_wiki.%s must be a literal boolean (true / false); env placeholders and other values are not supported for this security-relevant switch.', $path));
        }

        return $value;
    }

    /**
     * `allow_unauthenticated` lets every wiki action through without a role check: a dev / demo switch, refused in
     * the prod environment and in any non-debug kernel outside the test environments (test*).
     *
     * @throws InvalidConfigurationException in production
     */
    private function refuseAllowUnauthenticatedInProduction(ContainerBuilder $container): void
    {
        $environment = $container->hasParameter('kernel.environment') ? $container->getParameter('kernel.environment') : '';
        $environment = is_string($environment) ? $environment : '';
        $debug       = !$container->hasParameter('kernel.debug') || (bool) $container->getParameter('kernel.debug');

        if ($environment === 'prod' || (!$debug && !str_starts_with($environment, 'test'))) {
            throw new InvalidConfigurationException('nowo_wiki.security.allow_unauthenticated is a dev/demo switch: it lets every wiki action through without a role check and is refused in the prod environment and in non-debug kernels (test* environments excepted).');
        }
    }

    /**
     * Route path prefix: web_ui.path_prefix, else the deprecated route_prefix, else none.
     *
     * @param array<string, mixed> $config processed configuration
     */
    public static function pathPrefix(array $config): string
    {
        $prefix = $config['web_ui']['path_prefix'] ?? $config['route_prefix'] ?? '';

        return is_string($prefix) ? $prefix : '';
    }

    /**
     * Layout and page templates: web_ui.layout_template / web_ui.templates.<page>, else the deprecated
     * templates.layout / templates.<page>, else null (UiKit admin layout) / the bundle template.
     *
     * @param array<string, mixed> $config processed configuration
     *
     * @return array<string, string|null> `layout` + page id => template
     */
    public static function templates(array $config): array
    {
        $webUi     = is_array($config['web_ui'] ?? null) ? $config['web_ui'] : [];
        $legacy    = is_array($config['templates'] ?? null) ? $config['templates'] : [];
        $overrides = is_array($webUi['templates'] ?? null) ? $webUi['templates'] : [];
        $layout    = $webUi['layout_template'] ?? $legacy['layout'] ?? null;

        $templates = ['layout' => is_string($layout) && $layout !== '' && $layout !== WikiPages::LEGACY_DEFAULT_LAYOUT ? $layout : null];
        foreach (WikiPages::TEMPLATES as $page => $default) {
            $template         = $overrides[$page] ?? $legacy[$page] ?? null;
            $templates[$page] = is_string($template) && $template !== '' ? $template : $default;
        }

        return $templates;
    }

    /**
     * 1.9: attachment storage, scanner and limits (each replaceable by a service id or an alias override).
     *
     * @param array{enabled: bool, storage_dir: string, storage: ?string, scanner: ?string, max_file_bytes: int, max_space_bytes: int, allowed_types: array<string, list<string>>} $attachments
     */
    private function registerAttachments(ContainerBuilder $container, array $attachments): void
    {
        $container->setDefinition(LocalWikiAttachmentStorage::class, (new Definition(LocalWikiAttachmentStorage::class))
            ->setArgument('$directory', $attachments['storage_dir']));
        $container->setAlias(WikiAttachmentStorageInterface::class, $attachments['storage'] ?? LocalWikiAttachmentStorage::class);

        $container->setDefinition(NullWikiAttachmentScanner::class, new Definition(NullWikiAttachmentScanner::class));
        $container->setAlias(WikiAttachmentScannerInterface::class, $attachments['scanner'] ?? NullWikiAttachmentScanner::class);

        $container->setDefinition(ConfiguredWikiAttachmentLimitsProvider::class, (new Definition(ConfiguredWikiAttachmentLimitsProvider::class))
            ->setArgument('$maxFileBytes', $attachments['max_file_bytes'])
            ->setArgument('$maxSpaceBytes', $attachments['max_space_bytes'])
            ->setArgument('$allowedTypes', $attachments['allowed_types']));
        $container->setAlias(WikiAttachmentLimitsProviderInterface::class, ConfiguredWikiAttachmentLimitsProvider::class);
    }

    /**
     * @param array{enabled: bool, agent: string, context_injection: bool, max_context_pages: int, max_context_chars: int, system_prompt: ?string, http_timeout: int} $aiConfig
     */
    private function registerAiServices(ContainerBuilder $container, array $aiConfig): void
    {
        $container->setDefinition(NullWikiAiAssistant::class, new Definition(NullWikiAiAssistant::class));

        if (!$aiConfig['enabled']) {
            $container->setAlias(WikiAiAssistantInterface::class, NullWikiAiAssistant::class);

            return;
        }

        if (!interface_exists(AgentInterface::class)) {
            // @codeCoverageIgnoreStart
            throw new LogicException('nowo_wiki.ai.enabled is true but symfony/ai-bundle is not installed. Run: composer require symfony/ai-bundle');
            // @codeCoverageIgnoreEnd
        }

        $container->setDefinition('nowo_wiki.ai.http_client', (new Definition(HttpClientInterface::class))
            ->setFactory([new Reference('http_client'), 'withOptions'])
            ->setArguments([[
                'timeout'      => (float) $aiConfig['http_timeout'],
                'max_duration' => (float) $aiConfig['http_timeout'],
            ]]));

        $agentName = $aiConfig['agent'];
        $agentId   = str_starts_with($agentName, 'ai.agent.') ? $agentName : 'ai.agent.' . $agentName;

        $container->setDefinition(WikiContextRetriever::class, (new Definition(WikiContextRetriever::class))->setAutowired(true));
        $container->setDefinition(WikiKnowledgeSearchTool::class, (new Definition(WikiKnowledgeSearchTool::class))->setAutowired(true));
        $container->setDefinition(SymfonyAiWikiAssistant::class, (new Definition(SymfonyAiWikiAssistant::class))
            ->setAutowired(false)
            ->setArgument('$agent', new Reference($agentId))
            ->setArgument('$contextRetriever', new Reference(WikiContextRetriever::class))
            ->setArgument('$useContextInjection', $aiConfig['context_injection'])
            ->setArgument('$maxContextPages', $aiConfig['max_context_pages'])
            ->setArgument('$maxContextChars', $aiConfig['max_context_chars'])
            ->setArgument('$systemPrompt', $aiConfig['system_prompt'])
            ->setArgument('$logger', new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE))
            ->addTag('monolog.logger', ['channel' => 'nowo_wiki']));
        $container->setAlias(WikiAiAssistantInterface::class, SymfonyAiWikiAssistant::class);
    }

    /**
     * Returns the configuration alias of the bundle.
     *
     * @return string the {@code nowo_wiki} alias
     */
    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }

    /**
     * Prefer kernel.bundles: ContainerBuilder::hasExtension() can be false while SecurityBundle
     * is already registered (e.g. during early Flex cache:clear boots).
     */
    private function isSecurityBundleAvailable(ContainerBuilder $container): bool
    {
        if ($container->hasExtension('security')) {
            return true;
        }

        if (!$container->hasParameter('kernel.bundles')) {
            return false;
        }

        /** @var array<string, class-string> $bundles */
        $bundles = $container->getParameter('kernel.bundles');

        return isset($bundles['SecurityBundle']);
    }

    /**
     * Prepends Twig globals, asset package, Doctrine mapping, FormKit and UiKit defaults when those extensions are present.
     *
     * @param ContainerBuilder $container container being built
     */
    public function prepend(ContainerBuilder $container): void
    {
        // Parameter must exist before Twig globals resolve during container build.
        if (!$container->hasParameter('nowo_wiki.web_ui')) {
            $container->setParameter('nowo_wiki.web_ui', [
                'enabled'         => true,
                'css_framework'   => null,
                'layout_template' => null,
                'path_prefix'     => '',
                'templates'       => [],
            ]);
        }

        if ($container->hasExtension('twig')) {
            $container->prependExtensionConfig('twig', [
                'globals' => [
                    'nowo_wiki_web_ui' => '%nowo_wiki.web_ui%',
                ],
            ]);
        }

        if ($container->hasExtension('framework')) {
            $container->prependExtensionConfig('framework', [
                'assets' => [
                    'packages' => [
                        'nowo_wiki' => [
                            'base_path' => '/bundles/wiki',
                        ],
                    ],
                ],
            ]);
        }

        if ($container->hasExtension('doctrine')) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'mappings' => [
                        'WikiBundle' => [
                            'type'      => 'attribute',
                            'is_bundle' => true,
                        ],
                    ],
                ],
            ]);
        }

        $this->prependFormKitDefaults($container);
        $this->prependUiKitDefaults($container);
    }

    /**
     * When FormKit is installed, register the wiki profile. Forms select it via #[FormKitConfig].
     */
    private function prependFormKitDefaults(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('nowo_form_kit')) {
            return;
        }

        $hostHasCssFramework = false;
        $hostHasProfile      = false;
        foreach ($container->getExtensionConfig('nowo_form_kit') as $cfg) {
            /** @var array<string, mixed> $cfg */
            if (array_key_exists('css_framework', $cfg)) {
                $hostHasCssFramework = true;
            }
            $profiles = $cfg['profiles'] ?? null;
            if (is_array($profiles) && array_key_exists('wiki', $profiles)) {
                $hostHasProfile = true;
            }
        }

        $seed = [];

        if (!$hostHasCssFramework) {
            $seed['css_framework'] = 'bootstrap';
        }

        if (!$hostHasProfile) {
            $seed['profiles'] = [
                'wiki' => [
                    'alias'              => 'wiki',
                    'translation_domain' => 'NowoWikiBundle',
                    'defaults'           => [
                        'attr'     => ['class' => 'nowo-ui-input form-control'],
                        'row_attr' => ['class' => 'mb-2'],
                    ],
                    'field_types' => [
                        'checkbox' => [
                            'attr'     => ['class' => 'form-check-input'],
                            'row_attr' => ['class' => 'form-check mb-2'],
                        ],
                        'choice' => [
                            'attr' => ['class' => 'form-select'],
                        ],
                        'entity' => [
                            'attr' => ['class' => 'form-select'],
                        ],
                        'file' => [
                            'attr' => ['class' => 'nowo-ui-input form-control'],
                        ],
                        'textarea' => [
                            'attr' => ['class' => 'nowo-ui-input form-control'],
                        ],
                    ],
                ],
            ];
        }

        if ($seed !== []) {
            $container->prependExtensionConfig('nowo_form_kit', $seed);
        }
    }

    /**
     * When UiKit is installed and the host still sets the deprecated web_ui.css_framework, seed
     * nowo_ui_kit.css_framework / icon_set from it. Does not override keys the host already set.
     */
    private function prependUiKitDefaults(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('nowo_ui_kit')) {
            return;
        }

        $hostHasCssFramework = false;
        $hostHasIconSet      = false;
        foreach ($container->getExtensionConfig('nowo_ui_kit') as $cfg) {
            if (!is_array($cfg)) {
                continue;
            }
            if (array_key_exists('css_framework', $cfg)) {
                $hostHasCssFramework = true;
            }
            if (array_key_exists('icon_set', $cfg)) {
                $hostHasIconSet = true;
            }
        }

        if ($hostHasCssFramework && $hostHasIconSet) {
            return;
        }

        // Deprecated web_ui.css_framework (1.10): seeds UiKit only when the host set it and left UiKit unset.
        $framework = null;
        foreach ($container->getExtensionConfig(Configuration::ALIAS) as $cfg) {
            if (is_array($cfg) && is_array($cfg['web_ui'] ?? null) && is_string($cfg['web_ui']['css_framework'] ?? null)) {
                $framework = $cfg['web_ui']['css_framework'];
            }
        }
        if ($framework === null) {
            return;
        }

        $defaults = [];
        if (!$hostHasCssFramework) {
            $defaults['css_framework'] = $framework === 'bootstrap' ? 'bootstrap5' : $framework;
        }
        if (!$hostHasIconSet) {
            $defaults['icon_set'] = $framework === 'tabler' ? 'tabler-icons' : 'bootstrap-icons';
        }

        if ($defaults !== []) {
            $container->prependExtensionConfig('nowo_ui_kit', $defaults);
        }
    }
}
