<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\WikiBundle\Ai\NullWikiAiAssistant;
use Nowo\WikiBundle\Ai\WikiAiAnswer;
use Nowo\WikiBundle\Ai\WikiAiAssistantInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabPublisherInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Nowo\WikiBundle\Collaboration\WikiCollabSubscriberAuthorizerInterface;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Comment\WikiCommentsPanel;
use Nowo\WikiBundle\Controller\WikiManageController;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiInterchangeFormat;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiQuotaCheckEvent;
use Nowo\WikiBundle\History\WikiAuthorLabel;
use Nowo\WikiBundle\History\WikiHistoryTimeline;
use Nowo\WikiBundle\Interchange\WikiArchiveHelper;
use Nowo\WikiBundle\Interchange\WikiDocumentExporter;
use Nowo\WikiBundle\Interchange\WikiDocumentImporter;
use Nowo\WikiBundle\Interchange\WikiDocumentTreeReader;
use Nowo\WikiBundle\Interchange\WikiFormatDetector;
use Nowo\WikiBundle\Interchange\WikiFrontMatterParser;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Markdown\WikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface;
use Nowo\WikiBundle\Markdown\WikiRevisionBodyRenderer;
use Nowo\WikiBundle\Markdown\WikiViewerMarkdownRendererInterface;
use Nowo\WikiBundle\Panel\SessionWikiPanelStateStore;
use Nowo\WikiBundle\Repository\WikiCommentRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiRevisionLabelRepositoryInterface;
use Nowo\WikiBundle\Routing\WikiTenantPaths;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiPageTreeBuilder;
use Nowo\WikiBundle\Service\WikiQuotaGate;
use Nowo\WikiBundle\Service\WikiRevisionDiffService;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\InMemoryCollabStateStore;
use Nowo\WikiBundle\Tests\Support\WikiControllerContainerBuilder;
use Nowo\WikiBundle\Ui\WikiPageFactory;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Translation\TranslatableMessage;
use ZipArchive;

use function array_column;
use function array_diff;
use function array_keys;
use function array_slice;
use function array_values;

final class WikiManageControllerTest extends TestCase
{
    /** @var array<string, array{path: string, name: string}> */
    private array $routes;

    /** @var array<string, string> */
    private array $templates;

    protected function setUp(): void
    {
        $this->routes = [
            'index'        => ['path' => '/tools/wiki', 'name' => 'nowo_wiki_index'],
            'space'        => ['path' => '/tools/wiki/spaces/{spaceSlug}', 'name' => 'nowo_wiki_space'],
            'page_view'    => ['path' => '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}', 'name' => 'nowo_wiki_page_view'],
            'page_edit'    => ['path' => '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/edit', 'name' => 'nowo_wiki_page_edit'],
            'page_new'     => ['path' => '/tools/wiki/spaces/{spaceSlug}/pages/new', 'name' => 'nowo_wiki_page_new'],
            'page_history' => ['path' => '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/history', 'name' => 'nowo_wiki_page_history'],
            'page_diff'    => ['path' => '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/diff', 'name' => 'nowo_wiki_page_diff'],
            'page_archive' => ['path' => '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/archive', 'name' => 'nowo_wiki_page_archive'],
            'search'       => ['path' => '/tools/wiki/search', 'name' => 'nowo_wiki_search'],
            'ai_ask'       => ['path' => '/tools/wiki/ask', 'name' => 'nowo_wiki_ai_ask'],
            'space_export' => ['path' => '/tools/wiki/spaces/{spaceSlug}/export', 'name' => 'nowo_wiki_space_export'],
            'space_import' => ['path' => '/tools/wiki/spaces/{spaceSlug}/import', 'name' => 'nowo_wiki_space_import'],
        ];
        $this->templates = [
            'layout'       => '@NowoWikiBundle/layout.html.twig',
            'index'        => '@NowoWikiBundle/manage/index.html.twig',
            'space'        => '@NowoWikiBundle/manage/space.html.twig',
            'page_view'    => '@NowoWikiBundle/manage/page_view.html.twig',
            'page_edit'    => '@NowoWikiBundle/manage/page_edit.html.twig',
            'page_history' => '@NowoWikiBundle/manage/page_history.html.twig',
            'page_diff'    => '@NowoWikiBundle/manage/page_diff.html.twig',
            'search'       => '@NowoWikiBundle/manage/search.html.twig',
            'ai_ask'       => '@NowoWikiBundle/manage/ai_ask.html.twig',
        ];
    }

    public function testIndexListsSpaces(): void
    {
        $user  = new TestUser();
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(spaceAccessResolver: $resolver);
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->index();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('@NowoWikiBundle/manage/index.html.twig', (string) $response->getContent());
    }

    public function testIndexHasNoPaginationWhenEverySpaceFitsOnOnePage(): void
    {
        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1')]);

        $twig       = $this->capturingTwig();
        $controller = $this->controller(spaceAccessResolver: $resolver);
        WikiControllerContainerBuilder::bind($controller, new TestUser(), ['twig' => $twig]);

        $controller->index();
        self::assertInstanceOf(ListPage::class, $twig->context['page']);
        self::assertNull($twig->context['page']->pagination);
    }

    public function testSearchSpaceIsHiddenWhenTenantOnly(): void
    {
        $space    = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);
        $tenants = [['path' => '/projects/{spaceSlug}/wiki', 'name_prefix' => 'app_', 'slug_requirement' => '[a-z]+']];

        foreach ([[false, 'choice'], [true, 'hidden']] as [$tenantOnly, $prefix]) {
            $twig       = $this->capturingTwig();
            $controller = $this->controller(spaceAccessResolver: $resolver, tenantPaths: new WikiTenantPaths($tenants, [], $tenantOnly));
            WikiControllerContainerBuilder::bind($controller, new TestUser('user-1'), ['twig' => $twig]);

            $controller->search(Request::create('/search?space=eng&q=deploy'));
            $filters = $twig->context['page']->filters;
            self::assertInstanceOf(FormView::class, $filters);
            self::assertContains($prefix, $filters->children['space']->vars['block_prefixes']);
            self::assertSame('eng', $filters->children['space']->vars['value']);
            // 1.12: the query field has its own label; the placeholder text stays the placeholder.
            self::assertSame('wiki.search.query', $filters->children['q']->vars['label']);
            self::assertSame('wiki.search.placeholder', $filters->children['q']->vars['attr']['placeholder']);
        }
    }

    public function testIndexPaginatesSpaces(): void
    {
        $spaces = [];
        for ($i = 1; $i <= 5; ++$i) {
            $spaces[] = new WikiSpace('space-' . $i, 'Space ' . $i, WikiSpaceOwnerScope::User, 'user-1');
        }

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn($spaces);

        $twig       = $this->capturingTwig();
        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            pagination: ['spaces_per_page' => 2, 'revisions_per_page' => 25, 'max_tree_pages' => 500],
        );
        WikiControllerContainerBuilder::bind($controller, new TestUser(), ['twig' => $twig]);

        $controller->index(new Request(['page' => '2']));
        $page = $twig->context['page'];
        self::assertInstanceOf(ListPage::class, $page);
        self::assertNotNull($page->pagination);
        self::assertSame([2, 3, 5, 2], [$page->pagination->page, $page->pagination->totalPages, $page->pagination->total, $page->pagination->perPage]);
        self::assertSame(['space-3', 'space-4'], array_column($page->rows, 'slug'));

        $controller->index(new Request(['page' => '99']));
        self::assertSame(3, $twig->context['page']->pagination->page);
        self::assertSame(['space-5'], array_column($twig->context['page']->rows, 'slug'));

        $controller->index(new Request(['page' => '-4']));
        self::assertSame(1, $twig->context['page']->pagination->page);

        $controller->index();
        self::assertSame(1, $twig->context['page']->pagination->page);
    }

    public function testSpaceTreeIsCappedAndFlagged(): void
    {
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $pages = [];
        for ($i = 1; $i <= 11; ++$i) {
            $pages[] = new WikiPage($space, 'page-' . $i, 'Page ' . $i);
        }

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->expects(self::exactly(2))->method('findActiveBySpace')
            ->with($space, 11)
            ->willReturnOnConsecutiveCalls($pages, array_slice($pages, 0, 10));

        $twig       = $this->capturingTwig();
        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            pageRepository: $pageRepo,
            pagination: ['spaces_per_page' => 24, 'revisions_per_page' => 25, 'max_tree_pages' => 10],
        );
        WikiControllerContainerBuilder::bind($controller, new TestUser(), ['twig' => $twig]);

        $controller->space('eng');
        self::assertTrue($twig->context['tree_truncated']);
        self::assertCount(10, $twig->context['tree']);
        self::assertSame(10, $twig->context['max_tree_pages']);

        $controller->space('eng');
        self::assertFalse($twig->context['tree_truncated']);
        self::assertCount(10, $twig->context['tree']);
    }

    public function testPageHistoryPaginatesRevisions(): void
    {
        $user      = new TestUser('user-1');
        $space     = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page      = new WikiPage($space, 'welcome', 'Welcome');
        $revisions = [];
        for ($i = 6; $i >= 1; --$i) {
            $revisions[] = new WikiPageRevision($page, $i, '<p>' . $i . '</p>', $user);
        }

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);

        $revisionRepo = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $revisionRepo->method('findByPage')->willReturnCallback(
            static fn (WikiPage $p, ?int $limit, int $offset): array => array_slice($revisions, $offset, $limit),
        );

        $twig       = $this->capturingTwig();
        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            pageRepository: $pageRepo,
            revisionRepository: $revisionRepo,
            pagination: ['spaces_per_page' => 24, 'revisions_per_page' => 2, 'max_tree_pages' => 500],
        );
        WikiControllerContainerBuilder::bind($controller, $user, ['twig' => $twig]);

        $controller->pageHistory('eng', 'welcome', new Request(['page' => '2']));
        $history = $twig->context['page'];
        self::assertInstanceOf(ListPage::class, $history);
        self::assertSame([4, 3], array_column($history->rows, 'id'));
        // the last row compares with the first revision of the next page (v2)
        self::assertStringContainsString('nowo_wiki_page_diff', (string) $history->rowActions($history->rows[1])[0]->href);
        self::assertNotNull($history->pagination);
        self::assertSame([2, 2], [$history->pagination->page, $history->pagination->perPage]);
        self::assertGreaterThanOrEqual(3, $history->pagination->totalPages);

        $controller->pageHistory('eng', 'welcome', new Request(['page' => '3']));
        $history = $twig->context['page'];
        self::assertSame([2, 1], array_column($history->rows, 'id'));
        self::assertSame([], $history->rowActions($history->rows[1]));
        self::assertSame(3, $history->pagination?->totalPages);

        $controller->pageHistory('eng', 'welcome');
        self::assertSame(1, $twig->context['page']->pagination?->page);

        // 1.12: no pagination region when every revision fits on one page.
        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            pageRepository: $pageRepo,
            revisionRepository: $revisionRepo,
            pagination: ['spaces_per_page' => 24, 'revisions_per_page' => 10, 'max_tree_pages' => 500],
        );
        WikiControllerContainerBuilder::bind($controller, $user, ['twig' => $twig]);
        $controller->pageHistory('eng', 'welcome');
        self::assertCount(6, $twig->context['page']->rows);
        self::assertNull($twig->context['page']->pagination);
    }

    public function testTheHistoryTimelineAddsSaveVersionAndCopyToNewPage(): void
    {
        $user  = new TestUser();
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');
        $v1    = new WikiPageRevision($page, 1, '<p>1</p>', $user);
        $v2    = new WikiPageRevision($page, 2, '<p>2</p>', $user);
        $page->setCurrentRevision($v2);

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);
        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);
        $revisionRepo = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $revisionRepo->method('findByPage')->willReturn([$v2, $v1]);
        $labels = $this->createStub(WikiRevisionLabelRepositoryInterface::class);
        $labels->method('labelsFor')->willReturn([$v1->getId() => 'Before']);
        $timeline = new WikiHistoryTimeline($labels, new WikiAuthorLabel('secret'));
        $this->routes += [
            'page_version_save' => ['path' => '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/versions/new', 'name' => 'nowo_wiki_page_version_save'],
            'page_version_copy' => ['path' => '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/revisions/{revisionNumber}/copy', 'name' => 'nowo_wiki_page_version_copy'],
        ];

        $twig       = $this->capturingTwig();
        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo, revisionRepository: $revisionRepo, timeline: $timeline);
        WikiControllerContainerBuilder::bind($controller, $user, ['twig' => $twig]);

        $controller->pageHistory('eng', 'welcome');
        $history = $twig->context['page'];
        self::assertInstanceOf(ListPage::class, $history);
        self::assertSame(['', 'Before'], array_column($history->rows, 'label'));
        self::assertContains('wiki-version-copy', array_map(static fn (Action $a) => $a->attributes['data-testid'] ?? null, $history->rowActions($history->rows[0])));

        $controller->viewPage('eng', 'welcome');
        self::assertContains('wiki-save-version', array_map(static fn ($a) => $a->attributes['data-testid'] ?? null, $twig->context['page']->header->actions));

        // Without the right to create pages: no Copy to new page.
        $roles      = $this->createConfiguredMock(WikiAccessCheckerInterface::class, ['canAccess' => true, 'canList' => true, 'canCreate' => false, 'canEdit' => true, 'canViewHistory' => true, 'canArchive' => true, 'canAskAi' => true, 'canImport' => true, 'canExport' => true]);
        $controller = $this->controller(accessChecker: $roles, spaceAccessResolver: $resolver, pageRepository: $pageRepo, revisionRepository: $revisionRepo, timeline: $timeline);
        WikiControllerContainerBuilder::bind($controller, $user, ['twig' => $twig]);
        $controller->pageHistory('eng', 'welcome');
        self::assertNotContains('wiki-version-copy', array_map(static fn ($a) => $a->attributes['data-testid'] ?? null, $twig->context['page']->rowActions($twig->context['page']->rows[0])));
    }

    public function testDenyWhenCannotList(): void
    {
        $checker = $this->createMock(WikiAccessCheckerInterface::class);
        $checker->method('canList')->willReturn(false);

        $controller = $this->controller(accessChecker: $checker);
        WikiControllerContainerBuilder::bind($controller, new TestUser());

        $this->expectException(AccessDeniedHttpException::class);
        $controller->index();
    }

    public function testViewPageRendersContent(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hi</p>', $user));

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->viewPage('eng', 'welcome');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('page_view', (string) $response->getContent());
    }

    public function testViewPageRendersMarkdownForTheViewerWithRichContent(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>cache</p>', $user, 'source'));

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);
        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);
        $viewer = new class implements WikiMarkdownRendererInterface, WikiViewerMarkdownRendererInterface {
            public function render(string $markdown): string
            {
                return 'neutral';
            }

            public function renderForViewer(string $markdown): string
            {
                return '<p>for viewer: ' . $markdown . '</p>';
            }
        };

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo, bodyRenderer: new WikiRevisionBodyRenderer($viewer, new WikiHtmlSanitizer()));
        $twig       = new class {
            /** @var array<string, mixed> */
            public array $context = [];

            /** @param array<string, mixed> $context */
            public function render(string $name, array $context = []): string
            {
                $this->context = $context;

                return $name;
            }
        };
        WikiControllerContainerBuilder::bind($controller, $user, ['twig' => $twig]);

        $controller->viewPage('eng', 'welcome');

        self::assertSame('<p>for viewer: source</p>', $twig->context['content_html']);
        self::assertTrue($twig->context['rich_content']);

        $page->setCurrentRevision(null);
        $controller->viewPage('eng', 'welcome');
        self::assertSame('', $twig->context['content_html']);
    }

    public function testViewPageCarriesTheCommentsPanelAndTheReadOnlyMount(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hi</p>', $user, 'Hi **there**'));
        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);
        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);
        $this->routes['attachment_upload'] = ['path' => '/up', 'name' => 'nowo_wiki_attachment_upload'];

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo, editor: ['type' => 'milkdown', 'tiptap_config' => 'notion'], commentsPanel: $this->commentsPanel($space, [WikiAction::ViewComments, WikiAction::Comment]));
        $twig       = $this->capturingTwig();
        WikiControllerContainerBuilder::bind($controller, $user, ['twig' => $twig]);
        $response = $controller->viewPage('eng', 'welcome');

        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame(3, $twig->context['comments']['count']);
        self::assertSame('Hi **there**', $twig->context['comments_view']['markdown']);
        self::assertSame('container', $twig->context['comments_view']['options']['comments']['panel']);
        self::assertTrue($twig->context['comments_view']['options']['comments']['can_create']);
        self::assertArrayNotHasKey('collaboration', $twig->context['comments_view']['options']);
        self::assertArrayNotHasKey('upload_url', $twig->context['comments_view']['options']);
        // The tab row is always there (B-Q1).
        self::assertNotNull($twig->context['page']->header->tabs);

        // A page without a revision: the panel, no read-only mount.
        $page->setCurrentRevision(null);
        $controller->viewPage('eng', 'welcome');
        self::assertArrayHasKey('comments', $twig->context);
        self::assertArrayNotHasKey('comments_view', $twig->context);

        // 1.16 default: a commenter who may not edit gets the server-rendered threads, never the page source.
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hi</p>', $user, 'Hi **there** <!-- hidden -->'));
        $reader     = $this->createConfiguredMock(WikiAccessCheckerInterface::class, ['canAccess' => true, 'canList' => true, 'canEdit' => false, 'canViewHistory' => true, 'canArchive' => false, 'canAskAi' => false, 'canImport' => false, 'canExport' => false, 'canCreate' => false]);
        $controller = $this->controller(accessChecker: $reader, spaceAccessResolver: $resolver, pageRepository: $pageRepo, editor: ['type' => 'milkdown', 'tiptap_config' => 'notion'], commentsPanel: $this->commentsPanel($space, [WikiAction::ViewComments, WikiAction::Comment]));
        WikiControllerContainerBuilder::bind($controller, $user, ['twig' => $twig]);
        $response = $controller->viewPage('eng', 'welcome');
        self::assertFalse($twig->context['comments']['mounted']);
        self::assertNull($twig->context['comments']['edit_href']);
        self::assertArrayNotHasKey('comments_view', $twig->context);
        self::assertArrayNotHasKey('copy_markdown', $twig->context);
        // Every response with the panel is private and never stored.
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));

        // comments.read_view_source opts every commenter in to the read-only mount (and the source).
        $controller = $this->controller(accessChecker: $reader, spaceAccessResolver: $resolver, pageRepository: $pageRepo, editor: ['type' => 'milkdown', 'tiptap_config' => 'notion'], commentsPanel: $this->commentsPanel($space, [WikiAction::ViewComments], true));
        WikiControllerContainerBuilder::bind($controller, $user, ['twig' => $twig]);
        $controller->viewPage('eng', 'welcome');
        self::assertTrue($twig->context['comments']['mounted']);
        self::assertNull($twig->context['comments']['edit_href']);
        self::assertSame('Hi **there** <!-- hidden -->', $twig->context['comments_view']['markdown']);

        // Without ViewComments: no panel at all.
        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo, commentsPanel: $this->commentsPanel($space, [WikiAction::Comment]));
        WikiControllerContainerBuilder::bind($controller, $user, ['twig' => $twig]);
        $controller->viewPage('eng', 'welcome');
        self::assertArrayNotHasKey('comments', $twig->context);
    }

    public function testEditPageMountsTheThreadComponentIntoThePanel(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hi</p>', $user, 'Hi'));
        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);
        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo, editor: ['type' => 'markdown', 'tiptap_config' => 'notion'], commentsPanel: $this->commentsPanel($space, [WikiAction::ViewComments]));
        $twig       = $this->capturingTwig();
        WikiControllerContainerBuilder::bind($controller, $user, ['twig' => $twig]);
        $response = $controller->editPage(Request::create('/edit'), 'eng', 'welcome');

        self::assertSame(3, $twig->context['comments']['count']);
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $tabs = $twig->context['page']->header->tabs;
        self::assertSame(['wiki.tabs.page', 'wiki.tabs.edit', 'wiki.tabs.history'], array_map(static fn ($item): string => $item->label->key, $tabs->items));
        self::assertTrue($tabs->items[1]->active);
    }

    public function testViewPageNotFoundForForeignSpace(): void
    {
        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([]);

        $controller = $this->controller(spaceAccessResolver: $resolver);
        WikiControllerContainerBuilder::bind($controller, new TestUser());

        $this->expectException(NotFoundHttpException::class);
        $controller->viewPage('eng', 'missing');
    }

    public function testArchiveRequiresValidCsrf(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);

        $checker = $this->createMock(WikiAccessCheckerInterface::class);
        $checker->method('canArchive')->willReturn(true);

        $controller = $this->controller(accessChecker: $checker, spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $request = Request::create('/archive', 'POST', ['_token' => 'invalid']);

        $this->expectException(AccessDeniedHttpException::class);
        $controller->archivePage($request, 'eng', 'welcome');
    }

    public function testSpaceRendersTree(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findActiveBySpace')->willReturn([$page]);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->space('eng');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('space', (string) $response->getContent());
    }

    public function testSearchWithoutSpaceSlug(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'deploy', 'Deploy');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            searchService: $this->createSearchService([[$page, 'contentHtml' => '<p>deploy runbook</p>']]),
        );
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->search(Request::create('/search?q=deploy'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('search', (string) $response->getContent());
    }

    public function testAskAiShowsUnavailableWhenNotConfigured(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(spaceAccessResolver: $resolver);
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->askAi(Request::create('/ask'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('ai_ask', (string) $response->getContent());
    }

    /**
     * 1.19.1: with nowo_wiki.ai.enabled false the extension drops the ai_ask route; the action answers 404 before
     * asking the access checker, the spaces or the assistant.
     */
    public function testAskAiIsNotFoundWhenTheAiIsDisabled(): void
    {
        unset($this->routes['ai_ask']);
        $assistant = $this->createMock(WikiAiAssistantInterface::class);
        $assistant->expects(self::never())->method('isAvailable');
        $assistant->expects(self::never())->method('ask');
        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->expects(self::never())->method('listSpacesForUser');

        $controller = $this->controller(spaceAccessResolver: $resolver, aiAssistant: $assistant);
        WikiControllerContainerBuilder::bind($controller, new TestUser('user-1'));

        $this->expectException(NotFoundHttpException::class);
        $controller->askAi(Request::create('/ask', 'POST', ['q' => 'question']));
    }

    /**
     * 1.19.1: the index renders no Ask AI action nor dialog form when the AI is off (null assistant, no ai_ask
     * route), and none either when an assistant is available but the route is not registered.
     */
    public function testIndexRendersNoAiDialogWhenTheAiIsDisabled(): void
    {
        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1')]);
        $available = $this->createConfiguredMock(WikiAiAssistantInterface::class, ['isAvailable' => true]);

        foreach ([[new NullWikiAiAssistant(), true], [$available, false]] as [$assistant, $keepRoute]) {
            if (!$keepRoute) {
                unset($this->routes['ai_ask']);
            }
            $twig       = $this->capturingTwig();
            $controller = $this->controller(spaceAccessResolver: $resolver, aiAssistant: $assistant);
            WikiControllerContainerBuilder::bind($controller, new TestUser(), ['twig' => $twig]);
            $controller->index();

            self::assertNull($twig->context['ai_form']);
            self::assertInstanceOf(ListPage::class, $twig->context['page']);
            foreach ($twig->context['page']->actions as $action) {
                self::assertNotSame(WikiPageFactory::AI_DIALOG_ID, $action->dialogId);
            }
        }

        // Control: AI available and its route registered offer the dialog.
        $this->setUp();
        $twig       = $this->capturingTwig();
        $controller = $this->controller(spaceAccessResolver: $resolver, aiAssistant: $available);
        WikiControllerContainerBuilder::bind($controller, new TestUser(), ['twig' => $twig]);
        $controller->index();
        self::assertNotNull($twig->context['ai_form']);
    }

    public function testPageHistoryRendersRevisions(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');
        $rev   = new WikiPageRevision($page, 1, '<p>Hi</p>', $user);

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);

        $revisionRepo = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $revisionRepo->method('findByPage')->willReturn([$rev]);

        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            pageRepository: $pageRepo,
            revisionRepository: $revisionRepo,
        );
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->pageHistory('eng', 'welcome');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('page_history', (string) $response->getContent());
    }

    public function testPageDiffNotFoundWhenRevisionMissing(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $this->expectException(NotFoundHttpException::class);
        $controller->pageDiff(Request::create('/diff?from=1&to=2'), 'eng', 'welcome');
    }

    public function testPageDiffRendersWhenRevisionsExist(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');
        $from  = new WikiPageRevision($page, 1, '<p>old</p>', $user);
        $to    = new WikiPageRevision($page, 2, '<p>new</p>', $user);

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);

        $revisionRepo = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $revisionRepo->method('findByPageAndNumber')->willReturnMap([
            [$page, 1, $from],
            [$page, 2, $to],
        ]);

        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            pageRepository: $pageRepo,
            revisionRepository: $revisionRepo,
        );
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->pageDiff(Request::create('/diff?from=1&to=2'), 'eng', 'welcome');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('page_diff', (string) $response->getContent());
    }

    public function testEditPageGetRendersForm(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hi</p>', $user));

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->editPage(Request::create('/edit'), 'eng', 'welcome');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('page_edit', (string) $response->getContent());
    }

    public function testEditPagePassesTheCollaborationOptionAndMarksTheSave(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hi</p>', $user, 'Hi'));

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);
        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);
        $this->routes['page_collab'] = ['path' => '/collab/{action}', 'name' => 'nowo_wiki_page_collab'];
        $collab                      = new WikiCollabService(new InMemoryCollabStateStore(), $this->createStub(WikiCollabPublisherInterface::class), $this->createStub(WikiCollabSubscriberAuthorizerInterface::class), secret: 'test-secret');

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo, editor: ['type' => 'markdown', 'tiptap_config' => 'notion'], collab: $collab);
        WikiControllerContainerBuilder::bind($controller, $user);

        self::assertSame(Response::HTTP_OK, $controller->editPage(Request::create('/edit'), 'eng', 'welcome')->getStatusCode());
    }

    public function testAFormSaveFromTheLiveSessionIsMarked(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hi</p>', $user, 'Hi'));
        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);
        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);
        $collab = new WikiCollabService(new InMemoryCollabStateStore(), $this->createStub(WikiCollabPublisherInterface::class), $this->createStub(WikiCollabSubscriberAuthorizerInterface::class), secret: 'test-secret');

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo, editor: ['type' => 'markdown', 'tiptap_config' => 'notion'], collab: $collab);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);
        $post       = static fn (string $session): Request => Request::create('/edit', 'POST', [
            'wiki_page_form'                 => ['title' => 'Welcome', 'content' => 'Hi there', '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_edit_' . $page->getId())],
            WikiCollabService::SESSION_FIELD => $session,
        ]);

        $inSession = $post($collab->topic($page));
        $controller->editPage($inSession, 'eng', 'welcome');
        self::assertSame($page->getId(), $inSession->attributes->get(WikiCollabService::SESSION_SAVE_ATTRIBUTE));

        $outside = $post('urn:other');
        $controller->editPage($outside, 'eng', 'welcome');
        self::assertFalse($outside->attributes->has(WikiCollabService::SESSION_SAVE_ATTRIBUTE));
    }

    public function testDenyWhenCannotEdit(): void
    {
        $checker = $this->createMock(WikiAccessCheckerInterface::class);
        $checker->method('canEdit')->willReturn(false);

        $controller = $this->controller(accessChecker: $checker);
        WikiControllerContainerBuilder::bind($controller, new TestUser());

        $this->expectException(AccessDeniedHttpException::class);
        $controller->editPage(Request::create('/edit'), 'eng', 'welcome');
    }

    public function testDenyWhenCannotViewHistory(): void
    {
        $checker = $this->createMock(WikiAccessCheckerInterface::class);
        $checker->method('canList')->willReturn(true);
        $checker->method('canViewHistory')->willReturn(false);

        $controller = $this->controller(accessChecker: $checker);
        WikiControllerContainerBuilder::bind($controller, new TestUser());

        $this->expectException(AccessDeniedHttpException::class);
        $controller->pageHistory('eng', 'welcome');
    }

    public function testViewArchivedPageNotFound(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');
        $page->archive();

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $this->expectException(NotFoundHttpException::class);
        $controller->viewPage('eng', 'welcome');
    }

    public function testArchiveWithValidCsrfRedirects(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);
        $pageRepo->expects(self::once())->method('save');

        $revisionRepo = $this->createMock(WikiPageRevisionRepositoryInterface::class);

        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            pageRepository: $pageRepo,
            revisionRepository: $revisionRepo,
        );
        $container = WikiControllerContainerBuilder::bind($controller, $user);

        $tokenId = 'wiki_archive_' . $page->getId();
        $request = Request::create('/archive', 'POST', [
            '_token' => WikiControllerContainerBuilder::csrfToken($container, $tokenId),
        ]);

        $response = $controller->archivePage($request, 'eng', 'welcome');

        self::assertTrue($response->isRedirect());
    }

    public function testRequireUserDeniedWhenNotAuthenticated(): void
    {
        $controller = $this->controller();
        WikiControllerContainerBuilder::bind($controller);

        $this->expectException(AccessDeniedHttpException::class);
        $controller->index();
    }

    public function testEditPagePostSavesRevisionAndRedirects(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hi</p>', $user));

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);
        $pageRepo->expects(self::atLeastOnce())->method('save');

        $revisionRepo = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $revisionRepo->method('getNextRevisionNumber')->willReturn(2);
        $revisionRepo->expects(self::once())->method('save');

        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            pageRepository: $pageRepo,
            revisionRepository: $revisionRepo,
        );
        $container = WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->editPage(
            $this->createWikiPageFormRequest($container, 'wiki_edit_' . $page->getId(), [
                'title'   => 'Updated',
                'content' => '<p>Updated</p>',
            ]),
            'eng',
            'welcome',
        );

        self::assertTrue($response->isRedirect('/generated/nowo_wiki_page_view'));
    }

    /**
     * @return iterable<string, array{WikiPageRevision|null, string, string}>
     */
    public static function editorBodies(): iterable
    {
        $user = new TestUser('user-1');
        $page = new WikiPage(new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1'), 'welcome', 'Welcome');

        yield 'html revision in the markdown editor' => [new WikiPageRevision($page, 1, '<h2>Hi</h2><p><s>old</s></p>', $user), 'markdown', "## Hi\n\n~~old~~"];
        yield 'markdown revision in the markdown editor' => [new WikiPageRevision($page, 1, '<p>cache</p>', $user, '*source*'), 'markdown', '*source*'];
        yield 'markdown revision in the tiptap editor' => [new WikiPageRevision($page, 1, '<p><em>source</em></p>', $user, '*source*'), 'tiptap', '<p><em>source</em></p>'];
        yield 'page without revision' => [null, 'markdown', ''];
    }

    #[DataProvider('editorBodies')]
    public function testEditFormShowsTheBodyOfTheConfiguredEditor(?WikiPageRevision $revision, string $editorType, string $expected): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');
        if ($revision instanceof WikiPageRevision) {
            $page->setCurrentRevision($revision);
        }

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);
        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);

        $twig       = $this->capturingTwig();
        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo, editor: ['type' => $editorType, 'tiptap_config' => 'notion', 'markdown_form_type' => null]);
        WikiControllerContainerBuilder::bind($controller, $user, ['twig' => $twig]);

        $controller->editPage(Request::create('/edit'), 'eng', 'welcome');

        $form = $twig->context['page'];
        self::assertInstanceOf(FormPage::class, $form);
        self::assertNotNull($form->form);
        self::assertSame($expected, $form->form->children['content']->vars['value']);
    }

    public function testMarkdownEditorSavesAndCreatesMarkdownRevisions(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hi</p>', $user));

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);
        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);
        $pageRepo->method('countBySpaceAndSlug')->willReturn(0);

        $saved        = [];
        $revisionRepo = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $revisionRepo->method('getNextRevisionNumber')->willReturn(2);
        $revisionRepo->method('save')->willReturnCallback(static function (WikiPageRevision $revision) use (&$saved): void {
            $saved[] = $revision;
        });

        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            pageRepository: $pageRepo,
            revisionRepository: $revisionRepo,
            editor: ['type' => 'markdown', 'tiptap_config' => 'notion', 'markdown_form_type' => null],
        );
        $container = WikiControllerContainerBuilder::bind($controller, $user);

        $controller->editPage($this->createWikiPageFormRequest($container, 'wiki_edit_' . $page->getId(), ['content' => "| A |\r\n|---|\r\n| 1 |"]), 'eng', 'welcome');
        $controller->newPage($this->createWikiPageFormRequest($container, 'wiki_new_' . $space->getId(), ['title' => 'Fresh', 'content' => '- [x] done']), 'eng');

        self::assertCount(2, $saved);
        self::assertSame("| A |\n|---|\n| 1 |", $saved[0]->getContentMarkdown());
        self::assertStringContainsString('<td>1</td>', $saved[0]->getContentHtml());
        self::assertTrue($saved[1]->isMarkdown());
        self::assertSame('fresh', $saved[1]->getPage()->getSlug());
    }

    public function testNewPageGetRendersCreateForm(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(spaceAccessResolver: $resolver);
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->newPage(Request::create('/new'), 'eng');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('page_edit', (string) $response->getContent());
    }

    public function testNewPagePostCreatesPageAndRedirects(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('countBySpaceAndSlug')->willReturn(0);
        $pageRepo->expects(self::exactly(2))->method('save');

        $revisionRepo = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $revisionRepo->method('getNextRevisionNumber')->willReturn(1);
        $revisionRepo->expects(self::once())->method('save');

        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            pageRepository: $pageRepo,
            revisionRepository: $revisionRepo,
        );
        $container = WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->newPage(
            $this->createWikiPageFormRequest($container, 'wiki_new_' . $space->getId(), [
                'title'   => 'New doc',
                'content' => '<p>Body</p>',
            ]),
            'eng',
        );

        self::assertTrue($response->isRedirect('/generated/nowo_wiki_page_view'));
    }

    public function testSearchWithSpaceAndQuery(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $query = $this->createMock(Query::class);
        $query->method('setHint')->willReturnSelf();
        $query->method('getResult')->willReturn([[$page, 'contentHtml' => '<p>welcome</p>']]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('innerJoin')->willReturnSelf();
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            searchService: new WikiSearchService($em),
        );
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->search(Request::create('/search?space=eng&q=welcome'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('search', (string) $response->getContent());
    }

    public function testDenyWhenCannotCreate(): void
    {
        $checker = $this->createMock(WikiAccessCheckerInterface::class);
        $checker->method('canCreate')->willReturn(false);

        $controller = $this->controller(accessChecker: $checker);
        WikiControllerContainerBuilder::bind($controller, new TestUser());

        $this->expectException(AccessDeniedHttpException::class);
        $controller->newPage(Request::create('/new'), 'eng');
    }

    public function testEditPostRejectsInvalidCsrf(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'welcome', 'Welcome');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hi</p>', $user));

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findBySlug')->willReturn($page);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $request = $this->createWikiPageFormRequest($container, 'invalid-token-id', [
            'title'   => 'Updated',
            'content' => '<p>Updated</p>',
        ]);

        // REQ-TWIG-005: the Form's own CSRF field now guards the page form; an invalid token re-renders the form (422).
        $pageRepo->expects(self::never())->method('save');
        $response = $controller->editPage($request, 'eng', 'welcome');
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testNewPagePostRejectsInvalidCsrf(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(spaceAccessResolver: $resolver);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $request = $this->createWikiPageFormRequest($container, 'invalid-token-id', [
            'title'   => 'New doc',
            'content' => '<p>Body</p>',
        ]);

        $response = $controller->newPage($request, 'eng');
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('page_edit', (string) $response->getContent());
    }

    public function testNewPagePostRerendersOnDuplicateSlug(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('countBySpaceAndSlug')->willReturn(1);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->newPage(
            $this->createWikiPageFormRequest($container, 'wiki_new_' . $space->getId(), [
                'title'   => 'Duplicate',
                'content' => '<p>Body</p>',
            ]),
            'eng',
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('page_edit', (string) $response->getContent());
        // 1.12: a catalogue key (TranslatableMessage without a translator), not the service's English message.
        $errors = $this->flashes($container, 'error');
        self::assertCount(1, $errors);
        $this->assertTranslatable($errors[0], 'wiki.page.error.slug_exists');
    }

    public function testNewPagePostFlashesInvalidSlugAndOtherErrorsAsCatalogueMessages(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(spaceAccessResolver: $resolver);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);
        $controller->newPage($this->createWikiPageFormRequest($container, 'wiki_new_' . $space->getId(), ['title' => '!!!', 'content' => '<p>Body</p>']), 'eng');
        $this->assertTranslatable($this->flashes($container, 'error')[0], 'wiki.page.error.invalid_slug');

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('countBySpaceAndSlug')->willThrowException(new InvalidArgumentException('Storage refused the page.'));
        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);
        $controller->newPage($this->createWikiPageFormRequest($container, 'wiki_new_' . $space->getId(), ['title' => 'Fine', 'content' => '<p>Body</p>']), 'eng');
        $this->assertTranslatable($this->flashes($container, 'error')[0], 'wiki.page.error.save_failed', ['%reason%' => 'Storage refused the page.']);
    }

    public function testImportSpaceNotFoundWhenImportExportDisabled(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            importExportEnabled: false,
        );
        WikiControllerContainerBuilder::bind($controller, $user);

        $this->expectException(NotFoundHttpException::class);
        $controller->importSpace(Request::create('/import', 'POST'), 'eng');
    }

    public function testExportSpaceUsesOutlineWhenFormatAuto(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'intro', 'Intro');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hello</p>', $user));

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findActiveBySpace')->willReturn([$page]);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->exportSpace(Request::create('/export?format=auto'), 'eng');

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        @unlink($response->getFile()->getPathname());
    }

    public function testImportSpaceRejectsOversizedUpload(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            maxUploadBytes: 10,
        );
        $container = WikiControllerContainerBuilder::bind($controller, $user);

        $temp = tempnam(sys_get_temp_dir(), 'wiki-upload-');
        file_put_contents($temp, str_repeat('x', 20));

        $request = Request::create('/import', 'POST', [
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_import_' . $space->getId()),
        ], [], [
            'archive' => new UploadedFile($temp, 'archive.zip', 'application/zip', null, true),
        ]);

        $response = $controller->importSpace($request, 'eng');

        self::assertTrue($response->isRedirect('/generated/nowo_wiki_space'));
        @unlink($temp);
    }

    public function testImportSpaceHandlesImporterException(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(spaceAccessResolver: $resolver);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $zipPath = sys_get_temp_dir() . '/wiki-upload-' . bin2hex(random_bytes(4)) . '.zip';
        file_put_contents($zipPath, 'not-a-valid-zip');

        $request = Request::create('/import', 'POST', [
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_import_' . $space->getId()),
        ], [], [
            'archive' => new UploadedFile($zipPath, 'archive.zip', 'application/zip', null, true),
        ]);

        $response = $controller->importSpace($request, 'eng');

        self::assertTrue($response->isRedirect('/generated/nowo_wiki_space'));
        $this->assertTranslatable($this->flashes($container, 'error')[0], 'wiki.import.failed', ['%reason%' => 'Unable to open ZIP archive.']);
        @unlink($zipPath);
    }

    public function testImportSpaceFlashesWarningMessages(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $sourceDir = sys_get_temp_dir() . '/wiki-upload-src-' . bin2hex(random_bytes(4));
        mkdir($sourceDir);
        file_put_contents($sourceDir . '/intro.md', <<<'MD'
---
title: Intro
wiki_slug: intro
---
Welcome.
MD);

        $zipPath = sys_get_temp_dir() . '/wiki-upload-' . bin2hex(random_bytes(4)) . '.zip';
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($sourceDir . '/intro.md', 'intro.md');
        $zip->close();

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $page     = new WikiPage($space, 'intro', 'Intro');
        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findActiveBySpace')->willReturn([$page]);
        $pageRepo->method('countBySpaceAndSlug')->willReturn(1);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $request = Request::create('/import', 'POST', [
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_import_' . $space->getId()),
        ], [], [
            'archive' => new UploadedFile($zipPath, 'archive.zip', 'application/zip', null, true),
        ]);

        $response = $controller->importSpace($request, 'eng');

        self::assertTrue($response->isRedirect('/generated/nowo_wiki_space'));
        $this->assertTranslatable($this->flashes($container, 'success')[0], 'wiki.import.success');
        $report = $this->flashes($container, 'info')[0];
        self::assertInstanceOf(TranslatableMessage::class, $report);
        self::assertSame('wiki.import.report', $report->getMessage());
        self::assertSame(['%created%', '%updated%', '%skipped%', '%failed%'], array_keys($report->getParameters()));
        self::assertNotEmpty($this->flashes($container, 'warning'));
        unlink($sourceDir . '/intro.md');
        rmdir($sourceDir);
        @unlink($zipPath);
    }

    public function testImportSpaceImportsZipWithFormatOption(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $sourceDir = sys_get_temp_dir() . '/wiki-upload-src-' . bin2hex(random_bytes(4));
        mkdir($sourceDir);
        file_put_contents($sourceDir . '/intro.md', <<<'MD'
---
title: Intro
wiki_slug: intro
---
Welcome.
MD);

        $zipPath = sys_get_temp_dir() . '/wiki-upload-' . bin2hex(random_bytes(4)) . '.zip';
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($sourceDir . '/intro.md', 'intro.md');
        $zip->close();

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findActiveBySpace')->willReturn([]);
        $pageRepo->method('countBySpaceAndSlug')->willReturn(0);
        $pageRepo->expects(self::exactly(2))->method('save');

        $revisionRepo = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $revisionRepo->method('getNextRevisionNumber')->willReturn(1);
        $revisionRepo->expects(self::once())->method('save');

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo, revisionRepository: $revisionRepo);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $request = Request::create('/import', 'POST', [
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_import_' . $space->getId()),
            'format' => WikiInterchangeFormat::Outline->value,
        ], [], [
            'archive' => new UploadedFile($zipPath, 'archive.zip', 'application/zip', null, true),
        ]);

        try {
            $response = $controller->importSpace($request, 'eng');
            self::assertTrue($response->isRedirect('/generated/nowo_wiki_space'));
        } finally {
            unlink($sourceDir . '/intro.md');
            rmdir($sourceDir);
            @unlink($zipPath);
        }
    }

    public function testImportSpaceUsesAutoFormatFallback(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $sourceDir = sys_get_temp_dir() . '/wiki-upload-src-' . bin2hex(random_bytes(4));
        mkdir($sourceDir);
        file_put_contents($sourceDir . '/intro.md', <<<'MD'
---
title: Intro
wiki_slug: intro
---
Welcome.
MD);

        $zipPath = sys_get_temp_dir() . '/wiki-upload-' . bin2hex(random_bytes(4)) . '.zip';
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($sourceDir . '/intro.md', 'intro.md');
        $zip->close();

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findActiveBySpace')->willReturn([]);
        $pageRepo->method('countBySpaceAndSlug')->willReturn(0);
        $pageRepo->method('save');

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $request = Request::create('/import', 'POST', [
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_import_' . $space->getId()),
            'format' => 'not-a-format',
        ], [], [
            'archive' => new UploadedFile($zipPath, 'archive.zip', 'application/zip', null, true),
        ]);

        try {
            $response = $controller->importSpace($request, 'eng');
            self::assertTrue($response->isRedirect('/generated/nowo_wiki_space'));
        } finally {
            unlink($sourceDir . '/intro.md');
            rmdir($sourceDir);
            @unlink($zipPath);
        }
    }

    public function testExportSpaceUsesAutoFormatFallback(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'intro', 'Intro');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hello</p>', $user));

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findActiveBySpace')->willReturn([$page]);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->exportSpace(
            Request::create('/export?format=' . WikiInterchangeFormat::Auto->value),
            'eng',
        );

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        @unlink($response->getFile()->getPathname());
    }

    public function testExportSpaceFallsBackWhenFormatInvalid(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'intro', 'Intro');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hello</p>', $user));

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findActiveBySpace')->willReturn([$page]);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->exportSpace(Request::create('/export?format=not-valid'), 'eng');

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        @unlink($response->getFile()->getPathname());
    }

    public function testImportSpaceRedirectsWhenCopyFails(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(spaceAccessResolver: $resolver);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $upload = $this->createMock(UploadedFile::class);
        $upload->method('getClientOriginalExtension')->willReturn('zip');
        $upload->method('getPathname')->willReturn('/tmp/does-not-exist-' . bin2hex(random_bytes(4)) . '.zip');
        $upload->method('getSize')->willReturn(100);
        // REQ-TWIG-005: the upload now goes through WikiImportType's FileType, which rejects an invalid upload.
        $upload->method('isValid')->willReturn(true);

        $request = Request::create('/import', 'POST', [
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_import_' . $space->getId()),
        ], [], [
            'archive' => $upload,
        ]);

        $response = $controller->importSpace($request, 'eng');

        self::assertTrue($response->isRedirect('/generated/nowo_wiki_space'));
    }

    public function testDenyUnlessFeatureFallsBackToCanAccess(): void
    {
        $checker = $this->createMock(WikiAccessCheckerInterface::class);
        $checker->expects(self::once())->method('canAccess')->willReturn(false);

        $controller = $this->controller(accessChecker: $checker);
        WikiControllerContainerBuilder::bind($controller, new TestUser());
        $method = new ReflectionMethod(WikiManageController::class, 'denyUnlessFeature');

        $this->expectException(AccessDeniedHttpException::class);
        $method->invoke($controller, 'unknown');
    }

    public function testDenyUnlessFeatureSkippedWhenAllowUnauthenticated(): void
    {
        $checker = $this->createMock(WikiAccessCheckerInterface::class);
        $checker->expects(self::never())->method(self::anything());

        $controller = $this->controller(accessChecker: $checker, allowUnauthenticated: true);
        $method     = new ReflectionMethod(WikiManageController::class, 'denyUnlessFeature');

        $method->invoke($controller, 'list');
        self::assertTrue(true);
    }

    public function testDenyWhenCannotExport(): void
    {
        $checker = $this->createMock(WikiAccessCheckerInterface::class);
        $checker->method('canExport')->willReturn(false);

        $controller = $this->controller(accessChecker: $checker);
        WikiControllerContainerBuilder::bind($controller, new TestUser());

        $this->expectException(AccessDeniedHttpException::class);
        $controller->exportSpace(Request::create('/export'), 'eng');
    }

    public function testExportSpaceReturnsZipDownload(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'intro', 'Intro');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hello</p>', $user));

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findActiveBySpace')->willReturn([$page]);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->exportSpace(Request::create('/export?format=outline'), 'eng');

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertFileExists($response->getFile()->getPathname());
        @unlink($response->getFile()->getPathname());
    }

    public function testExportSpaceSetsZipContentTypeWithoutMimeGuessing(): void
    {
        // symfony/mime is not a dependency: prepare() must not need to guess the type.
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'intro', 'Intro');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hello</p>', $user));

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findActiveBySpace')->willReturn([$page]);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $request  = Request::create('/export?format=outline');
        $response = $controller->exportSpace($request, 'eng');
        $path     = $response->getFile()->getPathname();

        try {
            $response->prepare($request);

            self::assertSame('application/zip', $response->headers->get('Content-Type'));
            self::assertSame(
                'attachment; filename=eng-outline-export.zip',
                $response->headers->get('Content-Disposition'),
            );
        } finally {
            @unlink($path);
        }
    }

    public function testExportSpaceWithNoVisiblePagesReturnsZipWithReadme(): void
    {
        // 1.15.1: ZipArchive writes no file for an empty archive, so an empty space used to end in a 500
        // (BinaryFileResponse: "... .zip does not exist"). The README index keeps the archive non-empty.
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findActiveBySpace')->willReturn([]);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $before   = glob(sys_get_temp_dir() . '/wiki-export-*') ?: [];
        $response = $controller->exportSpace(Request::create('/export?format=outline'), 'eng');
        $path     = $response->getFile()->getPathname();

        try {
            self::assertSame(Response::HTTP_OK, $response->getStatusCode());
            $entries = $this->zipEntries($path);
            self::assertSame(['README.md'], array_keys($entries));
            self::assertStringContainsString('# Engineering', $entries['README.md']);
            self::assertStringContainsString('This space has no pages visible to you.', $entries['README.md']);
            // Only the archive itself is left behind (deleted after send); the working directory is gone.
            self::assertSame([$path], array_values(array_diff(glob(sys_get_temp_dir() . '/wiki-export-*') ?: [], $before)));
        } finally {
            @unlink($path);
        }
    }

    public function testExportSpaceReadmeListsTheExportedPages(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $intro = new WikiPage($space, 'intro', 'Intro');
        $setup = new WikiPage($space, 'setup', 'Setup [draft]', $intro);
        $intro->setCurrentRevision(new WikiPageRevision($intro, 1, '<p>Hello</p>', $user));
        $setup->setCurrentRevision(new WikiPageRevision($setup, 1, '<p>Steps</p>', $user));

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findActiveBySpace')->willReturn([$intro, $setup]);

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $path = $controller->exportSpace(Request::create('/export?format=notion'), 'eng')->getFile()->getPathname();

        try {
            $entries = $this->zipEntries($path);
            self::assertArrayHasKey('README.md', $entries);
            self::assertArrayHasKey('Intro/Intro.md', $entries);
            self::assertStringContainsString("- [Intro](Intro/Intro.md)\n  - [Setup \\[draft\\]](Intro/Setup%20%5Bdraft%5D/Setup%20%5Bdraft%5D.md)\n", $entries['README.md']);
            self::assertStringNotContainsString('no pages visible', $entries['README.md']);
        } finally {
            @unlink($path);
        }
    }

    public function testStaleTokenFromPreviousRequestIsIgnoredOutsideSecuredFirewall(): void
    {
        $space    = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        // 1.10: the index also asks the space checker per space (the 1.x one reads the resolver again).
        $resolver->expects(self::atLeastOnce())->method('listSpacesForUser')->willReturn([$space]);

        $requestStack = new RequestStack();
        $controller   = $this->controller(spaceAccessResolver: $resolver, tokenGuard: new WikiTokenGuard($requestStack));
        // The token storage keeps the user between requests, as it does in a worker without reset.
        WikiControllerContainerBuilder::bind($controller, new TestUser('user-1'));

        $secured = Request::create('/tools/wiki');
        $secured->attributes->set('_firewall_context', 'security.firewall.map.context.main');
        $requestStack->push($secured);
        self::assertSame(Response::HTTP_OK, $controller->index()->getStatusCode());
        $requestStack->pop();

        $requestStack->push(Request::create('/tools/wiki'));
        $this->expectException(AccessDeniedHttpException::class);
        $controller->index();
    }

    public function testExportSpaceFailureLeavesNoTemporaryFiles(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng-fail', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findActiveBySpace')->willThrowException(new RuntimeException('export failed'));

        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo);
        WikiControllerContainerBuilder::bind($controller, $user);

        $before = glob(sys_get_temp_dir() . '/wiki-export-*') ?: [];

        try {
            $controller->exportSpace(Request::create('/export?format=outline'), 'eng-fail');
            self::fail('Export should have failed.');
        } catch (RuntimeException $exception) {
            self::assertSame('export failed', $exception->getMessage());
        }

        self::assertSame($before, glob(sys_get_temp_dir() . '/wiki-export-*') ?: []);
    }

    public function testExportSpaceNotFoundWhenImportExportDisabled(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            importExportEnabled: false,
        );
        WikiControllerContainerBuilder::bind($controller, $user);

        $this->expectException(NotFoundHttpException::class);
        $controller->exportSpace(Request::create('/export'), 'eng');
    }

    public function testDenyWhenCannotImport(): void
    {
        $checker = $this->createMock(WikiAccessCheckerInterface::class);
        $checker->method('canImport')->willReturn(false);

        $controller = $this->controller(accessChecker: $checker);
        WikiControllerContainerBuilder::bind($controller, new TestUser());

        $this->expectException(AccessDeniedHttpException::class);
        $controller->importSpace(Request::create('/import', 'POST'), 'eng');
    }

    public function testImportSpaceRedirectsWhenFileMissing(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(spaceAccessResolver: $resolver);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $request = Request::create('/import', 'POST', [
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_import_' . $space->getId()),
        ]);

        $response = $controller->importSpace($request, 'eng');

        self::assertTrue($response->isRedirect('/generated/nowo_wiki_space'));
    }

    public function testImportSpaceRejectsNonZipUpload(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(spaceAccessResolver: $resolver);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $temp    = tempnam(sys_get_temp_dir(), 'wiki-upload-');
        $request = Request::create('/import', 'POST', [
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_import_' . $space->getId()),
        ], [], [
            'archive' => new UploadedFile($temp, 'notes.txt', 'text/plain', null, true),
        ]);

        $response = $controller->importSpace($request, 'eng');

        self::assertTrue($response->isRedirect('/generated/nowo_wiki_space'));
        @unlink($temp);
    }

    public function testImportSpaceImportsZipArchive(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $sourceDir = sys_get_temp_dir() . '/wiki-upload-src-' . bin2hex(random_bytes(4));
        mkdir($sourceDir);
        file_put_contents($sourceDir . '/intro.md', <<<'MD'
---
title: Intro
wiki_slug: intro
---
Welcome.
MD);

        $zipPath = sys_get_temp_dir() . '/wiki-upload-' . bin2hex(random_bytes(4)) . '.zip';
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($sourceDir . '/intro.md', 'intro.md');
        $zip->close();

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('findActiveBySpace')->willReturn([]);
        $pageRepo->method('countBySpaceAndSlug')->willReturn(0);
        $pageRepo->expects(self::exactly(2))->method('save');

        $revisionRepo = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $revisionRepo->method('getNextRevisionNumber')->willReturn(1);
        $revisionRepo->expects(self::once())->method('save');

        $controller = $this->controller(
            spaceAccessResolver: $resolver,
            pageRepository: $pageRepo,
            revisionRepository: $revisionRepo,
        );
        $container = WikiControllerContainerBuilder::bind($controller, $user);

        $request = Request::create('/import', 'POST', [
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_import_' . $space->getId()),
        ], [], [
            'archive' => new UploadedFile($zipPath, 'archive.zip', 'application/zip', null, true),
        ]);

        try {
            $response = $controller->importSpace($request, 'eng');
            self::assertTrue($response->isRedirect('/generated/nowo_wiki_space'));
        } finally {
            unlink($sourceDir . '/intro.md');
            rmdir($sourceDir);
            if (is_file($zipPath)) {
                unlink($zipPath);
            }
        }
    }

    public function testNewPageRefusedByTheQuotaShowsTheReasonAndWritesNothing(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->method('countBySpaceAndSlug')->willReturn(0);
        $pageRepo->expects(self::never())->method('save');

        $seen       = [];
        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo, quota: $this->refusingQuota('Your plan allows 10 pages.', $seen));
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->newPage($this->createWikiPageFormRequest($container, 'wiki_new_' . $space->getId(), ['title' => 'New doc']), 'eng');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertCount(1, $seen);
        self::assertSame(WikiQuotaCheckEvent::PAGE_CREATE, $seen[0]->action);
        self::assertSame($space, $seen[0]->space);
        self::assertSame(1, $seen[0]->count);
        self::assertNull($seen[0]->page);
    }

    public function testImportSpaceRefusedByTheQuotaFlashesTheReasonAndImportsNothing(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $pageRepo = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepo->expects(self::never())->method('save');

        $seen       = [];
        $controller = $this->controller(spaceAccessResolver: $resolver, pageRepository: $pageRepo, quota: $this->refusingQuota('No more pages on this plan.', $seen));
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $zipPath = tempnam(sys_get_temp_dir(), 'wiki-quota-');
        file_put_contents($zipPath, str_repeat('x', 300));
        $request = Request::create('/import', 'POST', [
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_import_' . $space->getId()),
        ], [], [
            'archive' => new UploadedFile($zipPath, 'archive.zip', 'application/zip', null, true),
        ]);

        try {
            $response = $controller->importSpace($request, 'eng');
        } finally {
            @unlink($zipPath);
        }

        self::assertTrue($response->isRedirect('/generated/nowo_wiki_space'));
        self::assertSame(['No more pages on this plan.'], $this->flashes($container, 'error'));
        self::assertSame(WikiQuotaCheckEvent::IMPORT, $seen[0]->action);
        self::assertSame(1, $seen[0]->count);
        self::assertSame(300, $seen[0]->bytes);
    }

    public function testImportSpaceRejectsInvalidCsrf(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(spaceAccessResolver: $resolver);
        WikiControllerContainerBuilder::bind($controller, $user);

        $this->expectException(AccessDeniedHttpException::class);
        $controller->importSpace(Request::create('/import', 'POST'), 'eng');
    }

    public function testAskAiPostReturnsAnswer(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $aiAssistant = $this->createMock(WikiAiAssistantInterface::class);
        $aiAssistant->method('isAvailable')->willReturn(true);
        $aiAssistant->method('ask')->willReturn(new WikiAiAnswer('Use the runbook.', []));

        $controller = $this->controller(spaceAccessResolver: $resolver, aiAssistant: $aiAssistant);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->askAi(Request::create('/ask', 'POST', [
            'q'      => 'How do I deploy?',
            'space'  => 'eng',
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_ai_ask'),
        ]));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('ai_ask', (string) $response->getContent());
    }

    public function testAskAiPostRejectsEmptyQuestion(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $aiAssistant = $this->createMock(WikiAiAssistantInterface::class);
        $aiAssistant->method('isAvailable')->willReturn(true);

        $controller = $this->controller(spaceAccessResolver: $resolver, aiAssistant: $aiAssistant);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->askAi(Request::create('/ask', 'POST', [
            'q'      => '   ',
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_ai_ask'),
        ]));

        self::assertStringContainsString('ai_ask', (string) $response->getContent());
    }

    public function testAskAiPostHandlesAssistantFailure(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $aiAssistant = $this->createMock(WikiAiAssistantInterface::class);
        $aiAssistant->method('isAvailable')->willReturn(true);
        $aiAssistant->method('ask')->willThrowException(new RuntimeException('AI down'));

        $controller = $this->controller(spaceAccessResolver: $resolver, aiAssistant: $aiAssistant);
        $container  = WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->askAi(Request::create('/ask', 'POST', [
            'q'      => 'question',
            '_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_ai_ask'),
        ]));

        self::assertStringContainsString('ai_ask', (string) $response->getContent());
    }

    public function testAskAiPostRejectsInvalidCsrf(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $aiAssistant = $this->createMock(WikiAiAssistantInterface::class);
        $aiAssistant->method('isAvailable')->willReturn(true);

        $controller = $this->controller(spaceAccessResolver: $resolver, aiAssistant: $aiAssistant);
        WikiControllerContainerBuilder::bind($controller, $user);

        $this->expectException(AccessDeniedHttpException::class);
        $controller->askAi(Request::create('/ask', 'POST', ['q' => 'question']));
    }

    public function testAskAiWithSpaceSlug(): void
    {
        $user  = new TestUser('user-1');
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $controller = $this->controller(spaceAccessResolver: $resolver);
        WikiControllerContainerBuilder::bind($controller, $user);

        $response = $controller->askAi(Request::create('/ask?space=eng&q=deploy'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * @param array<string, string> $fields
     */
    private function createWikiPageFormRequest(Container $container, string $actionTokenId, array $fields = []): Request
    {
        return Request::create('/submit', 'POST', [
            'wiki_page_form' => array_merge([
                'title'   => 'Title',
                'content' => '<p>Content</p>',
                '_token'  => WikiControllerContainerBuilder::csrfToken($container, $actionTokenId),
            ], $fields),
        ]);
    }

    private function createSearchService(array $rows): WikiSearchService
    {
        $query = $this->createMock(Query::class);
        $query->method('setHint')->willReturnSelf();
        $query->method('getResult')->willReturn($rows);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('innerJoin')->willReturnSelf();
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        return new WikiSearchService($em);
    }

    private function controller(
        ?WikiAccessCheckerInterface $accessChecker = null,
        ?WikiSpaceAccessResolverInterface $spaceAccessResolver = null,
        ?WikiPageRepositoryInterface $pageRepository = null,
        ?WikiPageRevisionRepositoryInterface $revisionRepository = null,
        ?WikiPageService $pageService = null,
        ?WikiSearchService $searchService = null,
        ?WikiAiAssistantInterface $aiAssistant = null,
        bool $importExportEnabled = true,
        int $maxUploadBytes = 52428800,
        bool $allowUnauthenticated = false,
        ?WikiTokenGuard $tokenGuard = null,
        array $pagination = WikiManageController::DEFAULT_PAGINATION,
        array $editor = ['tiptap_config' => 'notion'],
        ?WikiTenantPaths $tenantPaths = null,
        ?WikiRevisionBodyRenderer $bodyRenderer = null,
        ?WikiCollabService $collab = null,
        ?WikiHistoryTimeline $timeline = null,
        ?WikiCommentsPanel $commentsPanel = null,
        ?WikiQuotaGate $quota = null,
    ): WikiManageController {
        $accessChecker ??= $this->createConfiguredMock(WikiAccessCheckerInterface::class, [
            'canAccess'      => true,
            'canList'        => true,
            'canCreate'      => true,
            'canEdit'        => true,
            'canViewHistory' => true,
            'canArchive'     => true,
            'canAskAi'       => true,
            'canImport'      => true,
            'canExport'      => true,
        ]);

        $pageRepository ??= $this->createMock(WikiPageRepositoryInterface::class);
        $revisionRepository ??= $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $pageService ??= new WikiPageService(
            $pageRepository,
            $revisionRepository,
            new WikiSlugger(),
            new WikiHtmlSanitizer(),
            new EventDispatcher(),
        );

        return new WikiManageController(
            $accessChecker,
            $spaceAccessResolver ?? $this->createMock(WikiSpaceAccessResolverInterface::class),
            $pageRepository,
            $revisionRepository,
            $pageService,
            new WikiPageTreeBuilder(),
            new WikiRevisionDiffService($revisionRepository),
            $searchService ?? new WikiSearchService($this->createMock(EntityManagerInterface::class)),
            $aiAssistant ?? new NullWikiAiAssistant(),
            $this->documentImporter($pageRepository, $pageService),
            $this->documentExporter($pageRepository),
            new WikiArchiveHelper(),
            $this->routes,
            $this->templates,
            null,
            $editor,
            ['enabled' => $importExportEnabled, 'max_upload_bytes' => $maxUploadBytes],
            $allowUnauthenticated,
            $tokenGuard,
            $pagination,
            tenantPaths: $tenantPaths,
            bodyRenderer: $bodyRenderer,
            collab: $collab,
            timeline: $timeline,
            commentsPanel: $commentsPanel,
            quota: $quota,
        );
    }

    /**
     * 1.21: a quota gate whose listener refuses every check with the reason, recording what it was asked.
     *
     * @param list<WikiQuotaCheckEvent> $seen
     */
    private function refusingQuota(string $reason, array &$seen): WikiQuotaGate
    {
        $events = new EventDispatcher();
        $events->addListener(WikiEvents::QUOTA_CHECK, static function (WikiQuotaCheckEvent $event) use ($reason, &$seen): void {
            $seen[] = $event;
            $event->deny($reason);
        });

        return new WikiQuotaGate($events);
    }

    /**
     * 1.16: a comments panel whose own guard grants the given comment actions (open threads: 3).
     *
     * @param list<WikiAction> $granted
     */
    private function commentsPanel(WikiSpace $space, array $granted, bool $readViewSource = false): WikiCommentsPanel
    {
        $guard    = new WikiSpaceGuard(new FixedSpaceLocator($space), CallbackSpaceAccessChecker::allowing(...$granted));
        $comments = $this->createStub(WikiCommentRepositoryInterface::class);
        $comments->method('countOpen')->willReturn(3);
        $comments->method('threads')->willReturn([]);
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $name): string => '/' . $name);
        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('getToken')->willReturnCallback(static fn (string $id): CsrfToken => new CsrfToken($id, 'tok'));

        return new WikiCommentsPanel(
            new WikiCommentService($comments, $guard, new WikiAuthorLabel('s'), new WikiMarkdownRenderer(), new WikiHtmlSanitizer()),
            new SessionWikiPanelStateStore(new RequestStack()),
            $router,
            $csrf,
            ['page_comments' => ['path' => '/c/{path}', 'name' => 'nowo_wiki_page_comments']],
            readViewSource: $readViewSource,
        );
    }

    /**
     * Flash messages of one type stored in the session of the bound container.
     *
     * @return list<mixed>
     */
    private function flashes(Container $container, string $type): array
    {
        /** @var RequestStack $stack */
        $stack = $container->get('request_stack');
        /** @var FlashBagAwareSessionInterface $session */
        $session = $stack->getSession();

        return array_values($session->getFlashBag()->get($type));
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function assertTranslatable(mixed $message, string $key, array $parameters = []): void
    {
        self::assertInstanceOf(TranslatableMessage::class, $message);
        self::assertSame([$key, $parameters, 'NowoWikiBundle'], [$message->getMessage(), $message->getParameters(), $message->getDomain()]);
    }

    /**
     * Twig stub that keeps the context of the last render.
     */
    private function capturingTwig(): object
    {
        return new class {
            /** @var array<string, mixed> */
            public array $context = [];

            /**
             * @param array<string, mixed> $context
             */
            public function render(string $name, array $context = []): string
            {
                $this->context = $context;

                return $name;
            }
        };
    }

    /**
     * Entry name => contents of a ZIP archive.
     *
     * @return array<string, string>
     */
    private function zipEntries(string $path): array
    {
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path));
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = (string) $zip->getNameIndex($i);
            if (!str_ends_with($name, '/')) {
                $entries[$name] = (string) $zip->getFromIndex($i);
            }
        }
        $zip->close();

        return $entries;
    }

    private function documentImporter(
        WikiPageRepositoryInterface $pageRepository,
        WikiPageService $pageService,
    ): WikiDocumentImporter {
        return new WikiDocumentImporter(
            new WikiArchiveHelper(),
            new WikiFormatDetector(new WikiFrontMatterParser()),
            new WikiDocumentTreeReader(new WikiFrontMatterParser()),
            new WikiMarkdownConverter(),
            $pageRepository,
            $pageService,
            new WikiSlugger(),
        );
    }

    private function documentExporter(WikiPageRepositoryInterface $pageRepository): WikiDocumentExporter
    {
        return new WikiDocumentExporter(
            $pageRepository,
            new WikiPageTreeBuilder(),
            new WikiMarkdownConverter(),
            new WikiFrontMatterParser(),
        );
    }
}
