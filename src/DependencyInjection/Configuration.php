<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\DependencyInjection;

use InvalidArgumentException;
use Nowo\MilkdownEditorBundle\Form\MilkdownEditorType;
use Nowo\WikiBundle\Attachment\WikiAttachmentLimits;
use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Builder\VariableNodeDefinition;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function array_filter;
use function array_values;
use function class_exists;
use function implode;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Configuration tree for WikiBundle.
 */
final class Configuration implements ConfigurationInterface
{
    public const ALIAS = 'nowo_wiki';

    /** Composer package (deprecation notices). */
    public const PACKAGE = 'nowo-tech/wiki-bundle';

    /** editor.type: HTML pages edited with TiptapEditorType (default). */
    public const EDITOR_TIPTAP = 'tiptap';

    /** editor.type: Markdown pages (1.11). */
    public const EDITOR_MARKDOWN = 'markdown';

    /**
     * editor.type: Markdown pages in the nowo-tech/milkdown-editor-bundle dialect (1.13): MilkdownEditorType as the page
     * body, the dialect renderer (page links, mentions, Tabler icons, directives, math, Mermaid) and its limits.
     */
    public const EDITOR_MILKDOWN = 'milkdown';

    /** Form type of the page body with editor.type milkdown (nowo-tech/milkdown-editor-bundle). */
    public const MILKDOWN_FORM_TYPE = MilkdownEditorType::class;

    /** Default of security.access_roles. */
    public const DEFAULT_ACCESS_ROLES = ['ROLE_USER'];

    /** Default roles per action (security.roles.<action>) when neither the key nor its deprecated alias is set. */
    public const DEFAULT_ROLES = [
        'admin'   => ['ROLE_ADMIN'],
        'list'    => ['ROLE_USER'],
        'create'  => ['ROLE_USER'],
        'edit'    => ['ROLE_USER'],
        'history' => ['ROLE_USER'],
        'archive' => ['ROLE_USER'],
        'ai'      => ['ROLE_USER'],
        'import'  => ['ROLE_ADMIN'],
        'export'  => ['ROLE_USER'],
    ];

    /**
     * Whether pages of the editor type are stored as Markdown (`markdown` and `milkdown`, 1.13).
     *
     * @param string $editorType value of nowo_wiki.editor.type
     */
    public static function storesMarkdown(string $editorType): bool
    {
        return $editorType === self::EDITOR_MARKDOWN || $editorType === self::EDITOR_MILKDOWN;
    }

    /**
     * Builds the {@code nowo_wiki} configuration tree (user class, tables, routes, templates, security, AI, import/export, pagination, web UI).
     *
     * @return TreeBuilder configuration tree builder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $root        = $treeBuilder->getRootNode();

        $root
            ->children()
                ->scalarNode('user_class')
                    ->info('FQCN of the application user entity.')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->example('App\\Entity\\User')
                ->end()
                ->scalarNode('table_prefix')
                    ->defaultValue('wiki_')
                    ->cannotBeEmpty()
                ->end()
                ->enumNode('space_scope')
                    ->values(['team', 'user'])
                    ->defaultValue('team')
                ->end()
                ->scalarNode('team_membership_resolver')
                    ->defaultNull()
                ->end()
                ->arrayNode('database')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('driver')
                            ->defaultValue('doctrine_orm')
                            ->setDeprecated('nowo-tech/wiki-bundle', '1.6', 'The "%path%.%node%" option is deprecated and has no effect: the bundle only ships Doctrine ORM repositories. It will be removed in 2.0.')
                        ->end()
                        ->scalarNode('platform')
                            ->defaultValue('mysql')
                            ->setDeprecated('nowo-tech/wiki-bundle', '1.6', 'The "%path%.%node%" option is deprecated and has no effect: the database platform comes from the Doctrine connection. It will be removed in 2.0.')
                        ->end()
                        ->scalarNode('entity_manager')->defaultValue('default')->end()
                    ->end()
                ->end()
                ->scalarNode('route_prefix')
                    ->info('Deprecated since 1.10: use web_ui.path_prefix.')
                    ->defaultNull()
                    ->setDeprecated(self::PACKAGE, '1.10', 'The "%path%.%node%" option is deprecated, use "nowo_wiki.web_ui.path_prefix" instead.')
                ->end()
                ->scalarNode('dashboard_route')
                    ->defaultNull()
                ->end()
                ->arrayNode('tenant_paths')
                    ->info('1.9: copies of every space-scoped route under host paths (e.g. /projects/{spaceSlug}/wiki). The first tenant whose slug_requirement matches a space slug owns its URLs.')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('path')
                                ->isRequired()
                                ->cannotBeEmpty()
                                ->validate()
                                    ->ifTrue(static fn (mixed $path): bool => !is_string($path) || !str_contains($path, '{spaceSlug}'))
                                    ->thenInvalid('A wiki tenant path must contain {spaceSlug}, got %s.')
                                ->end()
                            ->end()
                            ->scalarNode('name_prefix')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('slug_requirement')->defaultValue('[a-z0-9]+(?:-[a-z0-9]+)*')->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
                ->end()
                ->booleanNode('tenant_only')
                    ->info('1.9: when tenant_paths is set, leave out the tenant-less copies of the space-scoped routes.')
                    ->defaultFalse()
                ->end()
                ->arrayNode('attachments')
                    ->info('1.9: page attachments and image upload (private storage, authorized on the page).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('storage_dir')->defaultValue('%kernel.project_dir%/var/wiki-attachments')->info('Directory of the default local storage (keep it outside public/).')->end()
                        ->scalarNode('storage')->defaultNull()->info('Service id of a WikiAttachmentStorageInterface replacing the local directory.')->end()
                        ->scalarNode('scanner')->defaultNull()->info('Service id of a WikiAttachmentScannerInterface (default: accepts every file).')->end()
                        ->integerNode('max_file_bytes')->defaultValue(10485760)->min(1024)->end()
                        ->integerNode('max_space_bytes')->defaultValue(524288000)->min(1024)->end()
                        ->arrayNode('allowed_types')
                            ->info('Extension => accepted MIME types sniffed on the server. SVG, HTML, XML and scripts are refused unless listed (do not).')
                            ->useAttributeAsKey('extension')
                            ->arrayPrototype()->scalarPrototype()->end()->end()
                            ->defaultValue(WikiAttachmentLimits::DEFAULT_ALLOWED_TYPES)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('collaboration')
                    ->info('1.13: real-time co-editing of Milkdown pages over Mercure (FR-076). Needs editor.type milkdown, nowo-tech/milkdown-editor-bundle >= 1.2 and symfony/mercure-bundle (or your own publisher / subscriber_authorizer services).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('hub_url')->info('Hub URL the browser subscribes to (same registrable domain as the app, for the cookie).')->defaultValue(WikiCollabService::DEFAULTS['hub_url'])->end()
                        ->scalarNode('topic_prefix')->info('Topic IRI prefix; the topic of a page is this prefix + the page id.')->defaultValue(WikiCollabService::DEFAULTS['topic_prefix'])->end()
                        ->integerNode('max_update_bytes')->info('Largest decoded update / awareness message.')->min(1024)->defaultValue(WikiCollabService::DEFAULTS['max_update_bytes'])->end()
                        ->integerNode('max_snapshot_bytes')->info('Largest decoded compaction snapshot.')->min(1024)->defaultValue(WikiCollabService::DEFAULTS['max_snapshot_bytes'])->end()
                        ->integerNode('max_log_bytes')->info('Byte budget of the stored log of a page (snapshot + updates, base64); over it the log is dropped and editors reload.')->min(65536)->defaultValue(WikiCollabService::DEFAULTS['max_log_bytes'])->end()
                        ->integerNode('compact_after')->info('Ask clients to compact the log once it holds this many updates.')->min(10)->defaultValue(WikiCollabService::DEFAULTS['compact_after'])->end()
                        ->integerNode('max_log_entries')->info('Refuse updates (429) above this many until a client compacts.')->min(20)->defaultValue(WikiCollabService::DEFAULTS['max_log_entries'])->end()
                        ->integerNode('seed_timeout')->info('Seconds after which an unfulfilled seed claim may be taken again.')->min(5)->defaultValue(WikiCollabService::DEFAULTS['seed_timeout'])->end()
                        ->integerNode('reset_after_idle')->info('Seconds of inactivity after which a log that does not match the current revision is dropped.')->min(0)->defaultValue(WikiCollabService::DEFAULTS['reset_after_idle'])->end()
                        ->integerNode('corrupt_resets')->info('Resets a client may trigger by reporting a log it cannot apply, per page and corrupt_window.')->min(1)->defaultValue(WikiCollabService::DEFAULTS['corrupt_resets'])->end()
                        ->integerNode('corrupt_window')->info('Window of corrupt_resets, in seconds.')->min(10)->defaultValue(WikiCollabService::DEFAULTS['corrupt_window'])->end()
                        ->scalarNode('rate_limiter')->info('Service id of a Symfony RateLimiterFactory applied per user and page to every collaboration request (token, state, update, awareness, compact). Required when collaboration is enabled.')->defaultNull()->end()
                        ->scalarNode('update_rate_limiter')->info('1.14.1: service id of a RateLimiterFactory applied per user and page to update requests instead of rate_limiter (e.g. limiter.wiki_collab_update; null: rate_limiter).')->defaultNull()->end()
                        ->scalarNode('awareness_rate_limiter')->info('1.14.1: service id of a RateLimiterFactory applied per user and page to presence (awareness) requests instead of rate_limiter (e.g. limiter.wiki_collab_awareness; null: rate_limiter).')->defaultNull()->end()
                        ->scalarNode('user_rate_limiter')->info('1.13.1 (CW-I9): service id of a Symfony RateLimiterFactory applied per user across all pages to every collaboration request (null: none).')->defaultNull()->end()
                        ->booleanNode('show_names')->info('1.13.1 (CW-I2): co-editors see each other\'s display name (getDisplayName() / getName()) instead of an opaque "Editor xxxx". Values that equal the login identifier or look like an e-mail address are never shown.')->defaultFalse()->end()
                        ->scalarNode('store')->info('Service id of a WikiCollabStateStoreInterface (null: the Doctrine store, tables <prefix>_page_collab_*).')->defaultNull()->end()
                        ->scalarNode('publisher')->info('Service id of a WikiCollabPublisherInterface (null: symfony/mercure HubInterface).')->defaultNull()->end()
                        ->scalarNode('subscriber_authorizer')->info('Service id of a WikiCollabSubscriberAuthorizerInterface (null: symfony/mercure Authorization cookie).')->defaultNull()->end()
                        ->scalarNode('mercure_hub')->info('Mercure hub name for the default publisher / authorizer (null: the default hub).')->defaultNull()->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $collab): bool => $collab['compact_after'] >= $collab['max_log_entries'])
                        ->thenInvalid('nowo_wiki.collaboration.compact_after must be lower than max_log_entries, got %s.')
                    ->end()
                ->end()
                ->arrayNode('tree')
                    ->info('1.14: Outline-style page tree in the sidebar (UiKit tree, nowo-tech/ui-kit-bundle >= 2.24): disclosures, current branch unfolded, fold state per user and space, moves by drag and drop, Alt+arrows and a "Move" form. Off: the 1.13 nested list.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->integerNode('max_depth')->info('Deepest level of a page after a move (root pages are level 1).')->min(1)->max(50)->defaultValue(10)->end()
                        ->scalarNode('state_store')->info('Service id of a WikiTreeStateStoreInterface keeping the fold state (null: the session).')->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('trash')
                    ->info('1.14 (T065): Delete moves a page and its sub-pages to the trash of the space (restore together, purge for good, retention). Off: no Delete action, no trash table in the schema.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->integerNode('retention_days')->info('Days a deletion stays in the trash; wiki:trash:purge removes older ones (D-W4).')->min(1)->max(3650)->defaultValue(30)->end()
                    ->end()
                ->end()
                ->arrayNode('sanitizer')
                    ->info('1.11: extra HTML allowed in page bodies (e.g. the <details> / <summary> or data-* attributes of a Markdown dialect). script, style, iframe, forms, embeds, style= and on* handlers are always refused.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('extra_allowed_elements')
                            ->info('Element => extra attributes of that element, e.g. {details: [open], summary: []}.')
                            ->useAttributeAsKey('element')
                            ->arrayPrototype()->scalarPrototype()->end()->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('extra_allowed_attributes')
                            ->info('Attributes allowed on every allowed element, e.g. [data-callout].')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static function (mixed $sanitizer): bool {
                            try {
                                WikiHtmlSanitizer::assertSafeAllowlist($sanitizer['extra_allowed_elements'] ?? [], $sanitizer['extra_allowed_attributes'] ?? []);
                            } catch (InvalidArgumentException) {
                                return true;
                            }

                            return false;
                        })
                        ->thenInvalid('nowo_wiki.sanitizer: an extra element or attribute is not allowed (script, style, iframe, forms, embeds, URL attributes, style= and on* handlers are always refused), got %s.')
                    ->end()
                ->end()
                ->arrayNode('editor')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('type')
                            ->info('1.11: tiptap = pages are HTML edited with TiptapEditorType; markdown = pages are stored as Markdown (content_markdown, the source) with content_html as its sanitized render cache, edited in a textarea or in markdown_form_type. 1.13: milkdown = markdown with nowo-tech/milkdown-editor-bundle (MilkdownEditorType and its dialect renderer).')
                            ->values([self::EDITOR_TIPTAP, self::EDITOR_MARKDOWN, self::EDITOR_MILKDOWN])
                            ->defaultValue(self::EDITOR_TIPTAP)
                        ->end()
                        ->scalarNode('tiptap_config')->defaultValue('notion')->end()
                        ->scalarNode('markdown_form_type')
                            ->info('1.11: FQCN of the form type of the page body when type is markdown (e.g. a Markdown editor bundle type); null = a plain textarea. It receives the editor options (WikiEvents::EDITOR_OPTIONS).')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static fn (mixed $value): bool => $value !== null && (!is_string($value) || !class_exists($value)))
                                ->thenInvalid('nowo_wiki.editor.markdown_form_type must be the class name of a form type, got %s.')
                            ->end()
                        ->end()
                        ->arrayNode('milkdown')
                            ->info('1.13: options of editor.type milkdown.')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('page_resolver')
                                    ->info('Service id of the Nowo\\MilkdownEditorBundle\\Markdown\\Resolver\\PageResolverInterface that resolves [[wiki:<uuid>]] links for the viewer in wiki pages; null = the bundle resolver (wiki pages the viewer may read, page_view URLs).')
                                    ->defaultNull()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('security')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('access_checker')->defaultNull()->end()
                        ->arrayNode('access_roles')
                            ->info('General gate of the wiki (REQ-UI-002). An empty list grants nothing through this gate.')
                            ->scalarPrototype()->end()
                            ->defaultValue(self::DEFAULT_ACCESS_ROLES)
                        ->end()
                        ->arrayNode('roles')
                            ->info('Roles per action (REQ-UI-003 security.roles.<action>; replace the deprecated <action>_roles keys). null = the deprecated key, else the default. admin = every action; an empty list grants that action to nobody but the admin roles.')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->append($this->rolesNode('admin'))
                                ->append($this->rolesNode('list'))
                                ->append($this->rolesNode('create'))
                                ->append($this->rolesNode('edit'))
                                ->append($this->rolesNode('history'))
                                ->append($this->rolesNode('archive'))
                                ->append($this->rolesNode('ai'))
                                ->append($this->rolesNode('import'))
                                ->append($this->rolesNode('export'))
                            ->end()
                        ->end()
                        ->variableNode('admin_roles')
                            ->info('Deprecated since 1.10: use security.roles.admin.')
                            ->defaultNull()
                            ->treatNullLike([])
                            ->validate()
                                ->ifTrue(static fn (mixed $value): bool => $value !== null && !self::isRoleList($value))
                                ->thenInvalid('Expected a list of role names, got %s.')
                            ->end()
                            ->setDeprecated(self::PACKAGE, '1.10', 'The "%path%.%node%" option is deprecated, use "nowo_wiki.security.roles.admin" instead.')
                        ->end()
                        ->variableNode('list_roles')
                            ->info('Deprecated since 1.10: use security.roles.list.')
                            ->defaultNull()
                            ->treatNullLike([])
                            ->validate()
                                ->ifTrue(static fn (mixed $value): bool => $value !== null && !self::isRoleList($value))
                                ->thenInvalid('Expected a list of role names, got %s.')
                            ->end()
                            ->setDeprecated(self::PACKAGE, '1.10', 'The "%path%.%node%" option is deprecated, use "nowo_wiki.security.roles.list" instead.')
                        ->end()
                        ->variableNode('create_roles')
                            ->info('Deprecated since 1.10: use security.roles.create.')
                            ->defaultNull()
                            ->treatNullLike([])
                            ->validate()
                                ->ifTrue(static fn (mixed $value): bool => $value !== null && !self::isRoleList($value))
                                ->thenInvalid('Expected a list of role names, got %s.')
                            ->end()
                            ->setDeprecated(self::PACKAGE, '1.10', 'The "%path%.%node%" option is deprecated, use "nowo_wiki.security.roles.create" instead.')
                        ->end()
                        ->variableNode('edit_roles')
                            ->info('Deprecated since 1.10: use security.roles.edit.')
                            ->defaultNull()
                            ->treatNullLike([])
                            ->validate()
                                ->ifTrue(static fn (mixed $value): bool => $value !== null && !self::isRoleList($value))
                                ->thenInvalid('Expected a list of role names, got %s.')
                            ->end()
                            ->setDeprecated(self::PACKAGE, '1.10', 'The "%path%.%node%" option is deprecated, use "nowo_wiki.security.roles.edit" instead.')
                        ->end()
                        ->variableNode('history_roles')
                            ->info('Deprecated since 1.10: use security.roles.history.')
                            ->defaultNull()
                            ->treatNullLike([])
                            ->validate()
                                ->ifTrue(static fn (mixed $value): bool => $value !== null && !self::isRoleList($value))
                                ->thenInvalid('Expected a list of role names, got %s.')
                            ->end()
                            ->setDeprecated(self::PACKAGE, '1.10', 'The "%path%.%node%" option is deprecated, use "nowo_wiki.security.roles.history" instead.')
                        ->end()
                        ->variableNode('archive_roles')
                            ->info('Deprecated since 1.10: use security.roles.archive.')
                            ->defaultNull()
                            ->treatNullLike([])
                            ->validate()
                                ->ifTrue(static fn (mixed $value): bool => $value !== null && !self::isRoleList($value))
                                ->thenInvalid('Expected a list of role names, got %s.')
                            ->end()
                            ->setDeprecated(self::PACKAGE, '1.10', 'The "%path%.%node%" option is deprecated, use "nowo_wiki.security.roles.archive" instead.')
                        ->end()
                        ->variableNode('ai_roles')
                            ->info('Deprecated since 1.10: use security.roles.ai.')
                            ->defaultNull()
                            ->treatNullLike([])
                            ->validate()
                                ->ifTrue(static fn (mixed $value): bool => $value !== null && !self::isRoleList($value))
                                ->thenInvalid('Expected a list of role names, got %s.')
                            ->end()
                            ->setDeprecated(self::PACKAGE, '1.10', 'The "%path%.%node%" option is deprecated, use "nowo_wiki.security.roles.ai" instead.')
                        ->end()
                        ->variableNode('import_roles')
                            ->info('Deprecated since 1.10: use security.roles.import.')
                            ->defaultNull()
                            ->treatNullLike([])
                            ->validate()
                                ->ifTrue(static fn (mixed $value): bool => $value !== null && !self::isRoleList($value))
                                ->thenInvalid('Expected a list of role names, got %s.')
                            ->end()
                            ->setDeprecated(self::PACKAGE, '1.10', 'The "%path%.%node%" option is deprecated, use "nowo_wiki.security.roles.import" instead.')
                        ->end()
                        ->variableNode('export_roles')
                            ->info('Deprecated since 1.10: use security.roles.export.')
                            ->defaultNull()
                            ->treatNullLike([])
                            ->validate()
                                ->ifTrue(static fn (mixed $value): bool => $value !== null && !self::isRoleList($value))
                                ->thenInvalid('Expected a list of role names, got %s.')
                            ->end()
                            ->setDeprecated(self::PACKAGE, '1.10', 'The "%path%.%node%" option is deprecated, use "nowo_wiki.security.roles.export" instead.')
                        ->end()
                        ->booleanNode('allow_unauthenticated')
                            ->info('DEV / DEMO ONLY: allow every wiki action without a role check. Literal boolean; refused in the prod environment and in non-debug kernels (test* environments excepted).')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('import_export')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->integerNode('max_upload_bytes')->defaultValue(52428800)->min(1048576)->max(524288000)->end()
                    ->end()
                ->end()
                ->arrayNode('pagination')
                    ->info('REQ-PERF-001: caps on the list pages of the manage UI.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('spaces_per_page')->defaultValue(24)->min(1)->max(500)->info('Space cards per page on the wiki index (?page=N).')->end()
                        ->integerNode('revisions_per_page')->defaultValue(25)->min(1)->max(500)->info('Revisions per page on the page history (?page=N).')->end()
                        ->integerNode('max_tree_pages')->defaultValue(500)->min(10)->max(10000)->info('Maximum pages loaded into the sidebar page tree; a notice links to search when more exist.')->end()
                    ->end()
                ->end()
                ->arrayNode('ai')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('agent')->defaultValue('wiki_assistant')->cannotBeEmpty()->end()
                        ->booleanNode('context_injection')->defaultTrue()->info('Inject search hits into the system prompt (in addition to optional agent tools).')->end()
                        ->integerNode('max_context_pages')->defaultValue(8)->min(1)->max(25)->end()
                        ->integerNode('max_context_chars')->defaultValue(12000)->min(1000)->max(100000)->end()
                        ->scalarNode('system_prompt')->defaultNull()->end()
                        ->integerNode('http_timeout')->defaultValue(30)->min(1)->max(600)->info('Idle timeout and max duration (seconds) of the nowo_wiki.ai.http_client service; set it as http_client of your AI platform.')->end()
                    ->end()
                ->end()
                ->arrayNode('routes')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('index')->addDefaultsIfNotSet()->children()
                            ->scalarNode('path')->defaultValue('/tools/wiki')->end()
                            ->scalarNode('name')->defaultValue('nowo_wiki_index')->end()
                        ->end()->end()
                        ->arrayNode('space')->addDefaultsIfNotSet()->children()
                            ->scalarNode('path')->defaultValue('/tools/wiki/spaces/{spaceSlug}')->end()
                            ->scalarNode('name')->defaultValue('nowo_wiki_space')->end()
                        ->end()->end()
                        ->arrayNode('page_view')->addDefaultsIfNotSet()->children()
                            ->scalarNode('path')->defaultValue('/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}')->end()
                            ->scalarNode('name')->defaultValue('nowo_wiki_page_view')->end()
                        ->end()->end()
                        ->arrayNode('page_edit')->addDefaultsIfNotSet()->children()
                            ->scalarNode('path')->defaultValue('/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/edit')->end()
                            ->scalarNode('name')->defaultValue('nowo_wiki_page_edit')->end()
                        ->end()->end()
                        ->arrayNode('page_new')->addDefaultsIfNotSet()->children()
                            ->scalarNode('path')->defaultValue('/tools/wiki/spaces/{spaceSlug}/pages/new')->end()
                            ->scalarNode('name')->defaultValue('nowo_wiki_page_new')->end()
                        ->end()->end()
                        ->arrayNode('page_history')->addDefaultsIfNotSet()->children()
                            ->scalarNode('path')->defaultValue('/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/history')->end()
                            ->scalarNode('name')->defaultValue('nowo_wiki_page_history')->end()
                        ->end()->end()
                        ->arrayNode('page_diff')->addDefaultsIfNotSet()->children()
                            ->scalarNode('path')->defaultValue('/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/diff')->end()
                            ->scalarNode('name')->defaultValue('nowo_wiki_page_diff')->end()
                        ->end()->end()
                        ->arrayNode('page_archive')->addDefaultsIfNotSet()->children()
                            ->scalarNode('path')->defaultValue('/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/archive')->end()
                            ->scalarNode('name')->defaultValue('nowo_wiki_page_archive')->end()
                        ->end()->end()
                        ->arrayNode('search')->addDefaultsIfNotSet()->children()
                            ->scalarNode('path')->defaultValue('/tools/wiki/search')->end()
                            ->scalarNode('name')->defaultValue('nowo_wiki_search')->end()
                        ->end()->end()
                        ->arrayNode('ai_ask')->addDefaultsIfNotSet()->children()
                            ->scalarNode('path')->defaultValue('/tools/wiki/ask')->end()
                            ->scalarNode('name')->defaultValue('nowo_wiki_ai_ask')->end()
                        ->end()->end()
                        ->arrayNode('space_export')->addDefaultsIfNotSet()->children()
                            ->scalarNode('path')->defaultValue('/tools/wiki/spaces/{spaceSlug}/export')->end()
                            ->scalarNode('name')->defaultValue('nowo_wiki_space_export')->end()
                        ->end()->end()
                        ->arrayNode('space_import')->addDefaultsIfNotSet()->children()
                            ->scalarNode('path')->defaultValue('/tools/wiki/spaces/{spaceSlug}/import')->end()
                            ->scalarNode('name')->defaultValue('nowo_wiki_space_import')->end()
                        ->end()->end()
                        ->arrayNode('page_unarchive')->addDefaultsIfNotSet()->children()
                            ->scalarNode('path')->defaultValue('/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/unarchive')->end()
                            ->scalarNode('name')->defaultValue('nowo_wiki_page_unarchive')->end()
                        ->end()->end()
                        ->arrayNode('page_restore')->addDefaultsIfNotSet()->children()
                            ->scalarNode('path')->defaultValue('/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/revisions/{revisionNumber}/restore')->end()
                            ->scalarNode('name')->defaultValue('nowo_wiki_page_restore')->end()
                        ->end()->end()
                        ->arrayNode('attachment_upload')->addDefaultsIfNotSet()->children()
                            ->scalarNode('path')->defaultValue('/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/attachments')->end()
                            ->scalarNode('name')->defaultValue('nowo_wiki_attachment_upload')->end()
                        ->end()->end()
                        ->arrayNode('attachment_download')->addDefaultsIfNotSet()->children()
                            ->scalarNode('path')->defaultValue('/tools/wiki/spaces/{spaceSlug}/attachments/{attachmentId}')->end()
                            ->scalarNode('name')->defaultValue('nowo_wiki_attachment_download')->end()
                        ->end()->end()
                        ->arrayNode('page_delete')->addDefaultsIfNotSet()->children()
                            ->scalarNode('path')->defaultValue('/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/delete')->end()
                            ->scalarNode('name')->defaultValue('nowo_wiki_page_delete')->end()
                        ->end()->end()
                        ->arrayNode('space_trash')->addDefaultsIfNotSet()->children()
                            ->scalarNode('path')->defaultValue('/tools/wiki/spaces/{spaceSlug}/trash')->end()
                            ->scalarNode('name')->defaultValue('nowo_wiki_space_trash')->end()
                        ->end()->end()
                        ->arrayNode('trash_restore')->addDefaultsIfNotSet()->children()
                            ->scalarNode('path')->defaultValue('/tools/wiki/spaces/{spaceSlug}/trash/{batchId}/restore')->end()
                            ->scalarNode('name')->defaultValue('nowo_wiki_trash_restore')->end()
                        ->end()->end()
                        ->arrayNode('trash_purge')->addDefaultsIfNotSet()->children()
                            ->scalarNode('path')->defaultValue('/tools/wiki/spaces/{spaceSlug}/trash/{batchId}/purge')->end()
                            ->scalarNode('name')->defaultValue('nowo_wiki_trash_purge')->end()
                        ->end()->end()
                        ->arrayNode('page_move')->addDefaultsIfNotSet()->children()
                            ->scalarNode('path')->defaultValue('/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/move')->end()
                            ->scalarNode('name')->defaultValue('nowo_wiki_page_move')->end()
                        ->end()->end()
                        ->arrayNode('space_tree')->addDefaultsIfNotSet()->children()
                            ->scalarNode('path')->defaultValue('/tools/wiki/spaces/{spaceSlug}/tree/{action}')->end()
                            ->scalarNode('name')->defaultValue('nowo_wiki_space_tree')->end()
                        ->end()->end()
                        ->arrayNode('page_collab')->addDefaultsIfNotSet()->children()
                            ->scalarNode('path')->defaultValue('/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/collab/{action}')->end()
                            ->scalarNode('name')->defaultValue('nowo_wiki_page_collab')->end()
                        ->end()->end()
                    ->end()
                ->end()
                ->arrayNode('templates')
                    ->info('Deprecated since 1.10: use web_ui.layout_template and web_ui.templates.<page>.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('layout')
                            ->defaultNull()
                            ->setDeprecated(self::PACKAGE, '1.10', 'The "%path%.%node%" option is deprecated, use "nowo_wiki.web_ui.layout_template" instead.')
                        ->end()
                        ->scalarNode('index')
                            ->defaultNull()
                            ->setDeprecated(self::PACKAGE, '1.10', 'The "%path%.%node%" option is deprecated, use "nowo_wiki.web_ui.templates.index" instead.')
                        ->end()
                        ->scalarNode('space')
                            ->defaultNull()
                            ->setDeprecated(self::PACKAGE, '1.10', 'The "%path%.%node%" option is deprecated, use "nowo_wiki.web_ui.templates.space" instead.')
                        ->end()
                        ->scalarNode('page_view')
                            ->defaultNull()
                            ->setDeprecated(self::PACKAGE, '1.10', 'The "%path%.%node%" option is deprecated, use "nowo_wiki.web_ui.templates.page_view" instead.')
                        ->end()
                        ->scalarNode('page_edit')
                            ->defaultNull()
                            ->setDeprecated(self::PACKAGE, '1.10', 'The "%path%.%node%" option is deprecated, use "nowo_wiki.web_ui.templates.page_edit" instead.')
                        ->end()
                        ->scalarNode('page_history')
                            ->defaultNull()
                            ->setDeprecated(self::PACKAGE, '1.10', 'The "%path%.%node%" option is deprecated, use "nowo_wiki.web_ui.templates.page_history" instead.')
                        ->end()
                        ->scalarNode('page_diff')
                            ->defaultNull()
                            ->setDeprecated(self::PACKAGE, '1.10', 'The "%path%.%node%" option is deprecated, use "nowo_wiki.web_ui.templates.page_diff" instead.')
                        ->end()
                        ->scalarNode('search')
                            ->defaultNull()
                            ->setDeprecated(self::PACKAGE, '1.10', 'The "%path%.%node%" option is deprecated, use "nowo_wiki.web_ui.templates.search" instead.')
                        ->end()
                        ->scalarNode('ai_ask')
                            ->defaultNull()
                            ->setDeprecated(self::PACKAGE, '1.10', 'The "%path%.%node%" option is deprecated, use "nowo_wiki.web_ui.templates.ai_ask" instead.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('web_ui')
                    ->info('Wiki Web UI (REQ-UI-001 / REQ-UI-003): the pages extend the UiKit page scaffolds; the CSS stack, icons and row actions come from nowo_ui_kit.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('false: the nowo_wiki route loader registers no route.')
                            ->defaultTrue()
                        ->end()
                        ->scalarNode('path_prefix')
                            ->info('Prefix of every wiki route path (e.g. /admin). null = the deprecated route_prefix, else none.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('layout_template')
                            ->info('Layout the pages extend. null = nowo_ui_kit.layouts.admin. A pre-1.10 layout without the UiKit canonical blocks is wrapped in a deprecated bridge.')
                            ->defaultNull()
                        ->end()
                        ->arrayNode('templates')
                            ->info('Per-page template overrides (page id => template); null = the bundle template.')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('index')->defaultNull()->end()
                                ->scalarNode('space')->defaultNull()->end()
                                ->scalarNode('page_view')->defaultNull()->end()
                                ->scalarNode('page_edit')->defaultNull()->end()
                                ->scalarNode('page_history')->defaultNull()->end()
                                ->scalarNode('page_diff')->defaultNull()->end()
                                ->scalarNode('search')->defaultNull()->end()
                                ->scalarNode('ai_ask')->defaultNull()->end()
                                ->scalarNode('page_move')->defaultNull()->end()
                                ->scalarNode('trash')->defaultNull()->end()
                                ->scalarNode('trash_purge')->defaultNull()->end()
                            ->end()
                        ->end()
                        ->enumNode('css_framework')
                            ->values([null, 'bootstrap', 'bootstrap4', 'bootstrap5', 'tabler', 'tailwind', 'foundation', 'custom', 'none'])
                            ->defaultNull()
                            ->info('Deprecated since 1.10: set nowo_ui_kit.css_framework once for every bundle. Only seeds nowo_ui_kit.css_framework (and icon_set) when the host leaves them unset.')
                            ->setDeprecated(self::PACKAGE, '1.10', 'The "%path%.%node%" option is deprecated: set nowo_ui_kit.css_framework once for every bundle. It only seeds nowo_ui_kit.css_framework when the host leaves it unset.')
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('firewall')
                    ->defaultValue('main')
                    ->setDeprecated('nowo-tech/wiki-bundle', '1.6', 'The "%path%.%node%" option is deprecated and has no effect: access uses whichever secured firewall handles the wiki routes (see WikiTokenGuard). It will be removed in 2.0.')
                ->end()
            ->end();

        return $treeBuilder;
    }

    /**
     * Resolved roles per action: security.roles.<action>, else the deprecated security.<action>_roles, else the
     * default ({@see self::DEFAULT_ROLES}).
     *
     * @param array<string, mixed> $security processed `security` node
     *
     * @return array<string, list<string>> action => roles
     */
    public static function resolveRoles(array $security): array
    {
        $roles = [];
        foreach (self::DEFAULT_ROLES as $action => $default) {
            $value          = $security['roles'][$action] ?? $security[$action . '_roles'] ?? $default;
            $roles[$action] = array_values(array_filter(is_array($value) ? $value : [$value], static fn (mixed $role): bool => is_string($role) && $role !== ''));
        }

        return $roles;
    }

    /**
     * `security.roles.<action>`: list of roles, null = the deprecated key / the default.
     */
    private function rolesNode(string $action): VariableNodeDefinition
    {
        $node = new VariableNodeDefinition($action);
        $node
            ->info(sprintf('Roles granted the "%s" action (default %s). An explicit null (`~`) means no role, like [].', $action, implode(', ', self::DEFAULT_ROLES[$action])))
            // An explicit `~` grants nothing (as 1.9 did); only an absent key falls back to the default.
            ->treatNullLike([])
            ->defaultNull()
            ->validate()
                ->ifTrue(static fn (mixed $value): bool => $value !== null && !self::isRoleList($value))
                ->thenInvalid('Expected a list of role names, got %s.')
            ->end();

        return $node;
    }

    /**
     * Whether a value is a list of role names (strings).
     */
    public static function isRoleList(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $role) {
            if (!is_string($role)) {
                return false;
            }
        }

        return true;
    }
}
