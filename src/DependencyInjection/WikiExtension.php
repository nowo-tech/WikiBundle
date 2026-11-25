<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\DependencyInjection;

use ArrayObject;
use LogicException;
use Nowo\BreadcrumbKitBundle\Event\BreadcrumbTrailBuiltEvent;
use Nowo\IconSelectorBundle\Emoji\EmojiCatalogInterface;
use Nowo\MilkdownEditorBundle\Comment\CommentAnchor;
use Nowo\MilkdownEditorBundle\Markdown\HostSchemes;
use Nowo\MilkdownEditorBundle\Markdown\MilkdownMarkdownRenderer;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\FilePreviewResolverInterface;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\HostFileResolverInterface;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\IconResolverInterface;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\UserResolverInterface;
use Nowo\MilkdownEditorBundle\ProfileRegistry;
use Nowo\MilkdownEditorBundle\Twig\MilkdownEditorTwig;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponseFormatterInterface;
use Nowo\WikiBundle\Ai\NullWikiAiAssistant;
use Nowo\WikiBundle\Ai\SymfonyAiWikiAssistant;
use Nowo\WikiBundle\Ai\Tool\WikiKnowledgeSearchTool;
use Nowo\WikiBundle\Ai\WikiContextRetriever;
use Nowo\WikiBundle\Api\Hook\DenyingWikiApiFiles;
use Nowo\WikiBundle\Api\Hook\DenyingWikiApiPlanGate;
use Nowo\WikiBundle\Api\Hook\DenyingWikiApiRateLimiter;
use Nowo\WikiBundle\Api\Hook\DenyingWikiApiWikiManagement;
use Nowo\WikiBundle\Api\Hook\NullWikiApiUsers;
use Nowo\WikiBundle\Api\Hook\WikiApiIdempotencyStoreInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiRateLimiterInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiUsersInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiWikiManagementInterface;
use Nowo\WikiBundle\Api\Idempotency\DoctrineWikiApiIdempotencyStore;
use Nowo\WikiBundle\Attachment\ConfiguredWikiAttachmentLimitsProvider;
use Nowo\WikiBundle\Attachment\LocalWikiAttachmentStorage;
use Nowo\WikiBundle\Attachment\NullWikiAttachmentScanner;
use Nowo\WikiBundle\Attachment\WikiAttachmentReferenceFilter;
use Nowo\WikiBundle\Attachment\WikiAttachmentScannerInterface;
use Nowo\WikiBundle\Attachment\WikiAttachmentStorageInterface;
use Nowo\WikiBundle\Avatar\NullWikiUserAvatarResolver;
use Nowo\WikiBundle\Avatar\WikiAvatars;
use Nowo\WikiBundle\Backlink\WikiBacklinks;
use Nowo\WikiBundle\Backlink\WikiPageReferenceIndex;
use Nowo\WikiBundle\Backlink\WikiPageReferenceListener;
use Nowo\WikiBundle\Collaboration\DoctrineWikiCollabStateStore;
use Nowo\WikiBundle\Collaboration\MercureWikiCollabPublisher;
use Nowo\WikiBundle\Collaboration\MercureWikiCollabSubscriberAuthorizer;
use Nowo\WikiBundle\Collaboration\WikiCollabCookieListener;
use Nowo\WikiBundle\Collaboration\WikiCollabPublisherInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabSaveListener;
use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Nowo\WikiBundle\Collaboration\WikiCollabStateStoreInterface;
use Nowo\WikiBundle\Command\WikiBacklinksRebuildCommand;
use Nowo\WikiBundle\Command\WikiQuickAccessEraseUserCommand;
use Nowo\WikiBundle\Command\WikiRecentsPurgeCommand;
use Nowo\WikiBundle\Command\WikiSearchBackfillTextCommand;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Comment\WikiCommentsPanel;
use Nowo\WikiBundle\Controller\WikiLinkPickerController;
use Nowo\WikiBundle\DependencyInjection\Compiler\MilkdownBridgePass;
use Nowo\WikiBundle\DependencyInjection\Compiler\PrivateEntityManagerPass;
use Nowo\WikiBundle\DependencyInjection\Compiler\WikiSearchKitPass;
use Nowo\WikiBundle\Doctrine\PrivateEntityManagerMiddleware;
use Nowo\WikiBundle\Doctrine\Type\WikiUserRefType;
use Nowo\WikiBundle\Doctrine\WikiEntityManager;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Doctrine\WikiExactKeyColumns;
use Nowo\WikiBundle\Doctrine\WikiManagedEntities;
use Nowo\WikiBundle\Doctrine\WikiMetadataListener;
use Nowo\WikiBundle\Doctrine\WikiSpaceCollationListener;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\History\WikiActorLabels;
use Nowo\WikiBundle\History\WikiAuthorLabel;
use Nowo\WikiBundle\History\WikiHistoryThinner;
use Nowo\WikiBundle\History\WikiHistoryTimeline;
use Nowo\WikiBundle\History\WikiRenderedRevisionDiff;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Link\WikiObjectLinkSourceInterface;
use Nowo\WikiBundle\LinkPicker\ConfiguredWikiLinkPickerSettings;
use Nowo\WikiBundle\LinkPicker\WikiLinkPickerEditorOptions;
use Nowo\WikiBundle\LinkPicker\WikiLinkPickerService;
use Nowo\WikiBundle\LinkPicker\WikiLinkPickerSettingsInterface;
use Nowo\WikiBundle\LinkPicker\WikiLinkTenantResolverInterface;
use Nowo\WikiBundle\LinkPicker\WikiTenantPathsLinkTenantResolver;
use Nowo\WikiBundle\Markdown\Milkdown\MilkdownWikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\Milkdown\WikiFilePreviewFrames;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownIconHydrator;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownPageResolver;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownRenderContext;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownRichContent;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownSectionResolver;
use Nowo\WikiBundle\Markdown\WikiMarkdownExtensionInterface;
use Nowo\WikiBundle\Markdown\WikiMarkdownReferenceExtractor;
use Nowo\WikiBundle\Markdown\WikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface;
use Nowo\WikiBundle\Markdown\WikiRenderContext;
use Nowo\WikiBundle\Member\NullWikiMemberBadgeProvider;
use Nowo\WikiBundle\Member\WikiMemberBadgeProviderInterface;
use Nowo\WikiBundle\Member\WikiMemberBadges;
use Nowo\WikiBundle\PageFile\WikiPageFileExtractor;
use Nowo\WikiBundle\Panel\SessionWikiPanelStateStore;
use Nowo\WikiBundle\Panel\WikiTreeRail;
use Nowo\WikiBundle\Port\Internal\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Port\Internal\WikiAiAssistantInterface;
use Nowo\WikiBundle\Port\Internal\WikiApiAuthenticatorInterface;
use Nowo\WikiBundle\Port\Internal\WikiApiDirectoryInterface;
use Nowo\WikiBundle\Port\Internal\WikiApiFilesInterface;
use Nowo\WikiBundle\Port\Internal\WikiApiPlanGateInterface;
use Nowo\WikiBundle\Port\Internal\WikiAttachmentLimitsProviderInterface;
use Nowo\WikiBundle\Port\Internal\WikiCollabSubscriberAuthorizerInterface;
use Nowo\WikiBundle\Port\Internal\WikiPanelStateStoreInterface;
use Nowo\WikiBundle\Port\Internal\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Port\Internal\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Port\Internal\WikiSpaceLocatorInterface;
use Nowo\WikiBundle\Port\Internal\WikiTeamMembershipResolverInterface;
use Nowo\WikiBundle\Port\Internal\WikiTreeStateStoreInterface;
use Nowo\WikiBundle\Port\Internal\WikiUserAvatarResolverInterface;
use Nowo\WikiBundle\Provenance\NullWikiWriteProvenanceProvider;
use Nowo\WikiBundle\Provenance\WikiProvenanceAuthorErasedListener;
use Nowo\WikiBundle\Provenance\WikiWriteProvenanceErasure;
use Nowo\WikiBundle\Provenance\WikiWriteProvenanceProviderInterface;
use Nowo\WikiBundle\Query\WikiPageQuery;
use Nowo\WikiBundle\Query\WikiPageQueryInterface;
use Nowo\WikiBundle\Query\WikiSpaceQuery;
use Nowo\WikiBundle\Query\WikiSpaceQueryInterface;
use Nowo\WikiBundle\QuickAccess\WikiQuickAccess;
use Nowo\WikiBundle\QuickAccess\WikiQuickAccessErasureListener;
use Nowo\WikiBundle\QuickAccess\WikiQuickAccessStore;
use Nowo\WikiBundle\QuickAccess\WikiReadableSpaces;
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
use Nowo\WikiBundle\Repository\WikiPageProvenanceFinderInterface;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiRevisionLabelRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiSpaceBatchFinderInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiTrashRepositoryInterface;
use Nowo\WikiBundle\Routing\WikiRouteLoader;
use Nowo\WikiBundle\Routing\WikiTenantPaths;
use Nowo\WikiBundle\Routing\WikiTenantUrlGenerator;
use Nowo\WikiBundle\Search\SearchKit\WikiPageSearchDefinition;
use Nowo\WikiBundle\Search\SearchKit\WikiPageSearchKitProvider;
use Nowo\WikiBundle\Search\SearchKit\WikiSearchKitAvailability;
use Nowo\WikiBundle\Search\UserProviderWikiSearchViewerResolver;
use Nowo\WikiBundle\Search\WikiReadablePageFinder;
use Nowo\WikiBundle\Search\WikiSearchScopeListener;
use Nowo\WikiBundle\Search\WikiSearchTenantResolverInterface;
use Nowo\WikiBundle\Search\WikiSearchTenantsResolverAdapter;
use Nowo\WikiBundle\Search\WikiSearchTenantsResolverInterface;
use Nowo\WikiBundle\Search\WikiSearchViewerResolverInterface;
use Nowo\WikiBundle\Search\WikiTenantPathsSearchTenantResolver;
use Nowo\WikiBundle\Security\AllowAllWikiAccessChecker;
use Nowo\WikiBundle\Security\ConfigurableWikiAccessChecker;
use Nowo\WikiBundle\Security\LegacyWikiSpaceAccessChecker;
use Nowo\WikiBundle\Security\NullWikiTeamMembershipResolver;
use Nowo\WikiBundle\Security\WikiArchivedSpaceAccessChecker;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;
use Nowo\WikiBundle\Security\WikiPageAccess;
use Nowo\WikiBundle\Security\WikiPageAccessInterface;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Service\AccessibleWikiSpaceLocator;
use Nowo\WikiBundle\Service\WikiEventDeferral;
use Nowo\WikiBundle\Service\WikiPageDuplicator;
use Nowo\WikiBundle\Service\WikiPageIconService;
use Nowo\WikiBundle\Service\WikiPageMover;
use Nowo\WikiBundle\Service\WikiPageServiceInterface;
use Nowo\WikiBundle\Service\WikiRequestBoundaryResetter;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolver;
use Nowo\WikiBundle\Space\WikiSpaceLifecycle;
use Nowo\WikiBundle\Space\WikiSpaceLifecycleInterface;
use Nowo\WikiBundle\Space\WikiSpacePurger;
use Nowo\WikiBundle\Transaction\WikiConflictAnswer;
use Nowo\WikiBundle\Transaction\WikiStorageFailureConflictFormatter;
use Nowo\WikiBundle\Transaction\WikiStorageFailureSubscriber;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use Nowo\WikiBundle\Trash\WikiTrashService;
use Nowo\WikiBundle\Tree\SessionWikiTreeStateStore;
use Nowo\WikiBundle\Ui\WikiBreadcrumbParentLabel;
use Nowo\WikiBundle\Ui\WikiPageFactory;
use Nowo\WikiBundle\Ui\WikiPages;
use Nowo\WikiBundle\User\WikiUserClassPolicy;
use Nowo\WikiBundle\User\WikiUserDirectoryInterface;
use Nowo\WikiBundle\User\WikiUserReferenceResolverInterface;
use Nowo\WikiBundle\User\WikiUsers;
use Nowo\WikiBundle\Util\WikiSlugger;
use Nowo\WikiBundle\Util\WikiSpaceSlugRule;
use Nowo\WikiBundle\ValueObject\WikiSpaceIcon;
use Symfony\AI\Agent\AgentInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
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
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function class_exists;
use function in_array;
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
        // 1.45: favourites, recents and backlinks; their tables (and the favourite route) exist only when they are on.
        $config['favourites']['enabled']    = $this->literalBool($config['favourites']['enabled'], 'favourites.enabled');
        $config['recents']['enabled']       = $this->literalBool($config['recents']['enabled'], 'recents.enabled');
        $config['backlinks']['enabled']     = $this->literalBool($config['backlinks']['enabled'], 'backlinks.enabled');
        $config['history']['rendered_diff'] = $this->literalBool($config['history']['rendered_diff'], 'history.rendered_diff');
        if (!$config['favourites']['enabled']) {
            unset($config['routes']['page_favourite'], $config['routes']['favourite_remove']);
        }
        // 1.14 (T065): the trash; its table and routes exist only when it is on.
        $config['trash']['enabled'] = $this->literalBool($config['trash']['enabled'], 'trash.enabled');
        if (!$config['trash']['enabled']) {
            unset($config['routes']['page_delete'], $config['routes']['space_trash'], $config['routes']['trash_restore'], $config['routes']['trash_purge']);
        }
        // 1.16: comments; their tables and routes exist only when they are on.
        $config['comments']['enabled']          = $this->literalBool($config['comments']['enabled'], 'comments.enabled');
        $config['comments']['read_view_source'] = $this->literalBool($config['comments']['read_view_source'], 'comments.read_view_source');
        // 1.29: the tree rail shares the panel state endpoint (and store) with the comments panel.
        $config['web_ui']['tree_rail'] = $this->literalBool($config['web_ui']['tree_rail'], 'web_ui.tree_rail');
        if (!$config['comments']['enabled']) {
            unset($config['routes']['page_comments']);
            if (!$config['web_ui']['tree_rail']) {
                unset($config['routes']['space_panel_state']);
            }
        }
        // 1.40: the link picker routes exist only with the picker on (editor.type milkdown, checked by registerLinkPicker()).
        $config['editor']['milkdown']['link_picker']['enabled'] = $this->literalBool($config['editor']['milkdown']['link_picker']['enabled'], 'editor.milkdown.link_picker.enabled');
        if (!$config['editor']['milkdown']['link_picker']['enabled']) {
            unset($config['routes']['link_candidates'], $config['routes']['link_sections']);
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
        WikiDoctrineMapping::check($container, $database);

        $container->setParameter('nowo_wiki.user_class', $config['user_class']);
        $container->setParameter('nowo_wiki.accepted_user_classes', $config['accepted_user_classes']);
        $container->setParameter('nowo_wiki.spaces_table', $spacesTable);
        $container->setParameter('nowo_wiki.pages_table', $pagesTable);
        $container->setParameter('nowo_wiki.revisions_table', $revisionsTable);
        $container->setParameter('nowo_wiki.database', $database);
        $container->setParameter('nowo_wiki.database.mapping_type', $database['mapping_type']);
        $container->setParameter('nowo_wiki.database.user_reference', $database['user_reference']);
        // 2.0: always true (the private entity manager is the only mode); kept for hosts that read it.
        $container->setParameter('nowo_wiki.database.private_entity_manager', true);
        $container->setParameter('nowo_wiki.user_directory', $config['user_directory']);
        // 1.36 (database.user_reference: id): the host's user directory, when configured.
        if (is_string($config['user_directory']) && $config['user_directory'] !== '') {
            $container->setAlias(WikiUserDirectoryInterface::class, $config['user_directory']);
        }
        $container->setParameter('nowo_wiki.space_scope', $config['space_scope']);
        $container->setParameter('nowo_wiki.route_prefix', self::pathPrefix($config));
        $container->setParameter('nowo_wiki.dashboard_route', $config['dashboard_route']);
        $container->setParameter('nowo_wiki.routes', $config['routes']);
        $container->setParameter('nowo_wiki.templates', self::templates($config));
        $container->setParameter('nowo_wiki.security.allow_unauthenticated', $config['security']['allow_unauthenticated']);
        $container->setParameter('nowo_wiki.editor', $config['editor']);
        $container->setParameter('nowo_wiki.editor.type', $config['editor']['type']);
        $container->setParameter('nowo_wiki.ai', $config['ai']);
        $container->setParameter('nowo_wiki.import_export', $config['import_export']);
        $container->setParameter('nowo_wiki.pagination', $config['pagination']);
        $container->setParameter('nowo_wiki.space_icon.allowed_prefixes', array_values($config['space_icon']['allowed_prefixes']));
        $container->setParameter('nowo_wiki.page_icon.form_field', $config['page_icon']['form_field']);
        $container->setParameter('nowo_wiki.icon_picker', self::iconPicker($config));
        $container->setParameter('nowo_wiki.space_icon.accepted_prefixes', self::iconPicker($config)['space']['prefixes']);
        $container->setParameter('nowo_wiki.space_icon.picker', self::iconPicker($config)['space']['picker']);
        $container->setParameter('nowo_wiki.page_icon.accepted_prefixes', self::iconPicker($config)['page']['prefixes']);
        $container->setParameter('nowo_wiki.tree.context_menu', $config['tree']['enabled'] && $this->literalBool($config['tree']['context_menu'], 'tree.context_menu'));
        $container->setParameter('nowo_wiki.attachments', $config['attachments']);
        $container->setParameter('nowo_wiki.tenant_paths', $config['tenant_paths']);
        $container->setParameter('nowo_wiki.import_export.enabled', (bool) $config['import_export']['enabled']);
        $container->setParameter('nowo_wiki.import_export.max_document_bytes', $config['import_export']['max_document_bytes']);
        $templates = self::templates($config);
        $container->setParameter('nowo_wiki.web_ui', [
            'enabled'         => (bool) $config['web_ui']['enabled'],
            'layout_template' => $templates['layout'],
            'path_prefix'     => self::pathPrefix($config),
            'templates'       => array_diff_key($templates, ['layout' => true]),
            // 1.21: read by the page templates through the Twig global nowo_wiki_web_ui.
            'space_index' => (bool) $config['web_ui']['space_index'],
            // 1.38: the nested page tree's default fold (tree.default_expanded), read by _page_tree.html.twig.
            'tree_default_expanded' => $config['tree']['default_expanded'],
        ] + self::ui($config['web_ui']));
        // 1.21 (audit GV-L1): the Twig global. A service, not %nowo_wiki.web_ui%: TwigExtension resolves a parameter while it
        // loads, so with TwigBundle registered first the global would hold the prepend() defaults, not this config.
        $container->setDefinition(self::WEB_UI_GLOBAL, new Definition(ArrayObject::class, ['%nowo_wiki.web_ui%']));
        $container->setParameter('nowo_wiki.search.scope', $config['search']['scope']);
        $container->setParameter('nowo_wiki.web_ui.enabled', (bool) $config['web_ui']['enabled']);
        // 1.29: the page layout options (controllers: tree rail, title dedupe).
        $container->setParameter('nowo_wiki.web_ui.layout', self::layout($config['web_ui']));
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

        $container->setParameter('nowo_wiki.database.entity_manager', $emName);
        // REQ-ARCH-001 rule 6 (2.0: the only mode): every wiki row is read and written through the wiki's private entity
        // manager, on the connection, mapping, listeners and SQL filters of database.entity_manager; a wiki write never
        // flushes, clears or closes the application's manager.
        $container->setDefinition(WikiEntityManager::class, (new Definition(WikiEntityManager::class))
            ->setArguments([new Reference('doctrine'), $emName, new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)])
            ->addTag('kernel.reset', ['method' => 'reset'])
            ->addTag('monolog.logger', ['channel' => 'nowo_wiki'])
            // Loads of the private manager inside a transaction are tracked like its writes (audit NJV-1).
            ->addTag('doctrine.event_listener', ['event' => 'postLoad', 'method' => 'postLoad', 'lazy' => true, 'entity_manager' => $emName]));
        if (PrivateEntityManagerPass::supportsRollbackMiddleware()) {
            $container->setDefinition(PrivateEntityManagerMiddleware::class, (new Definition(PrivateEntityManagerMiddleware::class))
                ->setArguments([new Reference(WikiEntityManager::class)])
                ->addTag('doctrine.middleware'));
        }
        $container->setDefinition(WikiEntityManagerProvider::class, (new Definition(WikiEntityManagerProvider::class))
            ->setArgument('$registry', new Reference('doctrine'))
            ->setArgument('$managerName', $emName)
            ->setArgument('$private', new Reference(WikiEntityManager::class)));
        $emProviderRef = new Reference(WikiEntityManagerProvider::class);
        // 1.37 (Beacon spec 239): the runner of every wiki write, injected by its class (never a runner the application
        // binds to TransactionRunnerInterface), and the 503 / 409 answers of the wiki's routes.
        $container->setDefinition(WikiTransactionRunner::class, (new Definition(WikiTransactionRunner::class))
            ->setArgument('$entityManager', $emProviderRef));
        $container->setAlias(WikiTransactionRunner::SERVICE_ID, WikiTransactionRunner::class);
        $container->setDefinition(WikiStorageFailureSubscriber::class, (new Definition(WikiStorageFailureSubscriber::class))
            // The shared policy (409 by default); absent when the host did not register nowo-tech/transaction-doctrine's bundle (the subscriber then uses 409 + 1 s).
            ->setArguments(['$policy' => new Reference('nowo_transaction_doctrine.conflict_response_policy', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->addTag('kernel.event_subscriber'));
        // 1.45: the rule 6 answer (CONFLICT_001 / CONFLICT_002) of the routes added in 1.45.
        $container->setDefinition(WikiConflictAnswer::class, (new Definition(WikiConflictAnswer::class))
            ->setArguments(['$policy' => new Reference('nowo_transaction_doctrine.conflict_response_policy', ContainerInterface::NULL_ON_INVALID_REFERENCE)]));
        if (interface_exists(ConflictResponseFormatterInterface::class)) {
            $container->setDefinition(WikiStorageFailureConflictFormatter::class, (new Definition(WikiStorageFailureConflictFormatter::class))
                ->addTag(ConflictResponseFormatterInterface::TAG));
        }

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
        // 1.55.2: the batch / no-refresh space reads (host access checkers) and the wiki manager's instance of a space or
        // page another manager loaded (deprecated host ports with the private manager).
        $container->setAlias(WikiSpaceBatchFinderInterface::class, DoctrineOrmWikiSpaceRepository::class);
        $container->setDefinition(WikiManagedEntities::class, (new Definition(WikiManagedEntities::class))
            ->setAutowired(false)
            ->setArgument('$entityManager', $emProviderRef));

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
            ->setArgument('$userReference', $database['user_reference'])
            ->setArgument('$quickAccessTablePrefix', $prefix)
            ->setArgument('$favouritesEnabled', $config['favourites']['enabled'])
            ->setArgument('$recentsEnabled', $config['recents']['enabled'])
            ->setArgument('$backlinksEnabled', $config['backlinks']['enabled'])
            ->addTag('doctrine.event_listener', ['event' => 'loadClassMetadata'])
            ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema']));

        // REQ-SEC-006: the bundle's own declaration of its identity / access-key columns, built from the same
        // configuration the schema is. Autoconfigured, so MigrationsKit tags it
        // (nowo:migrations:check-key-collations) with no registration by the host; the columns of a feature that is
        // off, and the user id columns of the `entity` user reference, are declared inactive rather than omitted.
        $container->setDefinition(WikiExactKeyColumns::class, (new Definition(WikiExactKeyColumns::class))
            ->setAutowired(false)
            ->setArgument('$spacesTableName', $spacesTable)
            ->setArgument('$revisionsTableName', $revisionsTable)
            ->setArgument('$linksTableName', $prefix . '_page_links')
            ->setArgument('$attachmentsTableName', $prefix . '_attachments')
            ->setArgument('$commentTablePrefix', $prefix)
            ->setArgument('$quickAccessTablePrefix', $prefix)
            ->setArgument('$userIds', $database['user_reference'] === WikiMetadataListener::USER_REFERENCE_ID)
            ->setArgument('$comments', $config['comments']['enabled'])
            ->setArgument('$favourites', $config['favourites']['enabled'])
            ->setArgument('$recents', $config['recents']['enabled'])
            ->setArgument('$backlinks', $config['backlinks']['enabled'])
            ->setAutoconfigured(true));

        // 1.31.1 (house rule LQ): binary NO PAD collation on wiki_spaces.owner_scope_id on MySQL / MariaDB; 1.49.1 (audit XI-2)
        // on postGenerateSchema, once every foreign key exists.
        $container->setDefinition(WikiSpaceCollationListener::class, (new Definition(WikiSpaceCollationListener::class))
            ->setArgument('$entityManager', $emProviderRef)
            ->setArgument('$logger', new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE))
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
        $this->registerPanelState($container, $config);
        $this->registerComments($container, $config, $emProviderRef);
        $this->registerLinkPicker($container, $config);
        $this->registerQuickAccess($container, $config);
        $this->registerBacklinks($container, $config);
        $this->registerRenderedDiff($container, $config);

        $container->getDefinition(WikiSearchService::class)->setArgument('$entityManager', $emProviderRef);
        // 1.16: wiki:search:backfill-text fills the revisions' plain-text column.
        $container->getDefinition(WikiSearchBackfillTextCommand::class)->setArgument('$entityManager', $emProviderRef);

        $this->registerAiServices($container, $config['ai']);
        $this->registerApi($container, $loader, $config);
        $this->registerSpaceLifecycle($container, $config);
        $this->registerReadModel($container, $config);
        $this->registerSearchKitProvider($container, $config);
        $this->registerRequestBoundary($container);
        $this->registerMemberBadgesAndProvenance($container, $emProviderRef, $config);
    }

    /**
     * 1.44 (Beacon KIT-85), 1.46 (SearchKit provider base): the optional SearchKit group (`search_kit_provider`),
     * registered only when it is on and SearchKitBundle >= 1.10.1 is installed: the read-rule port
     * {@see WikiReadablePageFinder} (framework-free), the viewer resolver, {@see WikiPageSearchDefinition} (tagged for
     * SearchKit's EntitySearchPass, which builds its provider) and {@see WikiPageSearchKitProvider}, tagged as a SearchKit
     * provider and reference resolver ({@see WikiSearchKitPass} hands it SearchKit's provider).
     *
     * @param array<string, mixed> $config processed configuration
     */
    private function registerSearchKitProvider(ContainerBuilder $container, array $config): void
    {
        $options = $config['search_kit_provider'];
        if (!$this->literalBool($options['enabled'], 'search_kit_provider.enabled') || !WikiSearchKitAvailability::isAvailable()) {
            return;
        }
        $sensitive = $this->literalBool($options['sensitive'], 'search_kit_provider.sensitive');

        $container->setDefinition(WikiReadablePageFinder::class, (new Definition(WikiReadablePageFinder::class))
            ->setArguments([
                '$roles'      => new Reference(WikiAccessCheckerInterface::class),
                '$spaces'     => new Reference(WikiSpaceAccessResolverInterface::class),
                '$checker'    => new Reference(WikiSpaceAccessCheckerInterface::class),
                '$search'     => new Reference(WikiSearchService::class),
                '$pages'      => new Reference(WikiPageRepositoryInterface::class),
                '$renderer'   => new Reference(WikiMarkdownRendererInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                '$candidates' => $options['candidates'],
                '$scopeMode'  => $options['scope'],
            ]));

        $viewerResolver = $options['viewer_resolver'];
        if (!is_string($viewerResolver) || $viewerResolver === '') {
            $viewerResolver = UserProviderWikiSearchViewerResolver::class;
            $container->setDefinition($viewerResolver, (new Definition(UserProviderWikiSearchViewerResolver::class))
                ->setArgument('$users', new Reference((string) $options['user_provider'], ContainerInterface::NULL_ON_INVALID_REFERENCE)));
        }
        $container->setAlias(WikiSearchViewerResolverInterface::class, $viewerResolver);

        // The tenant key of each space in the spaces event: the link picker's port (`tenant_paths` by default).
        if (!$container->hasAlias(WikiLinkTenantResolverInterface::class) && !$container->hasDefinition(WikiLinkTenantResolverInterface::class)) {
            $container->setDefinition(WikiTenantPathsLinkTenantResolver::class, (new Definition(WikiTenantPathsLinkTenantResolver::class))
                ->setArgument('$tenantPaths', new Reference(WikiTenantPaths::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)));
            $container->setAlias(WikiLinkTenantResolverInterface::class, WikiTenantPathsLinkTenantResolver::class);
        }

        $tenantResolver = $options['tenant_resolver'];
        if (!is_string($tenantResolver) || $tenantResolver === '') {
            $tenantResolver = WikiTenantPathsSearchTenantResolver::class;
            $container->setDefinition($tenantResolver, (new Definition(WikiTenantPathsSearchTenantResolver::class))
                ->setArgument('$tenantPaths', new Reference(WikiTenantPaths::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)));
        }
        // 1.47: the service may implement the single-key port (1.46) or the list port; the definition reads either.
        $container->setAlias(WikiSearchTenantResolverInterface::class, $tenantResolver);
        // 1.47.1 (audit VN2-2): the list interface is the configured service only through an adapter, so that autowiring it
        // never hands a single-key resolver where a list is declared.
        $container->setDefinition(WikiSearchTenantsResolverAdapter::class, (new Definition(WikiSearchTenantsResolverAdapter::class))
            ->setArgument('$inner', new Reference($tenantResolver)));
        $container->setAlias(WikiSearchTenantsResolverInterface::class, WikiSearchTenantsResolverAdapter::class);

        $pageView = $config['routes']['page_view']['name'];
        $container->setDefinition(WikiPageSearchDefinition::class, (new Definition(WikiPageSearchDefinition::class))
            ->setArguments([
                '$finder'               => new Reference(WikiReadablePageFinder::class),
                '$viewers'              => new Reference(WikiSearchViewerResolverInterface::class),
                '$checker'              => new Reference(WikiSpaceAccessCheckerInterface::class),
                '$search'               => new Reference(WikiSearchService::class),
                '$entityManager'        => new Reference(WikiEntityManagerProvider::class),
                '$urls'                 => new Reference('router'),
                '$translator'           => new Reference('translator'),
                '$pageViewRoute'        => $pageView,
                '$pageViewRoutes'       => array_values(array_map(static fn (array $tenant): string => $tenant['name_prefix'] . $pageView, $config['tenant_paths'])),
                '$searchRoute'          => $config['routes']['search']['name'] ?? null,
                '$globalSearchPage'     => $config['search']['scope'] === 'all',
                '$excludedSearchSpaces' => array_values($config['search']['excluded_spaces']),
                '$scopeMode'            => $options['scope'],
                '$candidates'           => $options['candidates'],
                '$icon'                 => $options['icon'],
                '$sensitive'            => $sensitive,
                '$renderer'             => new Reference(WikiMarkdownRendererInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                '$dispatcher'           => new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                '$tenants'              => new Reference(WikiLinkTenantResolverInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                '$tenantMode'           => $options['tenant'],
                '$currentTenants'       => new Reference($tenantResolver),
            ])
            ->addTag(WikiSearchKitAvailability::DEFINITION_TAG, ['priority' => $options['priority']]));

        $container->setDefinition(WikiPageSearchKitProvider::class, (new Definition(WikiPageSearchKitProvider::class))
            ->setArguments([
                // Set by WikiSearchKitPass to the provider SearchKit builds for the definition.
                '$provider'   => null,
                '$definition' => new Reference(WikiPageSearchDefinition::class),
            ])
            ->addTag(WikiSearchKitPass::PROVIDER_TAG, ['priority' => $options['priority']])
            ->addTag(WikiSearchKitPass::REFERENCE_RESOLVER_TAG, ['priority' => $options['priority']]));
    }

    /**
     * Spec 234: the member badge hook (a null provider by alias; the host aliases
     * {@see WikiMemberBadgeProviderInterface}), its per-request memo (emptied at every boundary and on `kernel.reset`),
     * read by the history timeline and the comments; and the write provenance hook (a null provider by alias; the host
     * aliases {@see WikiWriteProvenanceProviderInterface}), its erasure service and the API's provenance finder.
     */
    /**
     * @param array<string, mixed> $config processed configuration
     */
    private function registerMemberBadgesAndProvenance(ContainerBuilder $container, Reference $entityManager, array $config): void
    {
        $container->setDefinition(NullWikiMemberBadgeProvider::class, new Definition(NullWikiMemberBadgeProvider::class));
        $container->setAlias(WikiMemberBadgeProviderInterface::class, NullWikiMemberBadgeProvider::class);
        $container->setDefinition(WikiMemberBadges::class, (new Definition(WikiMemberBadges::class))
            ->setArgument('$provider', new Reference(WikiMemberBadgeProviderInterface::class))
            // 1.36.0: stored id references (database.user_reference: id) are members of user_class.
            ->setArgument('$userClass', $config['user_class'])
            ->addTag('kernel.reset', ['method' => 'reset']));
        foreach ([WikiHistoryTimeline::class, WikiCommentService::class] as $reader) {
            if ($container->hasDefinition($reader)) {
                $container->getDefinition($reader)->setArgument('$badges', new Reference(WikiMemberBadges::class));
            }
        }

        $container->setDefinition(NullWikiWriteProvenanceProvider::class, new Definition(NullWikiWriteProvenanceProvider::class));
        $container->setAlias(WikiWriteProvenanceProviderInterface::class, NullWikiWriteProvenanceProvider::class);
        $container->setDefinition(WikiWriteProvenanceErasure::class, (new Definition(WikiWriteProvenanceErasure::class))
            ->setArgument('$entityManager', $entityManager)
            ->setArgument('$users', new Reference(WikiUserReferenceResolverInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setPublic(true));
        // Audit PQ-D1-I2: the service export of a comment author carries their actor labels.
        if ($container->hasDefinition(WikiCommentService::class)) {
            $container->getDefinition(WikiCommentService::class)->setArgument('$provenance', new Reference(WikiWriteProvenanceErasure::class));
        }
        // Audit PQ-L1: the comment author erasure erases the user's actor labels (and the orphaned ones) too.
        $container->setDefinition(WikiProvenanceAuthorErasedListener::class, (new Definition(WikiProvenanceAuthorErasedListener::class))
            ->setArgument('$erasure', new Reference(WikiWriteProvenanceErasure::class))
            ->addTag('kernel.event_listener', ['event' => WikiEvents::COMMENT_AUTHOR_ERASED]));
        $container->setAlias(WikiPageProvenanceFinderInterface::class, DoctrineOrmWikiPageRevisionRepository::class);

        // Audit PQ-M1: actor labels only beside real names (history.show_actor_labels, default history.show_names).
        /** @var array{show_names: bool, show_actor_labels: bool|null} $history */
        $history = $config['history'];
        $container->setDefinition(WikiActorLabels::class, (new Definition(WikiActorLabels::class))
            ->setArgument('$authors', new Reference(WikiAuthorLabel::class))
            ->setArgument('$show', $history['show_actor_labels'] ?? $history['show_names'])
            ->setArgument('$classes', new Reference(WikiUserClassPolicy::class)));
        if ($container->hasDefinition(WikiHistoryTimeline::class)) {
            $container->getDefinition(WikiHistoryTimeline::class)->setArgument('$actorLabels', new Reference(WikiActorLabels::class));
        }
    }

    /**
     * 1.29.1 (REQ-RUNTIME-002): the request memos are emptied at every request, console and Messenger boundary, not only
     * by `kernel.reset` (when the services resetter does not run). Only services already built are reset
     * (`IGNORE_ON_UNINITIALIZED_REFERENCE`, as the services resetter does): a boundary never instantiates a memo, and
     * services this configuration does not register (file previews off, a host checker) are skipped.
     */
    private function registerRequestBoundary(ContainerBuilder $container): void
    {
        $memos = array_map(
            static fn (string $id): Reference => new Reference($id, ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE),
            [LegacyWikiSpaceAccessChecker::class, WikiAvatars::class, WikiFilePreviewFrames::class, WikiRenderContext::class, WikiEventDeferral::class, WikiUsers::class, WikiMemberBadges::class,
                // The private entity manager last, after the memos that may still hold its rows.
                WikiEntityManager::class],
        );
        $container->setDefinition(WikiRequestBoundaryResetter::class, (new Definition(WikiRequestBoundaryResetter::class))
            ->setArgument('$memos', new IteratorArgument($memos))
            ->addTag('kernel.event_subscriber'));
    }

    /**
     * 1.24 (spec 218): `api.*`. With `enabled: false` nothing is registered: no route, service, listener or parameter.
     *
     * @param array<string, mixed> $config processed configuration
     */
    private function registerApi(ContainerBuilder $container, YamlFileLoader $loader, array $config): void
    {
        /** @var array{enabled: bool, prefix: string, quota_listener_required: bool, max_body_bytes: int, search_candidates: int, hooks: array<string, string|null>} $api */
        $api = $config['api'];
        if (!$api['enabled']) {
            return;
        }
        foreach (['authenticator' => 'WikiApiAuthenticatorInterface', 'directory' => 'WikiApiDirectoryInterface'] as $hook => $interface) {
            if (!is_string($api['hooks'][$hook]) || $api['hooks'][$hook] === '') {
                throw new LogicException(sprintf('nowo_wiki.api.enabled needs api.hooks.%s: the service id of a Nowo\\WikiBundle\\Api\\Hook\\%s (the host keeps the tokens and the wikis).', $hook, $interface));
            }
        }
        if ($config['security']['allow_unauthenticated']) {
            throw new LogicException('nowo_wiki.api.enabled cannot be used with security.allow_unauthenticated: every wiki would be open to every token.');
        }

        $container->setParameter('nowo_wiki.api.prefix', $api['prefix']);
        $container->setParameter('nowo_wiki.api.quota_listener_required', $api['quota_listener_required']);
        $container->setParameter('nowo_wiki.api.max_body_bytes', $api['max_body_bytes']);
        $container->setParameter('nowo_wiki.api.search_candidates', $api['search_candidates']);
        $loader->load('services_api.yaml');
        $container->setAlias(WikiApiAuthenticatorInterface::class, (string) $api['hooks']['authenticator']);
        $container->setAlias(WikiApiDirectoryInterface::class, (string) $api['hooks']['directory']);
        $container->setAlias(WikiApiRateLimiterInterface::class, $this->serviceOr($api['hooks']['rate_limiter'], DenyingWikiApiRateLimiter::class));
        $container->setAlias(WikiApiPlanGateInterface::class, $this->serviceOr($api['hooks']['plan_gate'], DenyingWikiApiPlanGate::class));
        $container->setAlias(WikiApiUsersInterface::class, $this->serviceOr($api['hooks']['users'], NullWikiApiUsers::class));
        $container->setAlias(WikiApiWikiManagementInterface::class, $this->serviceOr($api['hooks']['wiki_management'], DenyingWikiApiWikiManagement::class));
        $container->setAlias(WikiApiFilesInterface::class, $this->serviceOr($api['hooks']['files'], DenyingWikiApiFiles::class));
        $container->getDefinition(WikiRouteLoader::class)->setArgument('$apiPrefix', $api['prefix']);

        $table = rtrim((string) $config['table_prefix'], '_') . '_api_idempotency';
        if (is_string($api['hooks']['idempotency_store']) && $api['hooks']['idempotency_store'] !== '') {
            $container->setAlias(WikiApiIdempotencyStoreInterface::class, $api['hooks']['idempotency_store']);
        } else {
            $container->setDefinition(DoctrineWikiApiIdempotencyStore::class, (new Definition(DoctrineWikiApiIdempotencyStore::class))
                ->setArgument('$entityManager', new Reference(WikiEntityManagerProvider::class))
                ->setArgument('$table', $table));
            $container->setAlias(WikiApiIdempotencyStoreInterface::class, DoctrineWikiApiIdempotencyStore::class);
            $container->getDefinition(WikiMetadataListener::class)
                ->setArgument('$apiIdempotencyTableName', $table)
                ->setArgument('$apiIdempotencyEnabled', true);
        }
    }

    private function serviceOr(?string $id, string $default): string
    {
        return is_string($id) && $id !== '' ? $id : $default;
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

        // 1.25: host file references (Milkdown's host_schemes / host_url_patterns) resolve with the render's frame.
        $container->setDefinition(WikiMilkdownRenderContext::class, (new Definition(WikiMilkdownRenderContext::class))
            ->setArgument('$context', new Reference(WikiRenderContext::class)));

        // 1.28 (spec 212 B): the request's preview frames, kept past the sanitizer (WikiHtmlTwigExtension puts them back).
        $filePreviews = ($config['editor']['milkdown']['file_previews'] ?? true) === true
            && ($config['editor']['milkdown']['file_cards'] ?? Configuration::FILE_CARDS_LINK) === Configuration::FILE_CARDS_CARD;
        if ($filePreviews) {
            $container->setDefinition(WikiFilePreviewFrames::class, (new Definition(WikiFilePreviewFrames::class))
                ->addTag('kernel.reset', ['method' => 'reset']));
        }

        // 1.40 (owner O3): `Title › Section text` labels of page links, for pages resolved for the viewer in this render.
        $container->setDefinition(WikiMilkdownSectionResolver::class, (new Definition(WikiMilkdownSectionResolver::class))
            ->setArgument('$pages', new Reference(WikiPageRepositoryInterface::class))
            ->setArgument('$renderContext', new Reference(WikiRenderContext::class)));

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
            ->setArgument('$hostSchemes', new Reference(HostSchemes::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$hostFiles', new Reference(HostFileResolverInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$renderContext', new Reference(WikiMilkdownRenderContext::class))
            // 1.25 (WK-8): ::file cards as Milkdown's file card in the per-viewer render.
            ->setArgument('$fileCards', ($config['editor']['milkdown']['file_cards'] ?? Configuration::FILE_CARDS_LINK) === Configuration::FILE_CARDS_CARD)
            // 1.28 (spec 212 B): previews of view="preview" cards through the host's resolver (file_cards: card only).
            ->setArgument('$filePreviews', $filePreviews ? new Reference(FilePreviewResolverInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE) : null)
            ->setArgument('$previewFrames', $filePreviews ? new Reference(WikiFilePreviewFrames::class) : null)
            ->setArgument('$sections', new Reference(WikiMilkdownSectionResolver::class))
            ->addTag('monolog.logger', ['channel' => 'nowo_wiki'])
            // 1.25: MilkdownBridgePass drops the host file arguments when editor.milkdown.host_files says so.
            ->addTag(MilkdownBridgePass::HOST_FILES_TAG, ['mode' => $config['editor']['milkdown']['host_files'] ?? Configuration::HOST_FILES_AUTO]));
        $container->setAlias(WikiMarkdownRendererInterface::class, MilkdownWikiMarkdownRenderer::class);

        // HTML revisions opened in the editor, exported or converted by wiki:convert-markdown are written in the dialect.
        $container->getDefinition(WikiMarkdownConverter::class)->setArgument('$dialect', true);
        $container->setDefinition(WikiMilkdownIconHydrator::class, (new Definition(WikiMilkdownIconHydrator::class))
            ->setArgument('$icons', new Reference(IconResolverInterface::class)));
        $container->setDefinition(WikiMilkdownRichContent::class, (new Definition(WikiMilkdownRichContent::class))
            ->setArgument('$twig', new Reference(MilkdownEditorTwig::class)));
    }

    /**
     * 1.45: favourites (`favourites.enabled`) and recently viewed pages (`recents.enabled`): the store, the per-viewer
     * service the page view and the favourite routes use, the erasure and purge commands.
     *
     * @param array<string, mixed> $config processed configuration
     */
    private function registerQuickAccess(ContainerBuilder $container, array $config): void
    {
        /** @var array{enabled: bool, max: int, shown: int} $favourites */
        $favourites = $config['favourites'];
        /** @var array{enabled: bool, keep: int, shown: int, retention_days: int} $recents */
        $recents = $config['recents'];
        $runner  = new Reference(WikiTransactionRunner::class);
        // Always (audit VC-3): the store, the erasure command and listener, the purge command; they act on the tables
        // that exist, whether or not their feature is on now.
        $container->setDefinition(WikiQuickAccessStore::class, (new Definition(WikiQuickAccessStore::class))->setArgument('$runner', $runner));
        $container->setDefinition(WikiQuickAccessEraseUserCommand::class, (new Definition(WikiQuickAccessEraseUserCommand::class))
            ->setArgument('$store', new Reference(WikiQuickAccessStore::class))
            ->addTag('console.command'));
        $container->setDefinition(WikiRecentsPurgeCommand::class, (new Definition(WikiRecentsPurgeCommand::class))
            ->setArguments(['$store' => new Reference(WikiQuickAccessStore::class), '$retentionDays' => $recents['retention_days']])
            ->addTag('console.command'));
        // Audit VC2-1: the comment service's privacy export carries the favourites and visits too.
        if ($container->hasDefinition(WikiCommentService::class)) {
            $container->getDefinition(WikiCommentService::class)->setArgument('$quickAccess', new Reference(WikiQuickAccessStore::class));
        }
        $container->setDefinition(WikiQuickAccessErasureListener::class, (new Definition(WikiQuickAccessErasureListener::class))
            ->setArgument('$store', new Reference(WikiQuickAccessStore::class))
            ->addTag('kernel.event_listener', ['event' => WikiEvents::COMMENT_AUTHOR_ERASED, 'method' => 'onAuthorErased']));
        if ($favourites['enabled'] || $recents['enabled'] || $config['backlinks']['enabled']) {
            $this->registerReadableSpaces($container);
        }
        if (!$favourites['enabled'] && !$recents['enabled']) {
            return;
        }
        $container->setDefinition(WikiQuickAccess::class, (new Definition(WikiQuickAccess::class))
            ->setArguments([
                '$store'      => new Reference(WikiQuickAccessStore::class),
                '$favourites' => $favourites,
                '$recents'    => $recents,
                '$logger'     => new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('monolog.logger', ['channel' => 'nowo_wiki']));
    }

    /**
     * 1.45 (owner decision A, audit VC-9): the per-view scope of the lists, tenant-aware through the link picker's
     * tenant port ({@see WikiLinkTenantResolverInterface}; `tenant_paths` by default, a host alias wins).
     */
    private function registerReadableSpaces(ContainerBuilder $container): void
    {
        if (!$container->hasAlias(WikiLinkTenantResolverInterface::class) && !$container->hasDefinition(WikiLinkTenantResolverInterface::class)) {
            $container->setDefinition(WikiTenantPathsLinkTenantResolver::class, (new Definition(WikiTenantPathsLinkTenantResolver::class))
                ->setArgument('$tenantPaths', new Reference(WikiTenantPaths::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)));
            $container->setAlias(WikiLinkTenantResolverInterface::class, WikiTenantPathsLinkTenantResolver::class);
        }
        $container->setDefinition(WikiReadableSpaces::class, (new Definition(WikiReadableSpaces::class))
            ->setArguments([
                '$roles'   => new Reference(WikiAccessCheckerInterface::class),
                '$spaces'  => new Reference(WikiSpaceAccessResolverInterface::class),
                '$checker' => new Reference(WikiSpaceAccessCheckerInterface::class),
                '$tenants' => new Reference(WikiLinkTenantResolverInterface::class),
            ]));
    }

    /**
     * 1.45 (`backlinks.enabled`): the backlink index, its PAGE_SAVED listener, the "Linked from" finder and
     * `wiki:backlinks:rebuild`. Nothing is registered when it is off.
     *
     * @param array<string, mixed> $config processed configuration
     */
    private function registerBacklinks(ContainerBuilder $container, array $config): void
    {
        /** @var array{enabled: bool, limit: int, candidates: int} $backlinks */
        $backlinks = $config['backlinks'];
        if (!$backlinks['enabled']) {
            return;
        }
        $runner = new Reference(WikiTransactionRunner::class);
        $container->setDefinition(WikiPageReferenceIndex::class, (new Definition(WikiPageReferenceIndex::class))
            ->setArguments([
                '$runner'     => $runner,
                '$references' => new Reference(WikiMarkdownReferenceExtractor::class),
                '$renderer'   => new Reference(WikiMarkdownRendererInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ]));
        $container->setDefinition(WikiPageReferenceListener::class, (new Definition(WikiPageReferenceListener::class))
            ->setArguments([
                '$index'  => new Reference(WikiPageReferenceIndex::class),
                '$logger' => new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('kernel.event_listener', ['event' => WikiEvents::PAGE_SAVED, 'method' => 'onPageSaved'])
            ->addTag('monolog.logger', ['channel' => 'nowo_wiki']));
        $container->setDefinition(WikiBacklinks::class, (new Definition(WikiBacklinks::class))
            ->setArguments([
                '$runner'     => $runner,
                '$limit'      => $backlinks['limit'],
                '$candidates' => $backlinks['candidates'],
            ]));
        $container->setDefinition(WikiBacklinksRebuildCommand::class, (new Definition(WikiBacklinksRebuildCommand::class))
            ->setArguments([
                '$index'  => new Reference(WikiPageReferenceIndex::class),
                '$logger' => new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('console.command')
            ->addTag('monolog.logger', ['channel' => 'nowo_wiki']));
    }

    /**
     * 1.45 (`history.rendered_diff`): the editor's rendered diff on the Compare page. Needs `editor.type: milkdown`.
     *
     * @param array<string, mixed> $config processed configuration
     *
     * @throws LogicException with rendered_diff on and another editor
     */
    private function registerRenderedDiff(ContainerBuilder $container, array $config): void
    {
        if (!$config['history']['rendered_diff']) {
            return;
        }
        if ($config['editor']['type'] !== Configuration::EDITOR_MILKDOWN) {
            throw new LogicException('nowo_wiki.history.rendered_diff needs nowo_wiki.editor.type: milkdown (the diff is the Milkdown editor\'s).');
        }
        $container->setDefinition(WikiRenderedRevisionDiff::class, (new Definition(WikiRenderedRevisionDiff::class))
            ->setArguments([
                '$renderer'               => new Reference(WikiMarkdownRendererInterface::class),
                '$sanitizer'              => new Reference(WikiHtmlSanitizerInterface::class),
                '$extraAllowedElements'   => $config['sanitizer']['extra_allowed_elements'],
                '$extraAllowedAttributes' => array_values($config['sanitizer']['extra_allowed_attributes']),
                '$renderContext'          => new Reference(WikiRenderContext::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                '$attachmentFilter'       => new Reference(WikiAttachmentReferenceFilter::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                '$translator'             => new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ]));
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
            ->setArgument('$avatars', new Reference(WikiAvatars::class))
            ->setArgument('$users', new Reference(WikiUsers::class)));
    }

    /**
     * 1.40 (contract "section anchors and link picker" §3.1): the Milkdown editor options of section anchors
     * (`editor.milkdown.stable_heading_ids`) and, with `editor.milkdown.link_picker.enabled`, the picker's service, its
     * runtime settings and tenant ports (both aliasable) and the two JSON routes.
     *
     * @param array<string, mixed> $config processed configuration
     *
     * @throws LogicException with the picker on and another editor type, or without a rate limiter
     */
    private function registerLinkPicker(ContainerBuilder $container, array $config): void
    {
        /** @var array{enabled: bool, cross_wiki: bool, rate_limiter: string|null} $picker */
        $picker   = $config['editor']['milkdown']['link_picker'];
        $milkdown = $config['editor']['type'] === Configuration::EDITOR_MILKDOWN;
        if ($picker['enabled'] && !$milkdown) {
            throw new LogicException('nowo_wiki.editor.milkdown.link_picker needs nowo_wiki.editor.type "milkdown".');
        }
        if ($picker['enabled'] && (!is_string($picker['rate_limiter']) || $picker['rate_limiter'] === '')) {
            throw new LogicException('nowo_wiki.editor.milkdown.link_picker needs a rate limiter: set editor.milkdown.link_picker.rate_limiter to the service id of a Symfony RateLimiterFactory (e.g. limiter.wiki_link_picker from framework.rate_limiter.wiki_link_picker: {policy: sliding_window, limit: 120, interval: "1 minute"}). It limits both picker routes per user.');
        }
        $stable = $this->literalBool($config['editor']['milkdown']['stable_heading_ids'], 'editor.milkdown.stable_heading_ids');
        if (!$milkdown || (!$stable && !$picker['enabled'])) {
            return;
        }

        $container->setDefinition(WikiLinkPickerEditorOptions::class, (new Definition(WikiLinkPickerEditorOptions::class))
            ->setArgument('$urlGenerator', new Reference('router'))
            ->setArgument('$routes', $config['routes'])
            ->setArgument('$linkPicker', $picker['enabled'])
            ->setArgument('$stableHeadingIds', $stable)
            ->setArgument('$profiles', new Reference(ProfileRegistry::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)));
        if (!$picker['enabled']) {
            return;
        }

        $container->setDefinition(ConfiguredWikiLinkPickerSettings::class, (new Definition(ConfiguredWikiLinkPickerSettings::class))
            ->setArgument('$crossWiki', $picker['cross_wiki']));
        $container->setAlias(WikiLinkPickerSettingsInterface::class, ConfiguredWikiLinkPickerSettings::class);
        $container->setDefinition(WikiTenantPathsLinkTenantResolver::class, (new Definition(WikiTenantPathsLinkTenantResolver::class))
            ->setArgument('$tenantPaths', new Reference(WikiTenantPaths::class)));
        $container->setAlias(WikiLinkTenantResolverInterface::class, WikiTenantPathsLinkTenantResolver::class);
        $container->setDefinition(WikiLinkPickerService::class, (new Definition(WikiLinkPickerService::class))
            ->setArgument('$pages', new Reference(WikiPageRepositoryInterface::class))
            ->setArgument('$guard', new Reference(WikiSpaceGuard::class))
            ->setArgument('$accessChecker', new Reference(WikiAccessCheckerInterface::class))
            ->setArgument('$spaces', new Reference(WikiSpaceAccessResolverInterface::class))
            ->setArgument('$urlGenerator', new Reference('router'))
            ->setArgument('$routes', $config['routes'])
            ->setArgument('$settings', new Reference(WikiLinkPickerSettingsInterface::class))
            ->setArgument('$tenants', new Reference(WikiLinkTenantResolverInterface::class))
            ->setArgument('$renderer', new Reference(WikiMarkdownRendererInterface::class))
            ->setArgument('$sanitizer', new Reference(WikiHtmlSanitizerInterface::class))
            ->setArgument('$renderContext', new Reference(WikiRenderContext::class))
            ->setArgument('$searchScope', $config['search']['scope'])
            ->setArgument('$excludedSpaces', array_values($config['search']['excluded_spaces']))
            ->setArgument('$searchCandidates', $config['api']['search_candidates']));
        $container->getDefinition(WikiLinkPickerController::class)
            ->setArgument('$picker', new Reference(WikiLinkPickerService::class))
            ->setArgument('$rateLimiter', new Reference((string) $picker['rate_limiter']));
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
        /** @var array{enabled: bool, rate_limiter: string|null, page_rate_limiter: string|null, state_store: string|null, shortcut: string|null, read_view_source: bool, moderation_panel: mixed, error_notices: bool, dock_min_width: string, layout?: string, thread_list?: string, limits: array{threads_per_page: int, total_threads_per_page: int, user_threads_per_page: int, comments_per_thread: int, reactions_per_comment: int, user_reactions_per_comment: int}} $comments */
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
            ->setArgument('$renderContext', new Reference(WikiRenderContext::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$deferral', new Reference(WikiEventDeferral::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$users', new Reference(WikiUserReferenceResolverInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$userClasses', new Reference(WikiUserClassPolicy::class))
            ->setArgument('$runner', new Reference(WikiTransactionRunner::class))
            ->setArgument('$logger', new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->addTag('monolog.logger', ['channel' => 'nowo_wiki']));
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
            ->setArgument('$errorNotices', $comments['error_notices'])
            ->setArgument('$dockMinWidth', $comments['dock_min_width'])
            ->setArgument('$sessionStore', $this->sessionPanelStore($comments))
            // 1.31: comments.layout (UiKit side panel layout; overlay starts closed).
            ->setArgument('$overlay', ($comments['layout'] ?? 'inline') === 'overlay')
            // 1.31: comments.thread_list (UiKit rows, badges and plural counts in the thread component's list).
            ->setArgument('$uikitThreadList', ($comments['thread_list'] ?? 'plain') === 'uikit'));
    }

    /**
     * 1.16 / 1.29: the panel state store (the session unless `comments.state_store` names a service), registered with
     * the comments or the tree rail (`web_ui.tree_rail`); with the rail, also the rail builder of the tree card.
     *
     * @param array<string, mixed> $config processed configuration
     */
    private function registerPanelState(ContainerBuilder $container, array $config): void
    {
        /** @var array{enabled: bool, state_store: string|null} $comments */
        $comments = $config['comments'];
        $treeRail = $config['web_ui']['tree_rail'] === true;
        if (!$comments['enabled'] && !$treeRail) {
            return;
        }

        $store = $comments['state_store'];
        if ($this->sessionPanelStore($comments)) {
            $store = SessionWikiPanelStateStore::class;
            $container->setDefinition($store, (new Definition(SessionWikiPanelStateStore::class))
                ->setArgument('$requestStack', new Reference('request_stack')));
        }
        $container->setAlias(WikiPanelStateStoreInterface::class, (string) $store);

        if ($treeRail) {
            $container->setDefinition(WikiTreeRail::class, (new Definition(WikiTreeRail::class))
                ->setArgument('$state', new Reference(WikiPanelStateStoreInterface::class))
                ->setArgument('$urlGenerator', new Reference('router'))
                ->setArgument('$csrf', new Reference('security.csrf.token_manager'))
                ->setArgument('$routes', $config['routes'])
                ->setArgument('$sessionStore', $this->sessionPanelStore($comments)));
        }
    }

    /**
     * Whether the panel state store is the bundle's session store (no `comments.state_store`): without a session the
     * panels then fall back to the browser's localStorage (UiKit `persist`).
     *
     * @param array<string, mixed> $comments processed `comments` node
     */
    private function sessionPanelStore(array $comments): bool
    {
        $store = $comments['state_store'] ?? null;

        return !is_string($store) || $store === '';
    }

    /**
     * 1.50: rename, archive, unarchive and purge of a space ({@see WikiSpaceLifecycleInterface}, public), and the archived
     * space's rule around whatever space checker the application configured. The purge clears only the tables of the
     * features that are on (the others do not exist).
     *
     * @param array<string, mixed> $config processed configuration
     */
    private function registerSpaceLifecycle(ContainerBuilder $container, array $config): void
    {
        /** @var array{enabled: bool} $collaboration */
        $collaboration = $config['collaboration'];
        /** @var array{enabled: bool} $trash */
        $trash = $config['trash'];
        /** @var array{timeline: bool} $history */
        $history = $config['history'];
        /** @var array{enabled: bool} $comments */
        $comments = $config['comments'];
        /** @var array{enabled: bool} $favourites */
        $favourites = $config['favourites'];
        /** @var array{enabled: bool} $recents */
        $recents = $config['recents'];
        /** @var array{enabled: bool} $backlinks */
        $backlinks = $config['backlinks'];
        /** @var array{purge_event: bool, max_files: int, max_purge_files: int} $pageFiles */
        $pageFiles = $config['page_files'];
        $metadata  = $container->getDefinition(WikiMetadataListener::class)->getArguments();

        $purger = (new Definition(WikiSpacePurger::class))
            ->setArgument('$runner', new Reference(WikiTransactionRunner::class))
            ->setArgument('$tables', [
                'comments'         => $comments['enabled'],
                'trash'            => $trash['enabled'],
                'history_timeline' => $history['timeline'],
                'collaboration'    => $collaboration['enabled'],
                'favourites'       => $favourites['enabled'],
                'recents'          => $recents['enabled'],
                'backlinks'        => $backlinks['enabled'],
                'api_idempotency'  => ($metadata['$apiIdempotencyEnabled'] ?? false) === true,
            ]);
        if ($pageFiles['purge_event']) {
            $purger
                ->setArgument('$pageFiles', (new Definition(WikiPageFileExtractor::class))->setArgument('$maxFiles', $pageFiles['max_files']))
                ->setArgument('$revisions', new Reference(WikiPageRevisionRepositoryInterface::class))
                ->setArgument('$maxFiles', $pageFiles['max_purge_files']);
        }
        $container->setDefinition(WikiSpacePurger::class, $purger);
        $container->setDefinition(WikiSpaceLifecycle::class, (new Definition(WikiSpaceLifecycle::class))
            ->setArgument('$runner', new Reference(WikiTransactionRunner::class))
            ->setArgument('$spaces', new Reference(WikiSpaceRepositoryInterface::class))
            ->setArgument('$purger', new Reference(WikiSpacePurger::class))
            ->setArgument('$slugger', new Reference(WikiSlugger::class))
            ->setArgument('$checker', new Reference(WikiSpaceAccessCheckerInterface::class))
            ->setArgument('$dispatcher', new Reference('event_dispatcher'))
            ->setArgument('$deferral', new Reference(WikiEventDeferral::class))
            ->setArgument('$storage', new Reference(WikiAttachmentStorageInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$logger', new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE))
            ->setArgument('$slugRule', new Reference(WikiSpaceSlugRule::class))
            ->addTag('monolog.logger', ['channel' => 'nowo_wiki']));
        $container->setAlias(WikiSpaceLifecycleInterface::class, WikiSpaceLifecycle::class)->setPublic(true);

        // Around the alias the application may point at its own checker (DecoratorServicePass follows the alias).
        $container->setDefinition(WikiArchivedSpaceAccessChecker::class, (new Definition(WikiArchivedSpaceAccessChecker::class))
            ->setDecoratedService(WikiSpaceAccessCheckerInterface::class)
            ->setArgument('$inner', new Reference('.inner')));
    }

    /**
     * 1.51: the read ports ({@see WikiSpaceQueryInterface}, {@see WikiPageQueryInterface}) and the page rule by ids
     * ({@see WikiPageAccessInterface}), public aliases. The page rule asks the same space checker as `WikiSpaceGuard`; the
     * backlinks read the index only when `backlinks.enabled` keeps it.
     *
     * @param array<string, mixed> $config processed configuration
     */
    private function registerReadModel(ContainerBuilder $container, array $config): void
    {
        /** @var array{enabled: bool, candidates: int} $backlinks */
        $backlinks = $config['backlinks'];
        $runner    = new Reference(WikiTransactionRunner::class);
        $container->setDefinition(WikiPageAccess::class, (new Definition(WikiPageAccess::class))
            ->setArgument('$runner', $runner)
            ->setArgument('$checker', new Reference(WikiSpaceAccessCheckerInterface::class)));
        $container->setAlias(WikiPageAccessInterface::class, WikiPageAccess::class)->setPublic(true);
        $container->setDefinition(WikiSpaceQuery::class, (new Definition(WikiSpaceQuery::class))
            ->setArgument('$runner', $runner));
        $container->setAlias(WikiSpaceQueryInterface::class, WikiSpaceQuery::class)->setPublic(true);
        $container->setDefinition(WikiPageQuery::class, (new Definition(WikiPageQuery::class))
            ->setArgument('$runner', $runner)
            ->setArgument('$access', new Reference(WikiPageAccessInterface::class))
            ->setArgument('$search', new Reference(WikiSearchService::class))
            ->setArgument('$references', new Reference(WikiMarkdownReferenceExtractor::class))
            ->setArgument('$backlinkIndex', $backlinks['enabled'])
            ->setArgument('$candidates', $backlinks['candidates'])
            ->setArgument('$renderer', new Reference(WikiMarkdownRendererInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)));
        $container->setAlias(WikiPageQueryInterface::class, WikiPageQuery::class)->setPublic(true);
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
            ->setArgument('$runner', new Reference(WikiTransactionRunner::class))
            ->setArgument('$managed', new Reference(WikiManagedEntities::class))
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
     * 1.39: the icon fields of pages and spaces. `prefixes` are the accepted `<prefix>` values, `emoji` adds the
     * pseudo-prefix "emoji" (values `emoji:<grapheme>`). `picker: icon_selector` and `emoji` need
     * nowo-tech/icon-selector-bundle >= 1.6 (its emoji catalogue and the `icon_prefixes` option).
     *
     * @param array<string, mixed> $config processed configuration
     * @param string $catalog the IconSelector interface that marks a usable version (tests name a missing one)
     *
     * @throws LogicException when the picker or emoji is configured without nowo-tech/icon-selector-bundle >= 1.6
     *
     * @return array{page: array{picker: string, emoji: bool, prefixes: list<string>, icon_prefixes: list<string>}, space: array{picker: string, emoji: bool, prefixes: list<string>, icon_prefixes: list<string>}}
     */
    public static function iconPicker(array $config, string $catalog = self::ICON_SELECTOR_EMOJI_CATALOG): array
    {
        /** @var array{allowed_prefixes: list<string>, picker?: string, emoji?: bool} $spaceIcon */
        $spaceIcon = $config['space_icon'];
        /** @var array{picker?: string, emoji?: bool} $pageIcon */
        $pageIcon = $config['page_icon'];
        $prefixes = array_values($spaceIcon['allowed_prefixes']);
        $out      = [];
        foreach (['page' => $pageIcon, 'space' => $spaceIcon] as $scope => $node) {
            $picker = $node['picker'] ?? 'text';
            $emoji  = ($node['emoji'] ?? false) === true;
            if (($picker === 'icon_selector' || $emoji) && !interface_exists($catalog)) {
                throw new LogicException(sprintf('nowo_wiki.%s_icon.%s needs nowo-tech/icon-selector-bundle >= 1.6 (composer require nowo-tech/icon-selector-bundle:^1.6) with NowoIconSelectorBundle registered.', $scope, $picker === 'icon_selector' ? 'picker: icon_selector' : 'emoji: true'));
            }
            $out[$scope] = [
                'picker'        => $picker,
                'emoji'         => $emoji,
                'prefixes'      => $emoji ? [...$prefixes, WikiSpaceIcon::EMOJI_PREFIX_NAME] : $prefixes,
                'icon_prefixes' => $prefixes,
            ];
        }

        /* @var array{page: array{picker: string, emoji: bool, prefixes: list<string>, icon_prefixes: list<string>}, space: array{picker: string, emoji: bool, prefixes: list<string>, icon_prefixes: list<string>}} $out */
        return $out;
    }

    /** IconSelectorBundle >= 1.6: the emoji catalogue port (also the marker of the `icon_prefixes` option). */
    public const ICON_SELECTOR_EMOJI_CATALOG = EmojiCatalogInterface::class;

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
            ->setArgument('$maxDepth', $tree['max_depth'])
            ->setArgument('$deferral', new Reference(WikiEventDeferral::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$runner', new Reference(WikiTransactionRunner::class))
            ->setArgument('$managed', new Reference(WikiManagedEntities::class)));
        // 1.41 (tree.context_menu): "Duplicate" of the tree menu, placed by the mover.
        $container->setDefinition(WikiPageDuplicator::class, (new Definition(WikiPageDuplicator::class))
            ->setArgument('$pages', new Reference(WikiPageServiceInterface::class))
            ->setArgument('$mover', new Reference(WikiPageMover::class))
            ->setArgument('$repository', new Reference(WikiPageRepositoryInterface::class))
            ->setArgument('$slugger', new Reference(WikiSlugger::class))
            ->setArgument('$icons', new Reference(WikiPageIconService::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$dispatcher', new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->setArgument('$managed', new Reference(WikiManagedEntities::class)));
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

        // 1.36: the authorizer is asked through its interface id, so WikiPortsPass can put the twin's gate there.
        $container->setAlias(WikiCollabSubscriberAuthorizerInterface::class, $authorizer);
        $container->setDefinition(WikiCollabCookieListener::class, (new Definition(WikiCollabCookieListener::class))
            ->addTag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onResponse']));

        // 1.16: the comment writes publish their thread ids through the same publisher.
        $container->setAlias(WikiCollabPublisherInterface::class, $publisher);
        $container->setDefinition(WikiCollabService::class, (new Definition(WikiCollabService::class))
            ->setArgument('$logger', new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->addTag('monolog.logger', ['channel' => 'nowo_wiki'])
            ->setArgument('$store', new Reference($store))
            ->setArgument('$publisher', new Reference($publisher))
            ->setArgument('$authorizer', new Reference(WikiCollabSubscriberAuthorizerInterface::class))
            ->setArgument('$options', array_intersect_key($collab, WikiCollabService::DEFAULTS))
            ->setArgument('$rateLimiter', new Reference((string) $collab['rate_limiter']))
            ->setArgument('$userRateLimiter', is_string($collab['user_rate_limiter']) && $collab['user_rate_limiter'] !== '' ? new Reference($collab['user_rate_limiter']) : null)
            ->setArgument('$updateRateLimiter', is_string($collab['update_rate_limiter']) && $collab['update_rate_limiter'] !== '' ? new Reference($collab['update_rate_limiter']) : null)
            ->setArgument('$awarenessRateLimiter', is_string($collab['awareness_rate_limiter']) && $collab['awareness_rate_limiter'] !== '' ? new Reference($collab['awareness_rate_limiter']) : null)
            ->setArgument('$secret', '%kernel.secret%')
            ->setArgument('$deferral', new Reference(WikiEventDeferral::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)));
        $container->setDefinition(WikiCollabSaveListener::class, (new Definition(WikiCollabSaveListener::class))
            ->setArgument('$collab', new Reference(WikiCollabService::class))
            ->setArgument('$requestStack', new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            // 1.24 (audit IF-L2): inside the write, under the log's lock; hosts' PAGE_SAVED listeners run after the commit.
            ->addTag('kernel.event_listener', ['event' => WikiEvents::PAGE_WRITTEN]));
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
        // 1.36: the framework-free twin takes the same tag (WikiPageLinkService asks it through a gate).
        $container->registerForAutoconfiguration(WikiObjectLinkSourceInterface::class)->addTag('nowo_wiki.object_link_resolver');

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
        $layout    = self::layout($config['web_ui']);
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
            ->setArgument('$importExportDialog', $config['web_ui']['import_export'] === 'dialog')
            ->setArgument('$compactHeader', ($config['web_ui']['page_header']['layout'] ?? 'stacked') === 'compact')
            // 1.31: page_header.tabs: toolbar (the edit form's header without back link and intro, Reset collaboration
            // in the "more" menu) and page_meta: card (the revision line in the content card, not the scaffold meta).
            ->setArgument('$tabsToolbar', ($config['web_ui']['page_header']['tabs'] ?? 'row') === 'toolbar')
            ->setArgument('$metaInCard', ($config['web_ui']['page_meta'] ?? 'scaffold') === 'card')
            // 1.33: page_header.edit_toolbar (the Edit tab left out of the edit form; a "View page" button).
            ->setArgument('$editToolbar', ['current_tab' => $layout['edit_current_tab'], 'back' => $layout['edit_back']]));
        // 1.31 (web_ui.breadcrumb_current_label, BreadcrumbKit >= 2.17 installed): the page crumb of the edit, history,
        // compare and move pages is named after the page (a trail listener; the title rides on the request).
        if ($layout['breadcrumb_current_label'] && class_exists(BreadcrumbTrailBuiltEvent::class)) {
            $container->setDefinition(WikiBreadcrumbParentLabel::class, (new Definition(WikiBreadcrumbParentLabel::class))
                ->setArgument('$urlGenerator', new Reference('router'))
                ->setArgument('$routes', $config['routes'])
                ->addTag('kernel.event_listener', ['event' => BreadcrumbTrailBuiltEvent::class, 'method' => '__invoke']));
        }
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
     * 1.29: `layout` (tree_rail, narrow_tree_position, reading_width, page_header, title, comments_dock), read by the
     * page view, the edit form and the space home. 1.30: tabs, toolbar_position, tree_collapsed_mode, reading_align, title `body`, path, children, breadcrumb_current_label, seamless_swap. 1.31: title `card`, page_meta. 1.33: edit_layout, edit_current_tab, edit_back. 1.35: new_page_dialog.
     *
     * @return array{assets: array{styles: bool, scripts: bool}, slots: array<string, string|null>, import_export: string, layout: array{tree_rail: bool, narrow_tree_position: string, reading_width: string, page_header: string, title: string, tabs: string, toolbar_position: string, tree_collapsed_mode: string, reading_align: string, path: string, children: string, breadcrumb_current_label: bool, seamless_swap: bool, page_meta: string, edit_layout: string, edit_current_tab: string, edit_back: string, new_page_dialog: bool, page_nav: string}}
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
            'layout'        => self::layout($webUi),
        ];
    }

    /**
     * 1.29: the page layout options of `web_ui` (every default keeps the 1.28 markup).
     *
     * @param array<string, mixed> $webUi processed `web_ui` node
     *
     * @return array{tree_rail: bool, narrow_tree_position: string, reading_width: string, page_header: string, title: string, tabs: string, toolbar_position: string, tree_collapsed_mode: string, reading_align: string, path: string, children: string, breadcrumb_current_label: bool, seamless_swap: bool, page_meta: string, edit_layout: string, edit_current_tab: string, edit_back: string, new_page_dialog: bool, page_nav: string}
     */
    public static function layout(array $webUi): array
    {
        $header = is_array($webUi['page_header'] ?? null) ? $webUi['page_header'] : [];

        return [
            'tree_rail'            => ($webUi['tree_rail'] ?? false) === true,
            'narrow_tree_position' => ($webUi['narrow_tree_position'] ?? 'before') === 'after' ? 'after' : 'before',
            'reading_width'        => match ($webUi['reading_width'] ?? 'full') {
                'readable' => 'readable',
                'text'     => 'text',
                default    => 'full',
            },
            'page_header' => ($header['layout'] ?? 'stacked') === 'compact' ? 'compact' : 'stacked',
            'title'       => match ($header['title'] ?? 'both') {
                'dedupe' => 'dedupe',
                'body'   => 'body',
                'card'   => 'card',
                default  => 'both',
            },
            // 1.30
            'tabs'                => ($header['tabs'] ?? 'row') === 'toolbar' ? 'toolbar' : 'row',
            'toolbar_position'    => ($header['toolbar_position'] ?? 'header') === 'card' ? 'card' : 'header',
            'tree_collapsed_mode' => ($webUi['tree_collapsed_mode'] ?? 'rail') === 'hidden' ? 'hidden' : 'rail',
            'reading_align'       => ($webUi['reading_align'] ?? 'center') === 'start' ? 'start' : 'center',
            'path'                => ($header['path'] ?? 'none') === 'card' ? 'card' : 'none',
            'children'            => match ($webUi['children'] ?? 'never') {
                'auto'   => 'auto',
                'always' => 'always',
                default  => 'never',
            },
            'breadcrumb_current_label' => ($webUi['breadcrumb_current_label'] ?? false) === true,
            'seamless_swap'            => ($webUi['read_view']['seamless_swap'] ?? false) === true,
            // 1.31
            'page_meta' => ($webUi['page_meta'] ?? 'scaffold') === 'card' ? 'card' : 'scaffold',
            // 1.33
            'edit_layout'      => ($webUi['edit_view']['layout'] ?? 'form') === 'read' ? 'read' : 'form',
            'edit_current_tab' => ($header['edit_toolbar']['current_tab'] ?? 'shown') === 'hidden' ? 'hidden' : 'shown',
            'edit_back'        => ($header['edit_toolbar']['back'] ?? 'none') === 'view' ? 'view' : 'none',
            // 1.35
            'new_page_dialog' => ($webUi['new_page_dialog'] ?? false) === true,
            // 1.39
            'page_nav' => in_array($webUi['page_nav'] ?? 'none', ['top', 'bottom', 'both'], true) ? $webUi['page_nav'] : 'none',
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
     * Route path prefix: web_ui.path_prefix, else none.
     *
     * @param array<string, mixed> $config processed configuration
     */
    public static function pathPrefix(array $config): string
    {
        $prefix = $config['web_ui']['path_prefix'] ?? '';

        return is_string($prefix) ? $prefix : '';
    }

    /**
     * Layout and page templates: web_ui.layout_template / web_ui.templates.<page>, else null (UiKit admin layout) / the
     * bundle template.
     *
     * @param array<string, mixed> $config processed configuration
     *
     * @return array<string, string|null> `layout` + page id => template
     */
    public static function templates(array $config): array
    {
        $webUi     = is_array($config['web_ui'] ?? null) ? $config['web_ui'] : [];
        $overrides = is_array($webUi['templates'] ?? null) ? $webUi['templates'] : [];
        $layout    = $webUi['layout_template'] ?? null;

        $templates = ['layout' => is_string($layout) && $layout !== '' && $layout !== WikiPages::LEGACY_DEFAULT_LAYOUT ? $layout : null];
        foreach (WikiPages::TEMPLATES as $page => $default) {
            $template         = $overrides[$page] ?? null;
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
     * Prepends Twig globals, asset package, Doctrine mapping and FormKit defaults when those extensions are present.
     *
     * @param ContainerBuilder $container container being built
     */
    public function prepend(ContainerBuilder $container): void
    {
        // Defaults for host config that reads %nowo_wiki.web_ui% before load(); the Twig global is a service (see load()).
        if (!$container->hasParameter('nowo_wiki.web_ui')) {
            $container->setParameter('nowo_wiki.web_ui', [
                'enabled'         => true,
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

        // 1.36.0: database.mapping_type (attribute by default, as up to 1.31; xml for DDD hosts).
        WikiDoctrineMapping::prepend($container);

        // 1.36 (database.user_reference: id): the column type of the stored user ids.
        if ($container->hasExtension('doctrine') && $this->userReferencesById($container)) {
            $container->prependExtensionConfig('doctrine', ['dbal' => ['types' => [WikiUserRefType::NAME => WikiUserRefType::class]]]);
        }

        $this->prependFormKitDefaults($container);
    }

    /**
     * Whether the host configuration sets `database.user_reference: id` (1.36; the last value wins, as in the tree).
     */
    private function userReferencesById(ContainerBuilder $container): bool
    {
        $mode = 'entity';
        foreach ($container->getExtensionConfig(Configuration::ALIAS) as $config) {
            $value = $config['database']['user_reference'] ?? null;
            if (is_string($value)) {
                $mode = $value;
            }
        }

        return $mode === 'id';
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
}
