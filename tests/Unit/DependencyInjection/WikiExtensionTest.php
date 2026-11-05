<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use LogicException;
use Nowo\MilkdownEditorBundle\Form\MilkdownEditorType;
use Nowo\MilkdownEditorBundle\Markdown\MilkdownMarkdownRenderer;
use Nowo\WikiBundle\Ai\NullWikiAiAssistant;
use Nowo\WikiBundle\Ai\SymfonyAiWikiAssistant;
use Nowo\WikiBundle\Ai\Tool\WikiKnowledgeSearchTool;
use Nowo\WikiBundle\Ai\WikiAiAssistantInterface;
use Nowo\WikiBundle\Avatar\NullWikiUserAvatarResolver;
use Nowo\WikiBundle\Avatar\WikiAvatars;
use Nowo\WikiBundle\Avatar\WikiUserAvatarResolverInterface;
use Nowo\WikiBundle\Collaboration\DoctrineWikiCollabStateStore;
use Nowo\WikiBundle\Collaboration\MercureWikiCollabPublisher;
use Nowo\WikiBundle\Collaboration\MercureWikiCollabSubscriberAuthorizer;
use Nowo\WikiBundle\Collaboration\WikiCollabPublisherInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabSaveListener;
use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Nowo\WikiBundle\Command\WikiSearchBackfillTextCommand;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Comment\WikiCommentsPanel;
use Nowo\WikiBundle\DependencyInjection\Configuration;
use Nowo\WikiBundle\DependencyInjection\WikiExtension;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Doctrine\WikiMetadataListener;
use Nowo\WikiBundle\History\WikiAuthorLabel;
use Nowo\WikiBundle\History\WikiHistoryThinner;
use Nowo\WikiBundle\History\WikiHistoryTimeline;
use Nowo\WikiBundle\Interchange\WikiDocumentImporter;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Markdown\Milkdown\MilkdownWikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownIconHydrator;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownPageResolver;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownRichContent;
use Nowo\WikiBundle\Markdown\WikiMarkdownExtensionInterface;
use Nowo\WikiBundle\Markdown\WikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface;
use Nowo\WikiBundle\Panel\SessionWikiPanelStateStore;
use Nowo\WikiBundle\Panel\WikiPanelStateStoreInterface;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiCommentRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiRevisionLabelRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiTrashRepository;
use Nowo\WikiBundle\Repository\WikiCommentRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiRevisionLabelRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiTrashRepositoryInterface;
use Nowo\WikiBundle\Security\NullWikiTeamMembershipResolver;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;
use Nowo\WikiBundle\Security\WikiTeamMembershipResolverInterface;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Service\WikiPageMover;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Trash\WikiTrashService;
use Nowo\WikiBundle\Tree\SessionWikiTreeStateStore;
use Nowo\WikiBundle\Tree\WikiTreeStateStoreInterface;
use Nowo\WikiBundle\Ui\WikiPageFactory;
use Nowo\WikiBundle\Ui\WikiPages;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\AI\Agent\AgentInterface;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\DependencyInjection\TwigExtension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mercure\HubInterface;
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

    public function testSanitizerAllowlistReachesTheSanitizer(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User', 'sanitizer' => ['extra_allowed_elements' => ['details' => ['open']], 'extra_allowed_attributes' => ['data-callout']]]], $container);

        $definition = $container->getDefinition(WikiHtmlSanitizer::class);
        self::assertSame(['details' => ['open']], $definition->getArgument('$extraAllowedElements'));
        self::assertSame(['data-callout'], $definition->getArgument('$extraAllowedAttributes'));
    }

    public function testRegistersTheMarkdownRendererAndItsExtensionTag(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User', 'editor' => ['type' => 'markdown']]], $container);

        self::assertSame(WikiMarkdownRenderer::class, (string) $container->getAlias(WikiMarkdownRendererInterface::class));
        self::assertTrue($container->hasDefinition(WikiMarkdownRenderer::class));
        self::assertSame('markdown', $container->getParameter('nowo_wiki.editor.type'));
        self::assertSame('%nowo_wiki.editor.type%', $container->getDefinition(WikiDocumentImporter::class)->getArgument('$editorType'));
        self::assertArrayHasKey('nowo_wiki.markdown_extension', $container->getAutoconfiguredInstanceof()[WikiMarkdownExtensionInterface::class]->getTags());
    }

    public function testMilkdownEditorWiresTheBridge(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([[
            'user_class' => 'App\\Entity\\User',
            'editor'     => ['type' => 'milkdown'],
            'sanitizer'  => ['extra_allowed_elements' => ['details' => ['data-x']], 'extra_allowed_attributes' => ['data-callout', 'role']],
        ]], $container);

        self::assertSame(MilkdownWikiMarkdownRenderer::class, (string) $container->getAlias(WikiMarkdownRendererInterface::class));
        self::assertSame(MilkdownEditorType::class, $container->getParameter('nowo_wiki.editor')['markdown_form_type']);
        self::assertSame('milkdown', $container->getParameter('nowo_wiki.editor.type'));
        self::assertTrue($container->getDefinition(WikiMarkdownConverter::class)->getArgument('$dialect'));
        self::assertTrue($container->hasDefinition(WikiMilkdownIconHydrator::class));
        self::assertTrue($container->hasDefinition(WikiMilkdownRichContent::class));
        self::assertSame(WikiMilkdownPageResolver::class, (string) $container->getDefinition(MilkdownWikiMarkdownRenderer::class)->getArgument('$pages'));

        $sanitizer = $container->getDefinition(WikiHtmlSanitizer::class);
        self::assertSame(['details' => ['data-x', 'open'], 'summary' => []], $sanitizer->getArgument('$extraAllowedElements'));
        self::assertSame(['data-callout', 'role', 'aria-label', 'data-math', 'data-directive', 'data-icon'], $sanitizer->getArgument('$extraAllowedAttributes'));
    }

    public function testMilkdownEditorTakesTheHostPageResolverAndFormType(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([[
            'user_class' => 'App\\Entity\\User',
            'editor'     => ['type' => 'milkdown', 'markdown_form_type' => TextareaType::class, 'milkdown' => ['page_resolver' => 'app.wiki_pages']],
        ]], $container);

        self::assertSame(TextareaType::class, $container->getParameter('nowo_wiki.editor')['markdown_form_type']);
        self::assertSame('app.wiki_pages', (string) $container->getDefinition(MilkdownWikiMarkdownRenderer::class)->getArgument('$pages'));
    }

    public function testMilkdownEditorNeedsTheMilkdownBundle(): void
    {
        $method = new ReflectionMethod(WikiExtension::class, 'milkdownDefaults');

        self::assertSame(['editor' => ['type' => 'tiptap']], $method->invoke(new WikiExtension(), ['editor' => ['type' => 'tiptap']], 'Missing\\Renderer'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('nowo-tech/milkdown-editor-bundle');
        $method->invoke(new WikiExtension(), ['editor' => ['type' => 'milkdown']], 'Missing\\Renderer');
    }

    public function testCopyMarkdownNeedsMilkdown15(): void
    {
        $method = new ReflectionMethod(WikiExtension::class, 'milkdownDefaults');
        $config = ['editor' => ['type' => 'milkdown', 'milkdown' => ['copy_markdown' => true]], 'sanitizer' => ['extra_allowed_elements' => [], 'extra_allowed_attributes' => []]];

        $result = $method->invoke(new WikiExtension(), $config);
        self::assertTrue($result['editor']['milkdown']['copy_markdown']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('copy_markdown needs nowo-tech/milkdown-editor-bundle >= 1.5');
        $method->invoke(new WikiExtension(), $config, MilkdownMarkdownRenderer::class, 'Missing\\Twig');
    }

    public function testOtherEditorsKeepTheDefaultConverterAndNoBridge(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User', 'editor' => ['type' => 'markdown']]], $container);

        self::assertFalse($container->hasDefinition(MilkdownWikiMarkdownRenderer::class));
        self::assertSame([], $container->getDefinition(WikiMarkdownConverter::class)->getArguments());
        self::assertNull($container->getParameter('nowo_wiki.editor')['markdown_form_type']);
    }

    public function testTheTrashIsOffByDefaultAndWiresItsServicesWhenOn(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User']], $container);
        /** @var array<string, mixed> $routes */
        $routes = $container->getParameter('nowo_wiki.routes');
        foreach (['page_delete', 'space_trash', 'trash_restore', 'trash_purge'] as $key) {
            self::assertArrayNotHasKey($key, $routes);
        }
        self::assertFalse($container->hasDefinition(WikiTrashService::class) && !$container->getDefinition(WikiTrashService::class)->hasTag('container.excluded'));
        self::assertFalse($container->getDefinition(WikiMetadataListener::class)->getArgument('$trashEnabled'));

        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User', 'table_prefix' => 'kb', 'trash' => ['enabled' => true, 'retention_days' => 7]]], $container);
        /** @var array<string, mixed> $routes */
        $routes = $container->getParameter('nowo_wiki.routes');
        self::assertArrayHasKey('trash_purge', $routes);
        $service = $container->getDefinition(WikiTrashService::class);
        self::assertSame(7, $service->getArgument('$retentionDays'));
        self::assertSame(DoctrineOrmWikiTrashRepository::class, (string) $container->getAlias(WikiTrashRepositoryInterface::class));
        self::assertSame('kb_page_trash', $container->getDefinition(WikiMetadataListener::class)->getArgument('$trashTableName'));
        self::assertTrue($container->getDefinition(WikiMetadataListener::class)->getArgument('$trashEnabled'));
    }

    public function testTheHistoryTimelineIsOffByDefaultAndWiresItsServicesWhenOn(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User']], $container);
        /** @var array<string, mixed> $routes */
        $routes = $container->getParameter('nowo_wiki.routes');
        self::assertArrayNotHasKey('page_version_save', $routes);
        self::assertArrayNotHasKey('page_version_copy', $routes);
        self::assertFalse($container->hasDefinition(WikiHistoryTimeline::class) && !$container->getDefinition(WikiHistoryTimeline::class)->hasTag('container.excluded'));
        self::assertFalse($container->getDefinition(WikiMetadataListener::class)->getArgument('$historyTimeline'));

        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User', 'table_prefix' => 'kb', 'history' => ['timeline' => true, 'session_minutes' => 15, 'show_names' => true, 'retention_days' => 90]]], $container);
        /** @var array<string, mixed> $routes */
        $routes = $container->getParameter('nowo_wiki.routes');
        self::assertArrayHasKey('page_version_save', $routes);
        self::assertArrayHasKey('page_version_copy', $routes);
        self::assertSame(15, $container->getDefinition(WikiHistoryTimeline::class)->getArgument('$sessionMinutes'));
        self::assertTrue($container->getDefinition(WikiAuthorLabel::class)->getArgument('$showNames'));
        self::assertSame(90, $container->getDefinition(WikiHistoryThinner::class)->getArgument('$retentionDays'));
        self::assertSame(DoctrineOrmWikiRevisionLabelRepository::class, (string) $container->getAlias(WikiRevisionLabelRepositoryInterface::class));
        self::assertSame('kb_revision_labels', $container->getDefinition(WikiMetadataListener::class)->getArgument('$revisionLabelsTableName'));
        self::assertTrue($container->getDefinition(WikiMetadataListener::class)->getArgument('$historyTimeline'));
    }

    public function testTheTrashSwitchMustBeALiteralBoolean(): void
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag());
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        $container->registerExtension(new WikiExtension());
        $container->loadFromExtension('nowo_wiki', ['user_class' => 'App\\Entity\\User', 'trash' => ['enabled' => '%env(bool:WIKI_TRASH)%']]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('nowo_wiki.trash.enabled must be a literal boolean');
        (new MergeExtensionConfigurationPass())->process($container);
    }

    public function testTheTreeIsOffByDefaultWithoutItsRoutesAndServices(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User']], $container);

        /** @var array<string, mixed> $routes */
        $routes = $container->getParameter('nowo_wiki.routes');
        self::assertArrayNotHasKey('page_move', $routes);
        self::assertArrayNotHasKey('space_tree', $routes);
        // Excluded from the resource scan: at most a `container.excluded` placeholder, never autowired.
        self::assertTrue(!$container->hasDefinition(WikiPageMover::class) || $container->getDefinition(WikiPageMover::class)->hasTag('container.excluded'));
        self::assertFalse($container->has(WikiTreeStateStoreInterface::class));
    }

    public function testTheTreeWiresTheMoverAndTheSessionStore(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User', 'tree' => ['enabled' => true, 'max_depth' => 4]]], $container);

        /** @var array<string, array{path: string, name: string}> $routes */
        $routes = $container->getParameter('nowo_wiki.routes');
        self::assertSame('nowo_wiki_page_move', $routes['page_move']['name']);
        self::assertSame('/tools/wiki/spaces/{spaceSlug}/tree/{action}', $routes['space_tree']['path']);
        $mover = $container->getDefinition(WikiPageMover::class);
        self::assertSame(4, $mover->getArgument('$maxDepth'));
        self::assertSame(DoctrineOrmWikiPageRepository::class, (string) $mover->getArgument('$writer'));
        self::assertSame(SessionWikiTreeStateStore::class, (string) $container->getAlias(WikiTreeStateStoreInterface::class));
        self::assertSame('request_stack', (string) $container->getDefinition(SessionWikiTreeStateStore::class)->getArgument('$requestStack'));
    }

    public function testTheTreeTakesTheHostStateStore(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User', 'tree' => ['enabled' => true, 'state_store' => 'app.tree_state']]], $container);

        self::assertSame('app.tree_state', (string) $container->getAlias(WikiTreeStateStoreInterface::class));
        self::assertFalse($container->hasDefinition(SessionWikiTreeStateStore::class));
    }

    public function testTheTreeSwitchMustBeALiteralBoolean(): void
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag());
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        $container->registerExtension(new WikiExtension());
        $container->loadFromExtension('nowo_wiki', ['user_class' => 'App\\Entity\\User', 'tree' => ['enabled' => '%env(bool:WIKI_TREE)%']]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('nowo_wiki.tree.enabled must be a literal boolean');
        (new MergeExtensionConfigurationPass())->process($container);
    }

    public function testCollaborationWiresTheMercureDefaults(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([[
            'user_class'    => 'App\\Entity\\User',
            'editor'        => ['type' => 'milkdown'],
            'collaboration' => ['enabled' => true, 'rate_limiter' => 'limiter.wiki_collab', 'max_update_bytes' => 4096],
        ]], $container);

        $service = $container->getDefinition(WikiCollabService::class);
        self::assertSame(DoctrineWikiCollabStateStore::class, (string) $service->getArgument('$store'));
        self::assertSame(MercureWikiCollabPublisher::class, (string) $service->getArgument('$publisher'));
        self::assertSame(MercureWikiCollabSubscriberAuthorizer::class, (string) $service->getArgument('$authorizer'));
        self::assertSame('limiter.wiki_collab', (string) $service->getArgument('$rateLimiter'));
        self::assertSame(4096, $service->getArgument('$options')['max_update_bytes']);
        self::assertSame(HubInterface::class, (string) $container->getDefinition(MercureWikiCollabPublisher::class)->getArgument('$hub'));
        self::assertNull($container->getDefinition(MercureWikiCollabSubscriberAuthorizer::class)->getArgument('$hub'));
        self::assertSame([['event' => 'nowo_wiki.page.saved']], $container->getDefinition(WikiCollabSaveListener::class)->getTag('kernel.event_listener'));
    }

    public function testCollaborationTakesHostServicesAndANamedHub(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([[
            'user_class'    => 'App\\Entity\\User',
            'editor'        => ['type' => 'milkdown'],
            'collaboration' => ['enabled' => true, 'store' => 'app.store', 'mercure_hub' => 'wiki', 'rate_limiter' => 'limiter.x'],
        ]], $container);

        $service = $container->getDefinition(WikiCollabService::class);
        self::assertSame('app.store', (string) $service->getArgument('$store'));
        self::assertSame('mercure.hub.wiki', (string) $container->getDefinition(MercureWikiCollabPublisher::class)->getArgument('$hub'));
        self::assertSame('wiki', $container->getDefinition(MercureWikiCollabSubscriberAuthorizer::class)->getArgument('$hub'));
    }

    public function testCollaborationIsOffByDefaultAndNeedsMilkdownAndMercure(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User']], $container);
        self::assertFalse($container->hasDefinition(WikiCollabService::class));

        $method = new ReflectionMethod(WikiExtension::class, 'registerCollaboration');
        $config = ['editor' => ['type' => 'milkdown'], 'collaboration' => ['enabled' => true, 'publisher' => null, 'subscriber_authorizer' => null, 'rate_limiter' => 'limiter.x']];
        try {
            $method->invoke(new WikiExtension(), new ContainerBuilder(), ['collaboration' => ['rate_limiter' => null] + $config['collaboration']] + $config, HubInterface::class);
            self::fail('accepted without a rate limiter');
        } catch (LogicException $exception) {
            self::assertStringContainsString('collaboration.rate_limiter', $exception->getMessage());
        }

        try {
            $method->invoke(new WikiExtension(), new ContainerBuilder(), $config, 'Missing\\Hub');
            self::fail('accepted without mercure');
        } catch (LogicException $exception) {
            self::assertStringContainsString('symfony/mercure-bundle', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('editor.type "milkdown"');
        $bare = new ContainerBuilder();
        $bare->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User', 'collaboration' => ['enabled' => true]]], $bare);
    }

    public function testCommentsAreOffByDefaultWithoutRoutesTablesOrServices(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User']], $container);

        /** @var array<string, mixed> $routes */
        $routes = $container->getParameter('nowo_wiki.routes');
        self::assertArrayNotHasKey('page_comments', $routes);
        self::assertArrayNotHasKey('space_panel_state', $routes);
        self::assertFalse($container->hasDefinition(WikiCommentService::class));
        self::assertFalse($container->hasDefinition(WikiCommentsPanel::class));
        self::assertFalse($container->getDefinition(WikiMetadataListener::class)->getArgument('$commentsEnabled'));
        self::assertSame('wiki', $container->getDefinition(WikiMetadataListener::class)->getArgument('$commentTablePrefix'));
        // 1.16: avatars are always wired (a null resolver by alias), and the author labels with them.
        self::assertSame(NullWikiUserAvatarResolver::class, (string) $container->getAlias(WikiUserAvatarResolverInterface::class));
        self::assertSame([['method' => 'reset']], $container->getDefinition(WikiAvatars::class)->getTag('kernel.reset'));
        self::assertSame(WikiAvatars::class, (string) $container->getDefinition(WikiAuthorLabel::class)->getArgument('$avatars'));
        self::assertFalse($container->getDefinition(WikiAuthorLabel::class)->getArgument('$showNames'));
        self::assertSame(WikiEntityManagerProvider::class, (string) $container->getDefinition(WikiSearchBackfillTextCommand::class)->getArgument('$entityManager'));
    }

    public function testCommentsWireTheirServicesAndRoutes(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([[
            'user_class'    => 'App\\Entity\\User',
            'table_prefix'  => 'kb',
            'editor'        => ['type' => 'milkdown'],
            'collaboration' => ['enabled' => true, 'rate_limiter' => 'limiter.wiki_collab'],
            'comments'      => ['enabled' => true, 'rate_limiter' => 'limiter.wiki_comments', 'page_rate_limiter' => 'limiter.wiki_comments_page', 'shortcut' => 'Alt+Shift+C', 'limits' => ['threads_per_page' => 50]],
            'trash'         => ['enabled' => true],
        ]], $container);

        /** @var array<string, array{path: string, name: string}> $routes */
        $routes = $container->getParameter('nowo_wiki.routes');
        self::assertSame('/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/comments/{path}', $routes['page_comments']['path']);
        self::assertSame('nowo_wiki_space_panel_state', $routes['space_panel_state']['name']);
        self::assertTrue($container->getDefinition(WikiMetadataListener::class)->getArgument('$commentsEnabled'));
        self::assertSame('kb', $container->getDefinition(WikiMetadataListener::class)->getArgument('$commentTablePrefix'));
        self::assertSame(DoctrineOrmWikiCommentRepository::class, (string) $container->getAlias(WikiCommentRepositoryInterface::class));
        self::assertSame(SessionWikiPanelStateStore::class, (string) $container->getAlias(WikiPanelStateStoreInterface::class));
        $service = $container->getDefinition(WikiCommentService::class);
        self::assertSame('limiter.wiki_comments', (string) $service->getArgument('$rateLimiter'));
        self::assertSame('limiter.wiki_comments_page', (string) $service->getArgument('$pageRateLimiter'));
        self::assertSame(50, $service->getArgument('$limits')['threads_per_page']);
        self::assertSame(20, $service->getArgument('$limits')['reactions_per_comment']);
        // EI-L5: the trash purge deletes comments explicitly.
        self::assertSame(WikiCommentRepositoryInterface::class, (string) $container->getDefinition(DoctrineOrmWikiTrashRepository::class)->getArgument('$comments'));
        self::assertSame(WikiCollabService::class, (string) $service->getArgument('$collab'));
        self::assertSame(WikiCollabPublisherInterface::class, (string) $service->getArgument('$publisher'));
        self::assertSame(MercureWikiCollabPublisher::class, (string) $container->getAlias(WikiCollabPublisherInterface::class));
        self::assertSame('Alt+Shift+C', $container->getDefinition(WikiCommentsPanel::class)->getArgument('$shortcut'));
        // The page source stays with editors unless the host opts in.
        self::assertFalse($container->getDefinition(WikiCommentsPanel::class)->getArgument('$readViewSource'));

        // Without co-editing: no live updates; a host panel state store.
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User', 'editor' => ['type' => 'milkdown'], 'comments' => ['enabled' => true, 'rate_limiter' => 'limiter.c', 'state_store' => 'app.panel_state', 'read_view_source' => true]]], $container);
        self::assertTrue($container->getDefinition(WikiCommentsPanel::class)->getArgument('$readViewSource'));
        self::assertNull($container->getDefinition(WikiCommentService::class)->getArgument('$collab'));
        self::assertNull($container->getDefinition(WikiCommentService::class)->getArgument('$publisher'));
        self::assertNull($container->getDefinition(WikiCommentService::class)->getArgument('$pageRateLimiter'));
        self::assertSame('app.panel_state', (string) $container->getAlias(WikiPanelStateStoreInterface::class));
    }

    public function testCommentsNeedMilkdown16ARateLimiterAndThePathPlaceholder(): void
    {
        $method = new ReflectionMethod(WikiExtension::class, 'registerComments');
        $config = (new Processor())->processConfiguration(new Configuration(), [['user_class' => 'App\\Entity\\User', 'editor' => ['type' => 'milkdown'], 'comments' => ['enabled' => true, 'rate_limiter' => 'limiter.c']]]);
        $cases  = [
            'editor.type "milkdown"'        => [['editor' => ['type' => 'tiptap'] + $config['editor']] + $config, null],
            'milkdown-editor-bundle >= 1.6' => [$config, 'Missing\\Anchor'],
            'comments.rate_limiter'         => [['comments' => ['rate_limiter' => null] + $config['comments']] + $config, null],
            'must end with "/{path}"'       => [['routes' => ['page_comments' => ['path' => '/c', 'name' => 'c']] + $config['routes']] + $config, null],
        ];
        foreach ($cases as $message => [$case, $anchor]) {
            try {
                $anchor === null
                    ? $method->invoke(new WikiExtension(), new ContainerBuilder(), $case, new Reference('em'))
                    : $method->invoke(new WikiExtension(), new ContainerBuilder(), $case, new Reference('em'), $anchor);
                self::fail('Accepted: ' . $message);
            } catch (LogicException $exception) {
                self::assertStringContainsString($message, $exception->getMessage());
            }
        }
    }

    public function testTheReadViewSourceSwitchMustBeALiteralBoolean(): void
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag());
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        $container->registerExtension(new WikiExtension());
        $container->loadFromExtension('nowo_wiki', ['user_class' => 'App\\Entity\\User', 'comments' => ['read_view_source' => '%env(bool:WIKI_SOURCE)%']]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('nowo_wiki.comments.read_view_source must be a literal boolean');
        (new MergeExtensionConfigurationPass())->process($container);
    }

    public function testTheCommentsSwitchMustBeALiteralBoolean(): void
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag());
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        $container->registerExtension(new WikiExtension());
        $container->loadFromExtension('nowo_wiki', ['user_class' => 'App\\Entity\\User', 'comments' => ['enabled' => '%env(bool:WIKI_COMMENTS)%']]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('nowo_wiki.comments.enabled must be a literal boolean');
        (new MergeExtensionConfigurationPass())->process($container);
    }

    public function testCollaborationWithOwnPublisherAndAuthorizerNeedsNoMercure(): void
    {
        $container = new ContainerBuilder();
        $method    = new ReflectionMethod(WikiExtension::class, 'registerCollaboration');
        $collab    = (new Processor())->processConfiguration(new Configuration(), [['user_class' => 'App\\Entity\\User', 'collaboration' => ['enabled' => true, 'publisher' => 'app.pub', 'subscriber_authorizer' => 'app.auth', 'rate_limiter' => 'limiter.x']]])['collaboration'];
        $method->invoke(new WikiExtension(), $container, ['editor' => ['type' => 'milkdown'], 'collaboration' => $collab], 'Missing\\Hub');

        self::assertSame('app.pub', (string) $container->getDefinition(WikiCollabService::class)->getArgument('$publisher'));
        self::assertSame('app.auth', (string) $container->getDefinition(WikiCollabService::class)->getArgument('$authorizer'));
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
        self::assertSame(['$extraAllowedElements' => [], '$extraAllowedAttributes' => []], $sanitizer->getArguments());
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
        self::assertSame(WikiPageFactory::TAB_ICONS, $container->getDefinition(WikiPageFactory::class)->getArgument('$tabIcons'));
    }

    public function testTabIconsReachThePageFactory(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([[
            'user_class' => 'App\\Entity\\User',
            'web_ui'     => ['tab_icons' => ['edit' => 'bi:pencil', 'history' => null]],
        ]], $container);

        self::assertSame(['page' => 'tabler:file-text', 'edit' => 'bi:pencil', 'history' => null], $container->getDefinition(WikiPageFactory::class)->getArgument('$tabIcons'));
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

    public function testSecuritySwitchesMustBeLiteralBooleans(): void
    {
        foreach ([['security' => ['allow_unauthenticated' => '%env(bool:WIKI_PUBLIC)%']], ['web_ui' => ['enabled' => '%env(bool:WIKI_UI)%']], ['collaboration' => ['enabled' => '%env(bool:WIKI_COLLAB)%']]] as $config) {
            $container = new ContainerBuilder();
            $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);

            try {
                (new WikiExtension())->load([['user_class' => 'App\\Entity\\User'] + $config], $container);
                self::fail('A placeholder must be refused.');
            } catch (InvalidConfigurationException $exception) {
                // The tree refuses a plain string; an env placeholder that passes the tree is refused by load().
                self::assertMatchesRegularExpression('/literal boolean|Expected "bool"/', $exception->getMessage());
            }
        }
    }

    public function testAnEnvPlaceholderThatPassesTheTreeIsRefused(): void
    {
        // In a real kernel an env placeholder passes the boolean node and reaches load() as a string.
        $literalBool = new ReflectionMethod(WikiExtension::class, 'literalBool');
        self::assertTrue($literalBool->invoke(new WikiExtension(), true, 'web_ui.enabled'));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('nowo_wiki.security.allow_unauthenticated must be a literal boolean');
        $literalBool->invoke(new WikiExtension(), 'env_0123456789abcdef_bool_WIKI_PUBLIC_0123456789abcdef', 'security.allow_unauthenticated');
    }

    public function testCollaborationEnabledFromAnEnvPlaceholderIsRefused(): void
    {
        // DA-M1: in a real kernel the placeholder passes the tree; services and schema listener would disagree.
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag());
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        $container->registerExtension(new WikiExtension());
        $container->loadFromExtension('nowo_wiki', ['user_class' => 'App\\Entity\\User', 'collaboration' => ['enabled' => '%env(bool:WIKI_COLLAB)%']]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('nowo_wiki.collaboration.enabled must be a literal boolean');
        (new MergeExtensionConfigurationPass())->process($container);
    }

    public function testAllowUnauthenticatedIsRefusedInProduction(): void
    {
        foreach ([['prod', true, false], ['staging', false, false], ['test', false, true], ['dev', true, true]] as [$environment, $debug, $allowed]) {
            $container = new ContainerBuilder();
            $container->setParameter('kernel.environment', $environment);
            $container->setParameter('kernel.debug', $debug);

            try {
                (new WikiExtension())->load([['user_class' => 'App\\Entity\\User', 'security' => ['allow_unauthenticated' => true]]], $container);
                self::assertTrue($allowed, $environment . ' must refuse allow_unauthenticated');
            } catch (InvalidConfigurationException $exception) {
                self::assertFalse($allowed, $environment . ': ' . $exception->getMessage());
                self::assertStringContainsString('dev/demo switch', $exception->getMessage());
            }
        }
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
                // 1.12: no Bootstrap field classes; the host form theme decides.
                self::assertSame(['alias', 'translation_domain'], array_keys($cfg['profiles']['wiki']));
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

    public function testAHostAllowedIdLogsADeprecationOnce(): void
    {
        foreach ([
            'global'     => ['extra_allowed_attributes' => ['data-x', 'ID', 'id']],
            'on element' => ['extra_allowed_elements' => ['section' => ['id']]],
        ] as $case => $sanitizer) {
            $deprecations = [];
            set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
                $deprecations[] = $message;

                return true;
            }, E_USER_DEPRECATED);

            try {
                (new WikiExtension())->load([['user_class' => 'App\\Entity\\User', 'sanitizer' => $sanitizer]], $this->hostIdContainer());
            } finally {
                restore_error_handler();
            }

            $hostId = array_values(array_filter($deprecations, static fn (string $m): bool => str_contains($m, 'Allowing "id"')));
            self::assertCount(1, $hostId, $case);
            self::assertStringContainsString('1.16.2', $hostId[0]);
        }

        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, E_USER_DEPRECATED);

        try {
            (new WikiExtension())->load([['user_class' => 'App\\Entity\\User', 'sanitizer' => ['extra_allowed_attributes' => ['data-id'], 'extra_allowed_elements' => ['details' => ['open']]]]], $this->hostIdContainer());
        } finally {
            restore_error_handler();
        }

        self::assertSame([], array_values(array_filter($deprecations, static fn (string $m): bool => str_contains($m, 'Allowing "id"'))));
    }

    private function hostIdContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);

        return $container;
    }
}
