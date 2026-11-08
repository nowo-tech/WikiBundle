<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\DependencyInjection;

use ArrayObject;
use LogicException;
use Nowo\MilkdownEditorBundle\Comment\CommentAnchor;
use Nowo\MilkdownEditorBundle\Markdown\MilkdownMarkdownRenderer;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\IconResolverInterface;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\UserResolverInterface;
use Nowo\MilkdownEditorBundle\ProfileRegistry;
use Nowo\MilkdownEditorBundle\Twig\MilkdownEditorTwig;
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
use Nowo\WikiBundle\Avatar\NullWikiUserAvatarResolver;
use Nowo\WikiBundle\Avatar\WikiAvatars;
use Nowo\WikiBundle\Avatar\WikiUserAvatarResolverInterface;
use Nowo\WikiBundle\Collaboration\DoctrineWikiCollabStateStore;
use Nowo\WikiBundle\Collaboration\MercureWikiCollabPublisher;
use Nowo\WikiBundle\Collaboration\MercureWikiCollabSubscriberAuthorizer;
use Nowo\WikiBundle\Collaboration\WikiCollabPublisherInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabSaveListener;
use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Nowo\WikiBundle\Collaboration\WikiCollabStateStoreInterface;
use Nowo\WikiBundle\Command\WikiSearchBackfillTextCommand;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Comment\WikiCommentsPanel;
use Nowo\WikiBundle\DependencyInjection\Compiler\MilkdownBridgePass;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Doctrine\WikiMetadataListener;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\History\WikiAuthorLabel;
use Nowo\WikiBundle\History\WikiHistoryThinner;
use Nowo\WikiBundle\History\WikiHistoryTimeline;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Link\WikiObjectLinkResolverInterface;
use Nowo\WikiBundle\Markdown\Milkdown\MilkdownWikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownIconHydrator;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownPageResolver;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownRichContent;
use Nowo\WikiBundle\Markdown\WikiMarkdownExtensionInterface;
use Nowo\WikiBundle\Markdown\WikiMarkdownReferenceExtractor;
use Nowo\WikiBundle\Markdown\WikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface;
use Nowo\WikiBundle\Markdown\WikiRenderContext;
use Nowo\WikiBundle\PageFile\WikiPageFileExtractor;
use Nowo\WikiBundle\Panel\SessionWikiPanelStateStore;
use Nowo\WikiBundle\Panel\WikiPanelStateStoreInterface;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiAttachmentRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiCommentRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageLinkRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiRevisionLabelRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiSpaceRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiTrashRepository;
use Nowo\WikiBundle\Repository\WikiAttachmentRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiCommentRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageLinkRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiRevisionLabelRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiTrashRepositoryInterface;
use Nowo\WikiBundle\Routing\WikiTenantPaths;
use Nowo\WikiBundle\Routing\WikiTenantUrlGenerator;
use Nowo\WikiBundle\Search\WikiSearchScopeListener;
use Nowo\WikiBundle\Security\AllowAllWikiAccessChecker;
use Nowo\WikiBundle\Security\ConfigurableWikiAccessChecker;
use Nowo\WikiBundle\Security\LegacyWikiSpaceAccessChecker;
use Nowo\WikiBundle\Security\NullWikiTeamMembershipResolver;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Security\WikiTeamMembershipResolverInterface;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Service\AccessibleWikiSpaceLocator;
use Nowo\WikiBundle\Service\WikiPageMover;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolver;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Service\WikiSpaceLocatorInterface;
use Nowo\WikiBundle\Trash\WikiTrashService;
use Nowo\WikiBundle\Tree\SessionWikiTreeStateStore;
use Nowo\WikiBundle\Tree\WikiTreeStateStoreInterface;
use Nowo\WikiBundle\Ui\WikiPageFactory;
use Nowo\WikiBundle\Ui\WikiPages;
use Symfony\AI\Agent\AgentInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_diff_key;
use function array_filter;
use function array_intersect_key;
use function array_key_exists;
use function array_merge;
use function array_unique;
use function array_values;
use function class_exists;
use function interface_exists;
use function is_array;
use function is_bool;
use function is_string;
use function rtrim;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strtolower;
use function trigger_deprecation;
use function trim;

/**
 * Loads bundle configuration and registers services.
 */
final class WikiExtension extends Extension implements PrependExtensionInterface
{
    /** 1.21: service id of the Twig global `nowo_wiki_web_ui` (an ArrayObject of %nowo_wiki.web_ui%). */
    public const WEB_UI_GLOBAL = 'nowo_wiki.web_ui_global';

    /** 1.13: elements of the Milkdown dialect markup kept by the sanitizer with editor.type milkdown. */
    public const MILKDOWN_SANITIZER_ELEMENTS = ['details' => ['open'], 'summary' => []];

    /**
     * 1.13: attributes of the Milkdown dialect markup kept on every allowed element with editor.type milkdown
     * (callout / icon roles and labels, math and directive markers, icon placeholders).
     */
    public const MILKDOWN_SANITIZER_ATTRIBUTES = ['role', 'aria-label', 'data-math', 'data-directive', 'data-icon'];

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
        $config        = $this->milkdownDefaults($config);
        $this->warnAboutHostIds($config['sanitizer']);

        // BB L1: the switches that open the wiki must be literal booleans (an env placeholder string would be truthy).
        $config['web_ui']['enabled']                 = $this->literalBool($config['web_ui']['enabled'], 'web_ui.enabled');
        $config['security']['allow_unauthenticated'] = $this->literalBool($config['security']['allow_unauthenticated'], 'security.allow_unauthenticated');
        // 1.13.1 (audit DA-M1): the schema listener and the collaboration services must agree; an env placeholder would
        // register the services while the schema left the tables out (and a diff would drop them).
        $config['collaboration']['enabled'] = $this->literalBool($config['collaboration']['enabled'], 'collaboration.enabled');
        // 1.14: without the tree its two routes are not registered (byte-identical route list and pages).
        $config['tree']['enabled'] = $this->literalBool($config['tree']['enabled'], 'tree.enabled');
        if (!$config['tree']['enabled']) {
            unset($config['routes']['page_move'], $config['routes']['space_tree']);
        }
        // 1.15: the history timeline; its table and routes exist only when it is on.
        $config['history']['timeline'] = $this->literalBool($config['history']['timeline'], 'history.timeline');
        if (!$config['history']['timeline']) {
            unset($config['routes']['page_version_save'], $config['routes']['page_version_copy']);
        }
        // 1.14 (T065): the trash; its table and routes exist only when it is on.
        $config['trash']['enabled'] = $this->literalBool($config['trash']['enabled'], 'trash.enabled');
        if (!$config['trash']['enabled']) {
            unset($config['routes']['page_delete'], $config['routes']['space_trash'], $config['routes']['trash_restore'], $config['routes']['trash_purge']);
        }
        // 1.16: comments; their tables and routes exist only when they are on.
        $config['comments']['enabled']          = $this->literalBool($config['comments']['enabled'], 'comments.enabled');
        $config['comments']['read_view_source'] = $this->literalBool($config['comments']['read_view_source'], 'comments.read_view_source');
        if (!$config['comments']['enabled']) {
            unset($config['routes']['page_comments'], $config['routes']['space_panel_state']);
        }
        // 1.19.1: with the AI off (default) its question route is not registered, so no endpoint or link exists.
        if (!$config['ai']['enabled']) {
            unset($config['routes']['ai_ask']);
        }
        // 1.21: without the space index its route is not registered (404, no link). Decided at compile time, so literal.
        if (!$this->literalBool($config['web_ui']['space_index'], 'web_ui.space_index')) {
            unset($config['routes']['index']);
        }
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
        $container->setParameter('nowo_wiki.import_export.max_document_bytes', $config['import_export']['max_document_bytes']);
        $templates = self::templates($config);
        $container->setParameter('nowo_wiki.web_ui', [
            'enabled'         => (bool) $config['web_ui']['enabled'],
            'css_framework'   => $config['web_ui']['css_framework'],
            'layout_template' => $templates['layout'],
            'path_prefix'     => self::pathPrefix($config),
            'templates'       => array_diff_key($templates, ['layout' => true]),
            // 1.21: read by the page templates through the Twig global nowo_wiki_web_ui.
            'space_index' => (bool) $config['web_ui']['space_index'],
        ] + self::ui($config['web_ui']));
        // 1.21 (audit GV-L1): the Twig global. A service, not %nowo_wiki.web_ui%: TwigExtension resolves a parameter while it
        // loads, so with TwigBundle registered first the global would hold the prepend() defaults, not this config.
        $container->setDefinition(self::WEB_UI_GLOBAL, new Definition(ArrayObject::class, ['%nowo_wiki.web_ui%']));
        $container->setParameter('nowo_wiki.search.scope', $config['search']['scope']);
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
        $container->setDefinition(WikiHtmlSanitizer::class, (new Definition(WikiHtmlSanitizer::class))
            ->setArgument('$extraAllowedElements', $config['sanitizer']['extra_allowed_elements'])
            ->setArgument('$extraAllowedAttributes', array_values($config['sanitizer']['extra_allowed_attributes'])));
        if ($config['editor']['type'] === Configuration::EDITOR_MILKDOWN) {
            $this->registerMilkdown($container, $config);
        }

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
            ->setArgument('$collabDocumentsTableName', $prefix . '_page_collab_documents')
            ->setArgument('$collabUpdatesTableName', $prefix . '_page_collab_updates')
            ->setArgument('$collaborationEnabled', $config['collaboration']['enabled'])
            ->setArgument('$trashTableName', $prefix . '_page_trash')
            ->setArgument('$trashEnabled', $config['trash']['enabled'])
            ->setArgument('$revisionLabelsTableName', $prefix . '_revision_labels')
            ->setArgument('$historyTimeline', $config['history']['timeline'])
            ->setArgument('$commentTablePrefix', $prefix)
            ->setArgument('$commentsEnabled', $config['comments']['enabled'])
            ->addTag('doctrine.event_listener', ['event' => 'loadClassMetadata'])
            ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema']));

        $container->setDefinition(WikiSpaceAccessResolver::class, (new Definition(WikiSpaceAccessResolver::class))
            ->setAutowired(true)
            ->setArgument('$defaultOwnerScope', $config['space_scope']));
        $container->setAlias(WikiSpaceAccessResolverInterface::class, WikiSpaceAccessResolver::class);

        $this->registerSpaceAccess($container, $config);
        $this->registerPages($container, $config);
        $this->registerAttachments($container, $config['attachments']);
        $this->registerCollaboration($container, $config);
        $this->registerTree($container, $config);
        $this->registerTrash($container, $config, $emProviderRef);
        $this->registerPageFiles($container, $config);
        $this->registerAvatars($container, $config);
        $this->registerHistory($container, $config, $emProviderRef);
        $this->registerComments($container, $config, $emProviderRef);

        $container->getDefinition(WikiSearchService::class)->setArgument('$entityManager', $emProviderRef);
        // 1.16: wiki:search:backfill-text fills the revisions' plain-text column.
        $container->getDefinition(WikiSearchBackfillTextCommand::class)->setArgument('$entityManager', $emProviderRef);

        $this->registerAiServices($container, $config['ai']);
    }

    /**
     * 1.16.2 (audit EP-I4): the sanitizer keeps an `id` only when it is a renderer anchor (`nowo-h-…`, `nowo-fn-…`), so an
     * `id` in the host allowlist no longer has any effect; say so once, at container build (`name` is refused outright).
     *
     * @param array<string, mixed> $sanitizer the processed `sanitizer` configuration
     */
    private function warnAboutHostIds(array $sanitizer): void
    {
        $attributes = $sanitizer['extra_allowed_attributes'] ?? [];
        foreach ($sanitizer['extra_allowed_elements'] ?? [] as $elementAttributes) {
            $attributes = array_merge($attributes, is_array($elementAttributes) ? $elementAttributes : []);
        }
        foreach ($attributes as $attribute) {
            if (is_string($attribute) && strtolower($attribute) === 'id') {
                trigger_deprecation('nowo-tech/wiki-bundle', '1.16.2', 'Allowing "id" in nowo_wiki.sanitizer.extra_allowed_elements / extra_allowed_attributes has no effect: the wiki sanitizer keeps only the "nowo-h-" / "nowo-fn-" anchors of the Markdown renderer and drops every other id (DOM clobbering). Remove "id" from the configuration.');

                return;
            }
        }
    }

    /**
     * 1.13: `editor.type: milkdown` — the page body is MilkdownEditorType unless `markdown_form_type` names another type,
     * and the sanitizer keeps the markup of the dialect ({@see self::MILKDOWN_SANITIZER_ELEMENTS} /
     * {@see self::MILKDOWN_SANITIZER_ATTRIBUTES}) on top of the host's extra allowlist.
     *
     * @param array<string, mixed> $config processed configuration
     * @param string $milkdownRenderer class that proves nowo-tech/milkdown-editor-bundle is installed
     * @param string $milkdownTwig its Twig extension (1.14: `copy_markdown` needs its copyMarkdown(), Milkdown 1.5)
     *
     * @throws LogicException when nowo-tech/milkdown-editor-bundle is not installed
     *
     * @return array<string, mixed> the configuration with the Milkdown defaults
     */
    private function milkdownDefaults(array $config, string $milkdownRenderer = MilkdownMarkdownRenderer::class, string $milkdownTwig = MilkdownEditorTwig::class): array
    {
        if ($config['editor']['type'] !== Configuration::EDITOR_MILKDOWN) {
            return $config;
        }

        if (!class_exists($milkdownRenderer)) {
            throw new LogicException('nowo_wiki.editor.type "milkdown" requires nowo-tech/milkdown-editor-bundle (composer require nowo-tech/milkdown-editor-bundle) with NowoMilkdownEditorBundle registered.');
        }

        $config['editor']['markdown_form_type'] ??= Configuration::MILKDOWN_FORM_TYPE;
        // 1.15: the page-view "Copy as Markdown" button prints nowo_milkdown_copy_markdown() (Milkdown 1.5).
        if (($config['editor']['milkdown']['copy_markdown'] ?? false) === true && !method_exists($milkdownTwig, 'copyMarkdown')) {
            throw new LogicException('nowo_wiki.editor.milkdown.copy_markdown needs nowo-tech/milkdown-editor-bundle >= 1.5 (composer require nowo-tech/milkdown-editor-bundle:^1.5).');
        }
        foreach (self::MILKDOWN_SANITIZER_ELEMENTS as $element => $attributes) {
            $config['sanitizer']['extra_allowed_elements'][$element] = array_values(array_unique(array_merge($config['sanitizer']['extra_allowed_elements'][$element] ?? [], $attributes)));
        }

        $config['sanitizer']['extra_allowed_attributes'] = array_values(array_unique(array_merge($config['sanitizer']['extra_allowed_attributes'], self::MILKDOWN_SANITIZER_ATTRIBUTES)));

        return $config;
    }

    /**
     * 1.13: the Milkdown ↔ wiki bridge — the dialect renderer (alias of {@see WikiMarkdownRendererInterface}), the
     * per-viewer page resolver, the icon hydrator and the read-side assets. {@see MilkdownBridgePass} gives the
     * renderer the `nowo_milkdown_editor.markdown` options (limits) once every extension is loaded.
     *
     * @param array<string, mixed> $config processed configuration
     */
    private function registerMilkdown(ContainerBuilder $container, array $config): void
    {
        $container->setDefinition(WikiMilkdownPageResolver::class, (new Definition(WikiMilkdownPageResolver::class))
            ->setArgument('$pages', new Reference(WikiPageRepositoryInterface::class))
            ->setArgument('$spaceAccess', new Reference(WikiSpaceAccessCheckerInterface::class))
            ->setArgument('$accessChecker', new Reference(WikiAccessCheckerInterface::class))
            ->setArgument('$tokenStorage', new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$urlGenerator', new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$routes', $config['routes'])
            ->setArgument('$tokenGuard', new Reference(WikiTokenGuard::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$renderContext', new Reference(WikiRenderContext::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)));

        $pageResolver = $config['editor']['milkdown']['page_resolver'] ?? null;
        $container->setDefinition(MilkdownWikiMarkdownRenderer::class, (new Definition(MilkdownWikiMarkdownRenderer::class))
            ->setArgument('$options', [])
            ->setArgument('$pages', new Reference(is_string($pageResolver) && $pageResolver !== '' ? $pageResolver : WikiMilkdownPageResolver::class))
            ->setArgument('$users', new Reference(UserResolverInterface::class))
            ->setArgument('$icons', new Reference(IconResolverInterface::class))
            ->setArgument('$directiveRenderers', new TaggedIteratorArgument('nowo_milkdown_editor.directive_renderer'))
            ->setArgument('$extensions', new TaggedIteratorArgument('nowo_wiki.markdown_extension'))
            ->setArgument('$translator', new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$logger', new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->addTag('monolog.logger', ['channel' => 'nowo_wiki']));
        $container->setAlias(WikiMarkdownRendererInterface::class, MilkdownWikiMarkdownRenderer::class);

        // HTML revisions opened in the editor, exported or converted by wiki:convert-markdown are written in the dialect.
        $container->getDefinition(WikiMarkdownConverter::class)->setArgument('$dialect', true);
        $container->setDefinition(WikiMilkdownIconHydrator::class, (new Definition(WikiMilkdownIconHydrator::class))
            ->setArgument('$icons', new Reference(IconResolverInterface::class)));
        $container->setDefinition(WikiMilkdownRichContent::class, (new Definition(WikiMilkdownRichContent::class))
            ->setArgument('$twig', new Reference(MilkdownEditorTwig::class)));
    }

    /**
     * 1.15: the history timeline — the named-version store, the author labels, the timeline service and the retention
     * (`wiki:history:thin` gets the thinner when it exists).
     *
     * @param array<string, mixed> $config processed configuration
     */
    private function registerHistory(ContainerBuilder $container, array $config, Reference $entityManager): void
    {
        /** @var array{timeline: bool, session_minutes: int, show_names: bool, retention_days: int} $history */
        $history = $config['history'];
        if (!$history['timeline']) {
            return;
        }
        $container->setDefinition(DoctrineOrmWikiRevisionLabelRepository::class, (new Definition(DoctrineOrmWikiRevisionLabelRepository::class))
            ->setArgument('$entityManager', $entityManager));
        $container->setAlias(WikiRevisionLabelRepositoryInterface::class, DoctrineOrmWikiRevisionLabelRepository::class);
        $container->setDefinition(WikiHistoryTimeline::class, (new Definition(WikiHistoryTimeline::class))
            ->setArgument('$labels', new Reference(WikiRevisionLabelRepositoryInterface::class))
            ->setArgument('$authors', new Reference(WikiAuthorLabel::class))
            ->setArgument('$sessionMinutes', $history['session_minutes']));
        $container->setDefinition(WikiHistoryThinner::class, (new Definition(WikiHistoryThinner::class))
            ->setArgument('$entityManager', $entityManager)
            ->setArgument('$retentionDays', $history['retention_days'])
            ->setArgument('$sessionMinutes', $history['session_minutes']));
    }

    /**
     * 1.16: the avatar hook (a null resolver by alias; the host aliases {@see WikiUserAvatarResolverInterface} to its
     * service), the per-response memo and the author labels of the history and the comments, which only ever resolve
     * the picture of an author shown under a real name (`history.show_names` and {@see WikiAuthorLabel}).
     *
     * @param array<string, mixed> $config processed configuration
     */
    private function registerAvatars(ContainerBuilder $container, array $config): void
    {
        $container->setDefinition(NullWikiUserAvatarResolver::class, new Definition(NullWikiUserAvatarResolver::class));
        $container->setAlias(WikiUserAvatarResolverInterface::class, NullWikiUserAvatarResolver::class);
        $container->setDefinition(WikiAvatars::class, (new Definition(WikiAvatars::class))
            ->setArgument('$resolver', new Reference(WikiUserAvatarResolverInterface::class))
            ->addTag('kernel.reset', ['method' => 'reset']));
        $container->setDefinition(WikiAuthorLabel::class, (new Definition(WikiAuthorLabel::class))
            ->setArgument('$secret', '%kernel.secret%')
            ->setArgument('$showNames', (bool) $config['history']['show_names'])
            ->setArgument('$avatars', new Reference(WikiAvatars::class)));
    }

    /**
     * 1.16: comments — the Doctrine store, the panel state store (the session unless `comments.state_store` names a
     * service), the service behind the endpoints and the panel of the read and edit views.
     *
     * @param array<string, mixed> $config processed configuration
     *
     * @throws LogicException without the Milkdown editor (>= 1.6), without a rate limiter, or with a `page_comments` path
     *                        that does not end with `/{path}`
     */
    private function registerComments(ContainerBuilder $container, array $config, Reference $entityManager, string $anchorClass = CommentAnchor::class): void
    {
        /** @var array{enabled: bool, rate_limiter: string|null, page_rate_limiter: string|null, state_store: string|null, shortcut: string|null, read_view_source: bool, moderation_panel: mixed, error_notices: bool, limits: array{threads_per_page: int, total_threads_per_page: int, user_threads_per_page: int, comments_per_thread: int, reactions_per_comment: int, user_reactions_per_comment: int}} $comments */
        $comments = $config['comments'];
        if (!$comments['enabled']) {
            return;
        }
        if ($config['editor']['type'] !== Configuration::EDITOR_MILKDOWN) {
            throw new LogicException('nowo_wiki.comments needs nowo_wiki.editor.type "milkdown".');
        }
        if (!class_exists($anchorClass)) {
            throw new LogicException('nowo_wiki.comments needs nowo-tech/milkdown-editor-bundle >= 1.6 (comment anchors and the thread component): composer require nowo-tech/milkdown-editor-bundle:^1.6');
        }
        if (!is_string($comments['rate_limiter']) || $comments['rate_limiter'] === '') {
            throw new LogicException('nowo_wiki.comments needs a rate limiter: set comments.rate_limiter to the service id of a Symfony RateLimiterFactory (e.g. limiter.wiki_comments from framework.rate_limiter.wiki_comments: {policy: sliding_window, limit: 60, interval: "1 minute"}). It limits the comment writes per user and page.');
        }
        if (!str_ends_with((string) $config['routes']['page_comments']['path'], '/{path}')) {
            throw new LogicException('nowo_wiki.routes.page_comments.path must end with "/{path}" (the endpoints of the comment thread component live under it).');
        }

        $container->setDefinition(DoctrineOrmWikiCommentRepository::class, (new Definition(DoctrineOrmWikiCommentRepository::class))
            ->setArgument('$entityManager', $entityManager));
        $container->setAlias(WikiCommentRepositoryInterface::class, DoctrineOrmWikiCommentRepository::class);
        // EI-L5: the trash purge deletes the comments of purged pages explicitly.
        if ($container->hasDefinition(DoctrineOrmWikiTrashRepository::class)) {
            $container->getDefinition(DoctrineOrmWikiTrashRepository::class)->setArgument('$comments', new Reference(WikiCommentRepositoryInterface::class));
        }

        $store = $comments['state_store'];
        if (!is_string($store) || $store === '') {
            $store = SessionWikiPanelStateStore::class;
            $container->setDefinition($store, (new Definition(SessionWikiPanelStateStore::class))
                ->setArgument('$requestStack', new Reference('request_stack')));
        }
        $container->setAlias(WikiPanelStateStoreInterface::class, $store);

        $collab = $config['collaboration']['enabled'];
        $container->setDefinition(WikiCommentService::class, (new Definition(WikiCommentService::class))
            ->setArgument('$comments', new Reference(WikiCommentRepositoryInterface::class))
            ->setArgument('$guard', new Reference(WikiSpaceGuard::class))
            ->setArgument('$authors', new Reference(WikiAuthorLabel::class))
            ->setArgument('$renderer', new Reference(WikiMarkdownRendererInterface::class))
            ->setArgument('$sanitizer', new Reference(WikiHtmlSanitizerInterface::class))
            ->setArgument('$references', new Reference(WikiMarkdownReferenceExtractor::class))
            ->setArgument('$dispatcher', new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$collab', $collab ? new Reference(WikiCollabService::class) : null)
            ->setArgument('$publisher', $collab ? new Reference(WikiCollabPublisherInterface::class) : null)
            ->setArgument('$rateLimiter', new Reference($comments['rate_limiter']))
            ->setArgument('$translator', new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$pageRateLimiter', is_string($comments['page_rate_limiter']) && $comments['page_rate_limiter'] !== '' ? new Reference($comments['page_rate_limiter']) : null)
            ->setArgument('$accessChecker', new Reference(WikiAccessCheckerInterface::class))
            ->setArgument('$readViewSource', $comments['read_view_source'])
            ->setArgument('$limits', $comments['limits'])
            ->setArgument('$renderContext', new Reference(WikiRenderContext::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)));
        $container->setDefinition(WikiCommentsPanel::class, (new Definition(WikiCommentsPanel::class))
            ->setArgument('$comments', new Reference(WikiCommentService::class))
            ->setArgument('$state', new Reference(WikiPanelStateStoreInterface::class))
            ->setArgument('$urlGenerator', new Reference('router'))
            ->setArgument('$csrf', new Reference('security.csrf.token_manager'))
            ->setArgument('$routes', $config['routes'])
            ->setArgument('$shortcut', $comments['shortcut'])
            ->setArgument('$profiles', new Reference(ProfileRegistry::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$forms', new Reference('form.factory', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$readViewSource', $comments['read_view_source'])
            ->setArgument('$moderationPanel', $this->literalBool($comments['moderation_panel'], 'comments.moderation_panel'))
            ->setArgument('$errorNotices', $comments['error_notices']));
    }

    /**
     * 1.14 (T065): the trash — its Doctrine store and the service (space lock of the tree writer, attachments when
     * they are on); `wiki:trash:purge` gets the service when it exists.
     *
     * @param array<string, mixed> $config processed configuration
     */
    private function registerTrash(ContainerBuilder $container, array $config, Reference $entityManager): void
    {
        /** @var array{enabled: bool, retention_days: int} $trash */
        $trash = $config['trash'];
        if (!$trash['enabled']) {
            return;
        }

        $container->setDefinition(DoctrineOrmWikiTrashRepository::class, (new Definition(DoctrineOrmWikiTrashRepository::class))
            ->setArgument('$entityManager', $entityManager));
        $container->setAlias(WikiTrashRepositoryInterface::class, DoctrineOrmWikiTrashRepository::class);
        $container->setDefinition(WikiTrashService::class, (new Definition(WikiTrashService::class))
            ->setArgument('$pages', new Reference(WikiPageRepositoryInterface::class))
            ->setArgument('$trash', new Reference(WikiTrashRepositoryInterface::class))
            ->setArgument('$writer', new Reference(DoctrineOrmWikiPageRepository::class))
            ->setArgument('$dispatcher', new Reference('event_dispatcher'))
            ->setArgument('$storage', new Reference(WikiAttachmentStorageInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$attachmentRepository', new Reference(WikiAttachmentRepositoryInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$retentionDays', $trash['retention_days'])
            ->setArgument('$logger', new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->addTag('monolog.logger', ['channel' => 'nowo_wiki']));
    }

    /**
     * 1.20: the files a page refers to — the page view lists them (`page_files.enabled`, the controller gets the
     * extractor) and the trash purge reports them to the host (`page_files.purge_event`, needs the trash).
     *
     * @param array<string, mixed> $config processed configuration
     */
    private function registerPageFiles(ContainerBuilder $container, array $config): void
    {
        /** @var array{enabled: bool, purge_event: bool, max_files: int, max_purge_files: int} $files */
        $files = $config['page_files'];
        if ($files['enabled']) {
            $container->setDefinition(WikiPageFileExtractor::class, (new Definition(WikiPageFileExtractor::class))
                ->setArgument('$maxFiles', $files['max_files']));
        }
        if (!$files['purge_event']) {
            return;
        }
        if (!$container->hasDefinition(WikiTrashService::class)) {
            throw new LogicException('nowo_wiki.page_files.purge_event needs nowo_wiki.trash.enabled: files are reported when the trash purges pages.');
        }
        $container->getDefinition(WikiTrashService::class)
            ->setArgument('$pageFiles', (new Definition(WikiPageFileExtractor::class))->setArgument('$maxFiles', $files['max_files']))
            ->setArgument('$revisions', new Reference(WikiPageRevisionRepositoryInterface::class))
            ->setArgument('$maxPurgeFiles', $files['max_purge_files']);
    }

    /**
     * 1.14: the page tree — the mover (space-locked transaction on the Doctrine page repository, `nowo_wiki.page.moved`)
     * and the fold state store (the session unless `tree.state_store` names a service).
     *
     * @param array<string, mixed> $config processed configuration
     */
    private function registerTree(ContainerBuilder $container, array $config): void
    {
        /** @var array{enabled: bool, max_depth: int, state_store: string|null} $tree */
        $tree = $config['tree'];
        if (!$tree['enabled']) {
            return;
        }

        $store = $tree['state_store'];
        if (!is_string($store) || $store === '') {
            $store = SessionWikiTreeStateStore::class;
            $container->setDefinition($store, (new Definition(SessionWikiTreeStateStore::class))
                ->setArgument('$requestStack', new Reference('request_stack')));
        }
        $container->setAlias(WikiTreeStateStoreInterface::class, $store);

        $container->setDefinition(WikiPageMover::class, (new Definition(WikiPageMover::class))
            ->setArgument('$repository', new Reference(WikiPageRepositoryInterface::class))
            ->setArgument('$writer', new Reference(DoctrineOrmWikiPageRepository::class))
            ->setArgument('$dispatcher', new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$maxDepth', $tree['max_depth']));
    }

    /**
     * 1.13: real-time co-editing over Mercure — the service behind the page collaboration endpoints, its Yjs log
     * store, publisher and subscriber authorizer (symfony/mercure by default).
     *
     * @param array<string, mixed> $config processed configuration
     *
     * @throws LogicException when collaboration is enabled without the Milkdown editor or without symfony/mercure
     */
    private function registerCollaboration(ContainerBuilder $container, array $config, string $mercureHub = HubInterface::class): void
    {
        $collab = $config['collaboration'];
        if (!$collab['enabled']) {
            return;
        }

        if ($config['editor']['type'] !== Configuration::EDITOR_MILKDOWN) {
            throw new LogicException('nowo_wiki.collaboration needs nowo_wiki.editor.type "milkdown".');
        }

        if (!is_string($collab['rate_limiter']) || $collab['rate_limiter'] === '') {
            throw new LogicException('nowo_wiki.collaboration needs a rate limiter: set collaboration.rate_limiter to the service id of a Symfony RateLimiterFactory (e.g. limiter.wiki_collab from framework.rate_limiter.wiki_collab: {policy: sliding_window, limit: 600, interval: "1 minute"}). It limits every collaboration request per user and page.');
        }

        $needsMercure = !is_string($collab['publisher']) || !is_string($collab['subscriber_authorizer']);
        if ($needsMercure && !interface_exists($mercureHub)) {
            throw new LogicException('nowo_wiki.collaboration needs symfony/mercure-bundle (composer require symfony/mercure-bundle) or your own collaboration.publisher and collaboration.subscriber_authorizer services.');
        }

        $hub   = is_string($collab['mercure_hub']) && $collab['mercure_hub'] !== '' ? $collab['mercure_hub'] : null;
        $store = $collab['store'];
        if (!is_string($store) || $store === '') {
            $store = DoctrineWikiCollabStateStore::class;
            $container->setDefinition($store, (new Definition(DoctrineWikiCollabStateStore::class))
                ->setArgument('$entityManager', new Reference(WikiEntityManagerProvider::class)));
        }

        $container->setAlias(WikiCollabStateStoreInterface::class, $store);

        $publisher = $collab['publisher'];
        if (!is_string($publisher) || $publisher === '') {
            $publisher = MercureWikiCollabPublisher::class;
            $container->setDefinition($publisher, (new Definition(MercureWikiCollabPublisher::class))
                ->setArgument('$hub', new Reference($hub === null ? HubInterface::class : 'mercure.hub.' . $hub)));
        }

        $authorizer = $collab['subscriber_authorizer'];
        if (!is_string($authorizer) || $authorizer === '') {
            $authorizer = MercureWikiCollabSubscriberAuthorizer::class;
            $container->setDefinition($authorizer, (new Definition(MercureWikiCollabSubscriberAuthorizer::class))
                ->setArgument('$authorization', new Reference(Authorization::class))
                ->setArgument('$hub', $hub));
        }

        // 1.16: the comment writes publish their thread ids through the same publisher.
        $container->setAlias(WikiCollabPublisherInterface::class, $publisher);
        $container->setDefinition(WikiCollabService::class, (new Definition(WikiCollabService::class))
            ->setArgument('$logger', new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->addTag('monolog.logger', ['channel' => 'nowo_wiki'])
            ->setArgument('$store', new Reference($store))
            ->setArgument('$publisher', new Reference($publisher))
            ->setArgument('$authorizer', new Reference($authorizer))
            ->setArgument('$options', array_intersect_key($collab, WikiCollabService::DEFAULTS))
            ->setArgument('$rateLimiter', new Reference((string) $collab['rate_limiter']))
            ->setArgument('$userRateLimiter', is_string($collab['user_rate_limiter']) && $collab['user_rate_limiter'] !== '' ? new Reference($collab['user_rate_limiter']) : null)
            ->setArgument('$updateRateLimiter', is_string($collab['update_rate_limiter']) && $collab['update_rate_limiter'] !== '' ? new Reference($collab['update_rate_limiter']) : null)
            ->setArgument('$awarenessRateLimiter', is_string($collab['awareness_rate_limiter']) && $collab['awareness_rate_limiter'] !== '' ? new Reference($collab['awareness_rate_limiter']) : null)
            ->setArgument('$secret', '%kernel.secret%'));
        $container->setDefinition(WikiCollabSaveListener::class, (new Definition(WikiCollabSaveListener::class))
            ->setArgument('$collab', new Reference(WikiCollabService::class))
            ->setArgument('$requestStack', new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->addTag('kernel.event_listener', ['event' => WikiEvents::PAGE_SAVED]));
    }

    /**
     * 1.9: space-aware access (host overrides the aliases), link resolvers and tenant routes.
     *
     * @param array<string, mixed> $config processed configuration
     */
    private function registerSpaceAccess(ContainerBuilder $container, array $config): void
    {
        $container->setDefinition(LegacyWikiSpaceAccessChecker::class, (new Definition(LegacyWikiSpaceAccessChecker::class))
            ->setAutowired(true)
            ->addTag('kernel.reset', ['method' => 'reset']));
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
            ->setArgument('$dashboardRoute', $config['dashboard_route'])
            ->setArgument('$tabIcons', $config['web_ui']['tab_icons'])
            ->setArgument('$importExportDialog', $config['web_ui']['import_export'] === 'dialog'));
        if ($config['search']['excluded_spaces'] !== [] && $config['search']['scope'] !== 'space') {
            throw new LogicException('nowo_wiki.search.excluded_spaces needs search.scope: space.');
        }

        // 1.21 (search.scope: space): the search page answers inside one space only.
        if ($config['search']['scope'] === 'space' && isset($config['routes']['search'])) {
            $container->setDefinition(WikiSearchScopeListener::class, (new Definition(WikiSearchScopeListener::class))
                ->setArgument('$routeName', $config['routes']['search']['name'])
                ->setArgument('$slugRequirements', array_column($config['tenant_paths'], 'slug_requirement'))
                ->setArgument('$excludedSpaces', array_values($config['search']['excluded_spaces']))
                ->addTag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'priority' => 30]));
        }
    }

    /**
     * 1.21: the page options of the templates (Twig global `nowo_wiki_web_ui`): assets, slots and the import / export
     * placement.
     *
     * @param array<string, mixed> $webUi processed `web_ui` node
     *
     * @throws InvalidConfigurationException when a slot is not a template name
     *
     * @return array{assets: array{styles: bool, scripts: bool}, slots: array<string, string|null>, import_export: string}
     */
    public static function ui(array $webUi): array
    {
        $slots = [];
        foreach (WikiPages::SLOTS as $slot) {
            $template = $webUi['slots'][$slot] ?? null;
            if ($template !== null && (!is_string($template) || trim($template) === '')) {
                throw new InvalidConfigurationException(sprintf('nowo_wiki.web_ui.slots.%s must be a template name or null.', $slot));
            }
            $slots[$slot] = $template;
        }

        return [
            'assets'        => ['styles' => (bool) ($webUi['assets']['styles'] ?? true), 'scripts' => (bool) ($webUi['assets']['scripts'] ?? true)],
            'slots'         => $slots,
            'import_export' => ($webUi['import_export'] ?? 'page') === 'dialog' ? 'dialog' : 'page',
        ];
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
        // Defaults for host config that reads %nowo_wiki.web_ui% before load(); the Twig global is a service (see load()).
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
                    'nowo_wiki_web_ui' => '@' . self::WEB_UI_GLOBAL,
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
     * When FormKit is installed, register the wiki profile (alias + translation domain; the host form theme styles the
     * fields). Forms select it via #[FormKitConfig].
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
            // 1.12: alias and translation domain only. The field classes come from the host form theme
            // (twig.form_themes / FormKit css_framework): the profile no longer forces Bootstrap classes
            // (form-control, form-select, form-check-*, mb-2) on Tailwind / custom hosts.
            $seed['profiles'] = [
                'wiki' => [
                    'alias'              => 'wiki',
                    'translation_domain' => 'NowoWikiBundle',
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
