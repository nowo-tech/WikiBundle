<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Controller;

use InvalidArgumentException;
use Nowo\UiKitBundle\View\Pagination;
use Nowo\WikiBundle\Ai\WikiAiAssistantInterface;
use Nowo\WikiBundle\DependencyInjection\Configuration;
use Nowo\WikiBundle\Dto\WikiPageFormData;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiInterchangeFormat;
use Nowo\WikiBundle\Event\WikiEditorOptionsEvent;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Form\WikiAiAskType;
use Nowo\WikiBundle\Form\WikiImportType;
use Nowo\WikiBundle\Form\WikiPageFormType;
use Nowo\WikiBundle\Form\WikiPostActionType;
use Nowo\WikiBundle\Form\WikiSearchType;
use Nowo\WikiBundle\Interchange\WikiArchiveHelper;
use Nowo\WikiBundle\Interchange\WikiDocumentExporter;
use Nowo\WikiBundle\Interchange\WikiDocumentImporter;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Link\WikiPageLinkService;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Routing\WikiRouteLoader;
use Nowo\WikiBundle\Routing\WikiTenantPaths;
use Nowo\WikiBundle\Security\LegacyWikiSpaceAccessChecker;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Service\AccessibleWikiSpaceLocator;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiPageTreeBuilder;
use Nowo\WikiBundle\Service\WikiRevisionDiffService;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Ui\WikiPageFactory;
use Nowo\WikiBundle\Ui\WikiPages;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

use function array_filter;
use function array_slice;
use function array_values;
use function ceil;
use function count;
use function is_string;
use function max;
use function min;

/**
 * Authenticated wiki management (spaces, pages, revisions, search).
 *
 * Soft-gated via {@see WikiAccessCheckerInterface}; set security.allow_unauthenticated
 * for trusted demos only (never in production).
 */
final class WikiManageController extends AbstractController
{
    use WikiCsrfTrait;

    /** Catalogue keys of the {@see WikiPageService} errors a page form can hit (1.12). */
    private const PAGE_ERROR_KEYS = [
        'Slug cannot be empty.'                   => 'wiki.page.error.invalid_slug',
        'Invalid page slug.'                      => 'wiki.page.error.invalid_slug',
        'Page slug already exists in this space.' => 'wiki.page.error.slug_exists',
    ];

    /** Defaults of the nowo_wiki.pagination config node (REQ-PERF-001). */
    public const DEFAULT_PAGINATION = [
        'spaces_per_page'    => 24,
        'revisions_per_page' => 25,
        'max_tree_pages'     => 500,
    ];

    /**
     * Wires the wiki services and the processed nowo_wiki configuration.
     *
     * @param array<string, array{path: string, name: string}> $routes route name/path per action
     * @param array<string, string> $templates Twig template per page
     * @param array{type?: string, tiptap_config: string, markdown_form_type?: string|null} $editor editor options
     * @param array{enabled: bool, max_upload_bytes: int} $importExport import/export options
     * @param array{spaces_per_page: int, revisions_per_page: int, max_tree_pages: int} $pagination list size limits
     * @param WikiSpaceGuard|null $spaceGuard space- and page-aware access (1.9; null = the 1.x behaviour)
     * @param WikiPageLinkService|null $linkService host-object links for "Used in" (1.9)
     * @param EventDispatcherInterface|null $eventDispatcher dispatches {@see WikiEvents::EDITOR_OPTIONS} (1.9)
     * @param array{enabled: bool} $attachments attachment options (1.9)
     * @param WikiPageFactory|null $pageFactory UiKit view models of the pages (1.10; null = built from the router)
     * @param TranslatorInterface|null $translator translates flash messages in the NowoWikiBundle domain when they are added (1.10)
     * @param WikiMarkdownConverter|null $markdownConverter turns an HTML revision into the Markdown of the editor (1.11)
     * @param WikiTenantPaths|null $tenantPaths tenant paths (1.12: with {@code tenant_only} the search keeps its space)
     */
    public function __construct(
        private readonly WikiAccessCheckerInterface $accessChecker,
        private readonly WikiSpaceAccessResolverInterface $spaceAccessResolver,
        private readonly WikiPageRepositoryInterface $pageRepository,
        private readonly WikiPageRevisionRepositoryInterface $revisionRepository,
        private readonly WikiPageService $pageService,
        private readonly WikiPageTreeBuilder $pageTreeBuilder,
        private readonly WikiRevisionDiffService $revisionDiffService,
        private readonly WikiSearchService $searchService,
        private readonly WikiAiAssistantInterface $aiAssistant,
        private readonly WikiDocumentImporter $documentImporter,
        private readonly WikiDocumentExporter $documentExporter,
        private readonly WikiArchiveHelper $archiveHelper,
        private readonly array $routes,
        private readonly array $templates,
        private readonly ?string $dashboardRoute,
        private readonly array $editor,
        private readonly array $importExport,
        private readonly bool $allowUnauthenticated = false,
        private readonly ?WikiTokenGuard $tokenGuard = null,
        private readonly array $pagination = self::DEFAULT_PAGINATION,
        ?WikiSpaceGuard $spaceGuard = null,
        private readonly ?WikiPageLinkService $linkService = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly array $attachments = ['enabled' => false],
        private readonly ?WikiPageFactory $pageFactory = null,
        private readonly ?TranslatorInterface $translator = null,
        ?WikiMarkdownConverter $markdownConverter = null,
        private readonly ?WikiTenantPaths $tenantPaths = null,
    ) {
        $this->spaceGuard = $spaceGuard ?? new WikiSpaceGuard(
            new AccessibleWikiSpaceLocator($this->spaceAccessResolver),
            new LegacyWikiSpaceAccessChecker($this->accessChecker, $this->spaceAccessResolver),
        );
        $this->markdownConverter = $markdownConverter ?? new WikiMarkdownConverter();
    }

    private readonly WikiSpaceGuard $spaceGuard;

    private readonly WikiMarkdownConverter $markdownConverter;

    /**
     * Lists the spaces the user can access, paginated by {@code pagination.spaces_per_page} ({@code ?page=N}).
     *
     * @param Request|null $request current request (reads the {@code page} query parameter)
     *
     * @return Response rendered index page
     */
    #[Route(path: '/tools/wiki', name: 'nowo_wiki_index', options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'index'], methods: ['GET'])]
    public function index(?Request $request = null): Response
    {
        $this->denyUnlessFeature('list');
        $user      = $this->requireUser();
        $spaces    = $this->readableSpaces($user);
        $perPage   = max(1, $this->pagination['spaces_per_page']);
        $total     = count($spaces);
        $pageCount = max(1, (int) ceil($total / $perPage));
        $current   = min($pageCount, max(1, $request?->query->getInt('page', 1) ?? 1));
        $canAskAi  = $this->accessChecker->canAskAi($user) && $this->aiAssistant->isAvailable() && isset($this->routes['ai_ask']);
        $pages     = $this->pages();

        return $this->renderPage('index', [
            'page' => $pages->index(
                array_slice($spaces, ($current - 1) * $perPage, $perPage),
                // 1.12: no pagination region (empty card / "1 of 1") when everything fits on one page.
                $total > $perPage ? Pagination::fromTotal($total, $current, $perPage, [$perPage], $perPage, $this->routes['index']['name']) : null,
                $this->createSearchForm($spaces, [], ['space' => 'wiki-index-search-space', 'q' => 'wiki-index-search-q']),
                $canAskAi,
            ),
            'ai_form'         => $canAskAi ? $this->createAiAskForm($spaces, [], ['space' => 'wiki-ai-modal-space', 'q' => 'wiki-ai-modal-question'], ['autofocus' => true])->createView() : null,
            'routes'          => $this->routes,
            'dashboard_route' => $this->dashboardRoute,
            'can_create'      => $this->accessChecker->canCreate($user),
            'can_ask_ai'      => $canAskAi,
        ]);
    }

    /**
     * Shows a space with its page tree (capped at {@code pagination.max_tree_pages}).
     *
     * @param string $spaceSlug space slug from the route
     *
     * @return Response rendered space page
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}', name: 'nowo_wiki_space', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'space'], methods: ['GET'])]
    public function space(string $spaceSlug): Response
    {
        $this->denyUnlessFeature('list');
        $user                   = $this->requireUser();
        $space                  = $this->requireAccessibleSpace($user, $spaceSlug);
        [$tree, $treeTruncated] = $this->buildTree($user, $space);
        $permissions            = $this->spaceGuard->grants($user, $space);
        $archived               = $permissions['archive'] && isset($this->routes['page_unarchive'])
            ? $this->visiblePages($user, $space, $this->pageRepository->findArchivedBySpace($space))
            : [];
        $can = [
            'create' => $this->accessChecker->canCreate($user) && $permissions['create'],
            'import' => $this->importExport['enabled'] && $this->accessChecker->canImport($user) && $permissions['import'],
            'export' => $this->importExport['enabled'] && $this->accessChecker->canExport($user) && $permissions['export'],
        ];
        $pages = $this->pages();

        return $this->renderPage('space', [
            'page'             => $pages->space($space, $can),
            'tree'             => $pages->tree($space, $tree),
            'tree_truncated'   => $treeTruncated,
            'max_tree_pages'   => $this->pagination['max_tree_pages'],
            'tree_search_href' => $this->treeSearchHref($space),
            'archived'         => $pages->archived($space, $archived, $this->postActionForms($archived, fn (WikiPage $item): array => [
                $item->getId(),
                $this->generateUrl($this->routes['page_unarchive']['name'], ['spaceSlug' => $space->getSlug(), 'pageSlug' => $item->getSlug()]),
                'wiki_unarchive_' . $item->getId(),
            ])),
            'search_form'     => $this->createSearchForm(null, ['space' => $space->getSlug()], ['q' => 'wiki-space-search'], false),
            'import_form'     => $can['import'] ? $this->createImportForm($space)->createView() : null,
            'routes'          => $this->routes,
            'dashboard_route' => $this->dashboardRoute,
            'can_create'      => $can['create'],
            'can_import'      => $can['import'],
            'can_export'      => $can['export'],
            'permissions'     => $permissions,
        ]);
    }

    /**
     * Shows the current revision of a page next to the (capped) space tree.
     *
     * @param string $spaceSlug space slug from the route
     * @param string $pageSlug page slug from the route
     *
     * @return Response rendered page view
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}', name: 'nowo_wiki_page_view', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG, 'pageSlug' => WikiRouteLoader::PAGE_SLUG], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'page_view'], methods: ['GET'])]
    public function viewPage(string $spaceSlug, string $pageSlug): Response
    {
        $this->denyUnlessFeature('list');
        $user                   = $this->requireUser();
        $space                  = $this->requireAccessibleSpace($user, $spaceSlug);
        $page                   = $this->requirePage($user, $space, $pageSlug);
        [$tree, $treeTruncated] = $this->buildTree($user, $space);
        $permissions            = $this->spaceGuard->grants($user, $space, $page);
        $can                    = [
            'edit'    => $this->accessChecker->canEdit($user) && $permissions['edit'],
            'history' => $this->accessChecker->canViewHistory($user) && $permissions['history'],
            'archive' => $this->accessChecker->canArchive($user) && $permissions['archive'] && isset($this->routes['page_archive']),
        ];
        $archiveForm = $can['archive'] ? $this->postActionForms([$page], fn (WikiPage $item): array => [
            0,
            $this->generateUrl($this->routes['page_archive']['name'], ['spaceSlug' => $space->getSlug(), 'pageSlug' => $item->getSlug()]),
            'wiki_archive_' . $item->getId(),
        ])[0] : null;
        $pages = $this->pages();

        return $this->renderPage('page_view', [
            'page'             => $pages->pageView($space, $page, $can, $archiveForm),
            'content_html'     => $page->getCurrentRevision()?->getContentHtml() ?? '',
            'tree'             => $pages->tree($space, $tree, $page->getSlug()),
            'tree_truncated'   => $treeTruncated,
            'max_tree_pages'   => $this->pagination['max_tree_pages'],
            'tree_search_href' => $this->treeSearchHref($space),
            'used_in'          => $pages->usedIn($this->linkService?->usedIn($page, $user) ?? []),
            'routes'           => $this->routes,
            'dashboard_route'  => $this->dashboardRoute,
            'can_edit'         => $can['edit'],
            'can_history'      => $can['history'],
            'can_archive'      => $can['archive'],
            'permissions'      => $permissions,
        ]);
    }

    /**
     * Shows the edit form of a page and saves a new revision on valid submission.
     *
     * Redirects to the page view after saving; re-renders the form otherwise.
     *
     * @param Request $request current request (form submission)
     * @param string $spaceSlug space slug from the route
     * @param string $pageSlug page slug from the route
     *
     * @return Response rendered edit form or redirect to the page view
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/edit', name: 'nowo_wiki_page_edit', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG, 'pageSlug' => WikiRouteLoader::PAGE_SLUG], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'page_edit'], methods: ['GET', 'POST'])]
    public function editPage(Request $request, string $spaceSlug, string $pageSlug): Response
    {
        $this->denyUnlessFeature('edit');
        $user  = $this->requireUser();
        $space = $this->requireAccessibleSpace($user, $spaceSlug);
        $page  = $this->requirePage($user, $space, $pageSlug);
        $this->spaceGuard->requireGranted($user, WikiAction::Edit, $space, $page);

        $data = new WikiPageFormData(
            $page->getTitle(),
            $this->editorBody($page->getCurrentRevision()),
        );

        // REQ-TWIG-005: the Form's own CSRF field (token id wiki_edit_<page id>) replaces the hand-rolled top-level _token.
        $form = $this->createForm(WikiPageFormType::class, $data, [
            'csrf_token_id'  => 'wiki_edit_' . $page->getId(),
            'editor_options' => $this->editorOptions($user, $space, $page),
        ] + $this->pageFormOptions());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var WikiPageFormData $payload */
            $payload = $form->getData();
            $this->pageService->saveRevision($page, $payload->title, $payload->content, $user, $this->editorFormat());

            return $this->redirectToRoute($this->routes['page_view']['name'], [
                'spaceSlug' => $space->getSlug(),
                'pageSlug'  => $page->getSlug(),
            ]);
        }

        return $this->renderPage('page_edit', [
            'page'            => $this->pages()->pageForm($space, $page, $form->createView()),
            'routes'          => $this->routes,
            'dashboard_route' => $this->dashboardRoute,
            'csrf_token_id'   => 'wiki_edit_' . $page->getId(),
        ], $form);
    }

    /**
     * Shows the create-page form and creates the page (with its first revision) on valid submission.
     *
     * Re-renders the form with an error flash when the page service rejects the input (e.g. duplicate slug).
     *
     * @param Request $request current request (form submission)
     * @param string $spaceSlug space slug from the route
     *
     * @return Response rendered create form or redirect to the new page
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/pages/new', name: 'nowo_wiki_page_new', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'page_new'], methods: ['GET', 'POST'])]
    public function newPage(Request $request, string $spaceSlug): Response
    {
        $this->denyUnlessFeature('create');
        $user  = $this->requireUser();
        $space = $this->requireAccessibleSpace($user, $spaceSlug);
        $this->spaceGuard->requireGranted($user, WikiAction::Create, $space);
        $data = new WikiPageFormData();

        // REQ-TWIG-005: the Form's own CSRF field (token id wiki_new_<space id>) replaces the hand-rolled top-level _token.
        $form = $this->createForm(WikiPageFormType::class, $data, [
            'csrf_token_id'  => 'wiki_new_' . $space->getId(),
            'editor_options' => $this->editorOptions($user, $space, null),
        ] + $this->pageFormOptions());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var WikiPageFormData $payload */
            $payload = $form->getData();

            try {
                $page = $this->pageService->create($space, $payload->title, $payload->content, $user, null, null, $this->editorFormat());
            } catch (InvalidArgumentException $exception) {
                $this->flash('error', $this->pageErrorMessage($exception));

                return $this->renderNewPage($space, $form);
            }

            return $this->redirectToRoute($this->routes['page_view']['name'], [
                'spaceSlug' => $space->getSlug(),
                'pageSlug'  => $page->getSlug(),
            ]);
        }

        return $this->renderNewPage($space, $form);
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function renderNewPage(WikiSpace $space, FormInterface $form): Response
    {
        return $this->renderPage('page_edit', [
            'page'            => $this->pages()->pageForm($space, null, $form->createView()),
            'routes'          => $this->routes,
            'dashboard_route' => $this->dashboardRoute,
            'csrf_token_id'   => 'wiki_new_' . $space->getId(),
        ], $form);
    }

    /**
     * Lists the revisions of a page, newest first, paginated by {@code pagination.revisions_per_page} ({@code ?page=N}).
     *
     * @param string $spaceSlug space slug from the route
     * @param string $pageSlug page slug from the route
     * @param Request|null $request current request (reads the {@code page} query parameter)
     *
     * @return Response rendered history page
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/history', name: 'nowo_wiki_page_history', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG, 'pageSlug' => WikiRouteLoader::PAGE_SLUG], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'page_history'], methods: ['GET'])]
    public function pageHistory(string $spaceSlug, string $pageSlug, ?Request $request = null): Response
    {
        $this->denyUnlessFeature('history');
        $user  = $this->requireUser();
        $space = $this->requireAccessibleSpace($user, $spaceSlug);
        $page  = $this->requirePage($user, $space, $pageSlug);
        $this->spaceGuard->requireGranted($user, WikiAction::History, $space, $page);
        $perPage = max(1, $this->pagination['revisions_per_page']);
        $current = max(1, $request?->query->getInt('page', 1) ?? 1);
        // One extra row: tells whether an older page exists and gives the last row its "compare" target.
        $fetched   = $this->revisionRepository->findByPage($page, $perPage + 1, ($current - 1) * $perPage);
        $revisions = array_slice($fetched, 0, $perPage);
        // Revisions are append-only and numbered 1..N: the current number is the total (at least what was fetched).
        $total = max($page->getCurrentRevision()?->getRevisionNumber() ?? 0, ($current - 1) * $perPage + count($fetched));
        $route = $this->routes['page_history']['name'];

        return $this->renderPage('page_history', [
            'page' => $this->pages()->history(
                $space,
                $page,
                $revisions,
                $fetched[$perPage] ?? null,
                $this->restoreForms($user, $space, $page, $revisions),
                // 1.12: no pagination region (empty card / "1 of 1") when every revision fits on one page.
                $total > $perPage ? Pagination::fromTotal($total, $current, $perPage, [$perPage], $perPage, $route, [], ['spaceSlug' => $space->getSlug(), 'pageSlug' => $page->getSlug()]) : null,
            ),
            'routes'          => $this->routes,
            'dashboard_route' => $this->dashboardRoute,
        ]);
    }

    /**
     * Renders the diff between two revisions of a page selected by the {@code from} / {@code to} query numbers.
     *
     * @param Request $request current request (reads the {@code from} and {@code to} query parameters)
     * @param string $spaceSlug space slug from the route
     * @param string $pageSlug page slug from the route
     *
     * @throws NotFoundHttpException when either revision does not exist
     *
     * @return Response rendered diff page
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/diff', name: 'nowo_wiki_page_diff', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG, 'pageSlug' => WikiRouteLoader::PAGE_SLUG], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'page_diff'], methods: ['GET'])]
    public function pageDiff(Request $request, string $spaceSlug, string $pageSlug): Response
    {
        $this->denyUnlessFeature('history');
        $user  = $this->requireUser();
        $space = $this->requireAccessibleSpace($user, $spaceSlug);
        $page  = $this->requirePage($user, $space, $pageSlug);
        $this->spaceGuard->requireGranted($user, WikiAction::History, $space, $page);

        $fromNumber = $request->query->getInt('from');
        $toNumber   = $request->query->getInt('to');
        $from       = $this->revisionRepository->findByPageAndNumber($page, $fromNumber);
        $to         = $this->revisionRepository->findByPageAndNumber($page, $toNumber);

        if (!$from instanceof WikiPageRevision || !$to instanceof WikiPageRevision) {
            throw new NotFoundHttpException('Revisions not found.');
        }

        $diff = $this->revisionDiffService->diff($from, $to);

        return $this->renderPage('page_diff', [
            'page'            => $this->pages()->diff($space, $page, $from->getRevisionNumber(), $to->getRevisionNumber()),
            'diff'            => $diff,
            'routes'          => $this->routes,
            'dashboard_route' => $this->dashboardRoute,
        ]);
    }

    /**
     * Archives a page after validating its CSRF token, then redirects to the space.
     *
     * @param Request $request current request (carries the CSRF token)
     * @param string $spaceSlug space slug from the route
     * @param string $pageSlug page slug from the route
     *
     * @throws AccessDeniedHttpException when the CSRF token is invalid
     *
     * @return RedirectResponse redirect to the space page
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/archive', name: 'nowo_wiki_page_archive', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG, 'pageSlug' => WikiRouteLoader::PAGE_SLUG], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'page_archive'], methods: ['POST'])]
    public function archivePage(Request $request, string $spaceSlug, string $pageSlug): RedirectResponse
    {
        $this->denyUnlessFeature('archive');
        $user  = $this->requireUser();
        $space = $this->requireAccessibleSpace($user, $spaceSlug);
        $page  = $this->requirePage($user, $space, $pageSlug);
        $this->spaceGuard->requireGranted($user, WikiAction::Archive, $space, $page);

        if (!$this->isValidCsrf($request, 'wiki_archive_' . $page->getId())) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $this->pageService->archive($page, $user);

        return $this->redirectToRoute($this->routes['space']['name'], ['spaceSlug' => $space->getSlug()]);
    }

    /**
     * Brings an archived page back after validating its CSRF token, then redirects to the page (1.9).
     *
     * @param Request $request current request (carries the CSRF token)
     * @param string $spaceSlug space slug from the route
     * @param string $pageSlug page slug from the route
     *
     * @throws NotFoundHttpException when the page is missing or not archived
     * @throws AccessDeniedHttpException when the action is denied or the CSRF token is invalid
     *
     * @return RedirectResponse redirect to the page
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/unarchive', name: 'nowo_wiki_page_unarchive', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG, 'pageSlug' => WikiRouteLoader::PAGE_SLUG], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'page_unarchive'], methods: ['POST'])]
    public function unarchivePage(Request $request, string $spaceSlug, string $pageSlug): RedirectResponse
    {
        $this->denyUnlessFeature('archive');
        $user  = $this->requireUser();
        $space = $this->requireAccessibleSpace($user, $spaceSlug);
        $page  = $this->pageRepository->findBySlug($space, $pageSlug);
        if (!$page instanceof WikiPage || !$page->isArchived()) {
            throw new NotFoundHttpException('Page not found.');
        }

        $this->spaceGuard->requireGranted($user, WikiAction::View, $space, $page);
        $this->spaceGuard->requireGranted($user, WikiAction::Archive, $space, $page);
        if (!$this->isValidCsrf($request, 'wiki_unarchive_' . $page->getId())) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $this->pageService->unarchive($page, $user);

        return $this->redirectToRoute($this->routes['page_view']['name'], ['spaceSlug' => $space->getSlug(), 'pageSlug' => $page->getSlug()]);
    }

    /**
     * Restores an old revision as a new revision (history stays append-only) after validating the CSRF token (1.9).
     *
     * @param Request $request current request (carries the CSRF token)
     * @param string $spaceSlug space slug from the route
     * @param string $pageSlug page slug from the route
     * @param int $revisionNumber number of the revision to restore
     *
     * @throws NotFoundHttpException when the page or the revision is missing
     * @throws AccessDeniedHttpException when the action is denied or the CSRF token is invalid
     *
     * @return RedirectResponse redirect to the page
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/revisions/{revisionNumber}/restore', name: 'nowo_wiki_page_restore', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG, 'pageSlug' => WikiRouteLoader::PAGE_SLUG, 'revisionNumber' => '\d+'], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'page_restore'], methods: ['POST'])]
    public function restoreRevision(Request $request, string $spaceSlug, string $pageSlug, int $revisionNumber): RedirectResponse
    {
        $this->denyUnlessFeature('edit');
        $user  = $this->requireUser();
        $space = $this->requireAccessibleSpace($user, $spaceSlug);
        $page  = $this->requirePage($user, $space, $pageSlug);
        $this->spaceGuard->requireGranted($user, WikiAction::Restore, $space, $page);

        // CSRF first: the revision lookup must not tell a forged request which revision numbers exist.
        if (!$this->isValidCsrf($request, 'wiki_restore_' . $page->getId())) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $revision = $this->revisionRepository->findByPageAndNumber($page, $revisionNumber);
        if (!$revision instanceof WikiPageRevision) {
            throw new NotFoundHttpException('Revision not found.');
        }

        $this->pageService->restore($page, $revision, $user);
        $this->flash('success', 'wiki.history.restored');

        return $this->redirectToRoute($this->routes['page_view']['name'], ['spaceSlug' => $space->getSlug(), 'pageSlug' => $page->getSlug()]);
    }

    /**
     * Searches pages within one space ({@code ?space=}) or across all accessible spaces ({@code ?q=} only).
     *
     * @param Request $request current request (reads the {@code q} and {@code space} query parameters)
     *
     * @return Response rendered search results page
     */
    #[Route(path: '/tools/wiki/search', name: 'nowo_wiki_search', options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'search'], methods: ['GET'])]
    public function search(Request $request): Response
    {
        $this->denyUnlessFeature('list');
        $user      = $this->requireUser();
        $query     = $request->query->getString('q');
        $spaceSlug = $request->query->getString('space');
        $results   = [];
        $space     = null;
        $spaces    = $this->readableSpaces($user);
        $global    = false;

        if ($spaceSlug !== '') {
            $space   = $this->requireAccessibleSpace($user, $spaceSlug);
            $results = $query !== '' ? $this->searchService->search($space, $query) : [];
        } elseif ($query !== '') {
            $results = $this->searchService->searchAcrossSpaces($spaces, $query);
            $global  = true;
        }

        // 1.12: with tenant_only every page lives under a tenant path, so there is no "all spaces" to pick: the space
        // stays a hidden field (the searched space).
        $form = $this->createSearchForm(
            $this->tenantPaths?->isTenantOnly() === true ? null : $spaces,
            ['space' => $space?->getSlug(), 'q' => $query],
            ['space' => 'wiki-search-space', 'q' => 'wiki-search-q'],
        );

        return $this->renderPage('search', [
            'page'            => $this->pages()->search($form, $this->readableHits($user, $results), $query, $global),
            'query'           => $query,
            'global_search'   => $global,
            'routes'          => $this->routes,
            'dashboard_route' => $this->dashboardRoute,
        ]);
    }

    /**
     * Exports a space to Outline or Notion Markdown and streams it as a ZIP download (deleted after send).
     *
     * @param Request $request current request (reads the {@code format} query parameter)
     * @param string $spaceSlug space slug from the route
     *
     * @throws NotFoundHttpException when import/export is disabled
     *
     * @return BinaryFileResponse ZIP attachment response
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/export', name: 'nowo_wiki_space_export', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'space_export'], methods: ['GET'])]
    public function exportSpace(Request $request, string $spaceSlug): BinaryFileResponse
    {
        $this->denyUnlessFeature('export');
        if (!$this->importExport['enabled']) {
            throw new NotFoundHttpException('Import/export is disabled.');
        }

        $user  = $this->requireUser();
        $space = $this->requireAccessibleSpace($user, $spaceSlug);
        $this->spaceGuard->requireGranted($user, WikiAction::Export, $space);
        $format = WikiInterchangeFormat::tryFrom($request->query->getString('format', WikiInterchangeFormat::Outline->value))
            ?? WikiInterchangeFormat::Outline;
        if ($format === WikiInterchangeFormat::Auto) {
            $format = WikiInterchangeFormat::Outline;
        }

        $workingDir = sys_get_temp_dir() . '/wiki-export-' . bin2hex(random_bytes(8));
        // @codeCoverageIgnoreStart
        if (!mkdir($workingDir, 0700, true) && !is_dir($workingDir)) {
            throw new RuntimeException('Unable to create export directory.');
        }
        // @codeCoverageIgnoreEnd

        $zipPath = sys_get_temp_dir() . '/wiki-export-' . $space->getSlug() . '-' . bin2hex(random_bytes(4)) . '.zip';
        try {
            $this->documentExporter->export($space, $workingDir, $format, fn (array $pages): array => $this->visiblePages($user, $space, $pages));
            $this->archiveHelper->createZipFromDirectory($workingDir, $zipPath);
        } catch (Throwable $exception) {
            // @codeCoverageIgnoreStart
            if (is_file($zipPath)) {
                unlink($zipPath);
            }
            // @codeCoverageIgnoreEnd

            throw $exception;
        } finally {
            // @igor-ignore - HTTP handler delegates to services; no controller worker state.
            $this->archiveHelper->removeDirectory($workingDir);
        }

        // Explicit Content-Type: BinaryFileResponse::prepare() would otherwise guess it via
        // symfony/mime, which is not a dependency of this bundle (500 when it is absent).
        $response = new BinaryFileResponse($zipPath, Response::HTTP_OK, ['Content-Type' => 'application/zip']);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $space->getSlug() . '-' . $format->value . '-export.zip',
        );
        $response->deleteFileAfterSend(true);

        return $response;
    }

    /**
     * Imports an uploaded Outline/Notion ZIP export into a space and flashes the import report.
     *
     * Missing, oversized or non-ZIP uploads and importer validation errors are reported as error flashes.
     *
     * @param Request $request current request (import form submission with the uploaded archive)
     * @param string $spaceSlug space slug from the route
     *
     * @throws NotFoundHttpException when import/export is disabled
     * @throws AccessDeniedHttpException when the form or its CSRF token is invalid
     *
     * @return RedirectResponse redirect to the space page
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/import', name: 'nowo_wiki_space_import', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'space_import'], methods: ['POST'])]
    public function importSpace(Request $request, string $spaceSlug): RedirectResponse
    {
        $this->denyUnlessFeature('import');
        if (!$this->importExport['enabled']) {
            throw new NotFoundHttpException('Import/export is disabled.');
        }

        $user  = $this->requireUser();
        $space = $this->requireAccessibleSpace($user, $spaceSlug);
        $this->spaceGuard->requireGranted($user, WikiAction::Import, $space);

        $form = $this->createImportForm($space);
        if (!$this->isValidSubmittedForm($form, $request)) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $upload = $form->get('archive')->getData();
        if (!$upload instanceof UploadedFile) {
            $this->flash('error', 'wiki.import.missing_file');

            return $this->redirectToRoute($this->routes['space']['name'], ['spaceSlug' => $space->getSlug()]);
        }

        if ($upload->getSize() > $this->importExport['max_upload_bytes']) {
            $this->flash('error', 'wiki.import.file_too_large');

            return $this->redirectToRoute($this->routes['space']['name'], ['spaceSlug' => $space->getSlug()]);
        }

        $formatValue = $form->get('format')->getData();
        $format      = WikiInterchangeFormat::tryFrom(is_string($formatValue) ? $formatValue : '') ?? WikiInterchangeFormat::Auto;

        if (strtolower($upload->getClientOriginalExtension()) !== 'zip') {
            $this->flash('error', 'wiki.import.invalid_archive');

            return $this->redirectToRoute($this->routes['space']['name'], ['spaceSlug' => $space->getSlug()]);
        }

        $zipTempPath = sys_get_temp_dir() . '/wiki-upload-' . bin2hex(random_bytes(8)) . '.zip';
        if (!copy($upload->getPathname(), $zipTempPath)) {
            $this->flash('error', 'wiki.import.invalid_archive');

            return $this->redirectToRoute($this->routes['space']['name'], ['spaceSlug' => $space->getSlug()]);
        }

        try {
            $report = $this->documentImporter->import(
                $space,
                $zipTempPath,
                $format,
                $user,
                $form->get('update_existing')->getData() === true,
            );
        } catch (InvalidArgumentException $exception) {
            $this->flash('error', new TranslatableMessage('wiki.import.failed', ['%reason%' => $exception->getMessage()], WikiPages::DOMAIN));

            return $this->redirectToRoute($this->routes['space']['name'], ['spaceSlug' => $space->getSlug()]);
        } finally {
            if (is_file($zipTempPath)) {
                unlink($zipTempPath);
            }
        }

        $this->flash('success', 'wiki.import.success');
        $this->flash('info', new TranslatableMessage('wiki.import.report', [
            '%created%' => $report->created,
            '%updated%' => $report->updated,
            '%skipped%' => $report->skipped,
            '%failed%'  => $report->failed,
        ], WikiPages::DOMAIN));

        foreach ($report->messages as $message) {
            $this->flash('warning', $message);
        }

        return $this->redirectToRoute($this->routes['space']['name'], ['spaceSlug' => $space->getSlug()]);
    }

    /**
     * Shows the AI question form and, on a valid POST, asks the AI assistant and renders its answer and sources.
     *
     * Assistant failures are rendered as an error message instead of being rethrown.
     *
     * @param Request $request current request (reads {@code q} and {@code space} from the body or query)
     *
     * @throws AccessDeniedHttpException when a POST carries an invalid CSRF token
     *
     * @return Response rendered AI question page
     */
    #[Route(path: '/tools/wiki/ask', name: 'nowo_wiki_ai_ask', options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'ai_ask'], methods: ['GET', 'POST'])]
    public function askAi(Request $request): Response
    {
        $this->denyUnlessFeature('ai');
        $user      = $this->requireUser();
        $spaces    = $this->readableSpaces($user, WikiAction::AskAi);
        $question  = trim($request->request->getString('q', $request->query->getString('q')));
        $spaceSlug = $request->request->getString('space', $request->query->getString('space'));
        $space     = null;
        $answer    = null;
        $sources   = [];
        $error     = null;

        if ($spaceSlug !== '') {
            $space = $this->requireAccessibleSpace($user, $spaceSlug);
            $this->spaceGuard->requireGranted($user, WikiAction::AskAi, $space);
        }

        if (!$this->aiAssistant->isAvailable()) {
            $error = 'wiki.ai.unavailable';
        } elseif ($request->isMethod('POST')) {
            if (!$this->isValidSubmittedForm($this->createAiAskForm($spaces, [], []), $request)) {
                throw new AccessDeniedHttpException('Invalid CSRF token.');
            }

            if ($question === '') {
                $error = 'wiki.ai.empty_question';
            } else {
                try {
                    $result  = $this->aiAssistant->ask($user, $question, $space);
                    $answer  = $result->answer;
                    $sources = $result->sources;
                } catch (Throwable) {
                    $error = 'wiki.ai.error';
                }
            }
        }

        $pages = $this->pages();

        return $this->renderPage('ai_ask', [
            'page' => $pages->aiAsk($this->createAiAskForm(
                $spaces,
                ['space' => $space?->getSlug(), 'q' => $question],
                ['space' => 'wiki-ai-space', 'q' => 'wiki-ai-question'],
            )->createView(), $answer !== null),
            'question'        => $question,
            'answer'          => $answer,
            'sources'         => $pages->hits($this->readableHits($user, $sources)),
            'error'           => $error,
            'routes'          => $this->routes,
            'dashboard_route' => $this->dashboardRoute,
            'csrf_token_id'   => WikiAiAskType::CSRF_TOKEN_ID,
        ]);
    }

    /**
     * GET search filter (flat {@code space} / {@code q}); {@code $spaces === null} renders the space as a hidden field.
     *
     * @param list<WikiSpace>|null $spaces
     * @param array<string, mixed> $data
     * @param array<string, string> $ids
     */
    private function createSearchForm(?array $spaces, array $data, array $ids, bool $queryRequired = true): FormView
    {
        return $this->createForm(WikiSearchType::class, $data, [
            'action'         => $this->generateUrl($this->routes['search']['name']),
            'space_choices'  => $spaces === null ? null : $this->spaceChoices($spaces),
            'element_ids'    => $ids,
            'query_required' => $queryRequired,
        ])->createView();
    }

    /**
     * AI question form (flat {@code space} / {@code q} / {@code _token}).
     *
     * @param list<WikiSpace> $spaces
     * @param array<string, mixed> $data
     * @param array<string, string> $ids
     * @param array<string, mixed> $questionAttr
     *
     * @return FormInterface<mixed>
     */
    private function createAiAskForm(array $spaces, array $data, array $ids, array $questionAttr = []): FormInterface
    {
        return $this->createForm(WikiAiAskType::class, $data, [
            'action'        => isset($this->routes['ai_ask']) ? $this->generateUrl($this->routes['ai_ask']['name']) : '',
            'space_choices' => $this->spaceChoices($spaces),
            'element_ids'   => $ids,
            'question_attr' => $questionAttr,
        ]);
    }

    /**
     * @return FormInterface<mixed>
     */
    private function createImportForm(WikiSpace $space): FormInterface
    {
        return $this->createForm(WikiImportType::class, null, [
            'action'        => $this->generateUrl($this->routes['space_import']['name'], ['spaceSlug' => $space->getSlug()]),
            'csrf_token_id' => 'wiki_import_' . $space->getId(),
        ]);
    }

    /**
     * @param list<WikiSpace> $spaces
     *
     * @return array<string, string> slug => name
     */
    private function spaceChoices(array $spaces): array
    {
        $choices = [];
        foreach ($spaces as $item) {
            $choices[$item->getSlug()] = $item->getName();
        }

        return $choices;
    }

    /**
     * Adds a flash message in the bundle domain: a key (or a text printed as given when the catalogue has no entry for
     * it) or a {@see TranslatableMessage} with parameters (1.12). With the translator it is stored translated, so any
     * host layout prints it without knowing the domain; without one the {@see TranslatableMessage} itself is stored, so
     * a layout that prints flashes through the {@code trans} filter still translates it (never a bare key string).
     */
    private function flash(string $type, string|TranslatableMessage $message): void
    {
        $message = is_string($message) ? new TranslatableMessage($message, [], WikiPages::DOMAIN) : $message;
        $this->addFlash($type, $this->translator instanceof TranslatorInterface ? $message->trans($this->translator) : $message);
    }

    /**
     * The catalogue message of a page create error ({@see WikiPageService} refuses an empty, invalid or duplicate slug); any
     * other reason is wrapped in {@code wiki.page.error.save_failed} (1.12).
     */
    private function pageErrorMessage(InvalidArgumentException $exception): TranslatableMessage
    {
        $key = self::PAGE_ERROR_KEYS[$exception->getMessage()] ?? null;

        return $key !== null
            ? new TranslatableMessage($key, [], WikiPages::DOMAIN)
            : new TranslatableMessage('wiki.page.error.save_failed', ['%reason%' => $exception->getMessage()], WikiPages::DOMAIN);
    }

    private function denyUnlessFeature(string $feature): void
    {
        if ($this->allowUnauthenticated) {
            return;
        }

        $user = $this->currentUser();

        $allowed = match ($feature) {
            'list'    => $this->accessChecker->canList($user),
            'create'  => $this->accessChecker->canCreate($user),
            'edit'    => $this->accessChecker->canEdit($user),
            'history' => $this->accessChecker->canViewHistory($user),
            'archive' => $this->accessChecker->canArchive($user),
            'ai'      => $this->accessChecker->canAskAi($user),
            'import'  => $this->accessChecker->canImport($user),
            'export'  => $this->accessChecker->canExport($user),
            default   => $this->accessChecker->canAccess($user),
        };

        if (!$allowed) {
            throw new AccessDeniedHttpException('Access denied.');
        }
    }

    private function requireUser(): UserInterface
    {
        $user = $this->currentUser();
        if (!$user instanceof UserInterface) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        return $user;
    }

    private function currentUser(): ?UserInterface
    {
        if ($this->tokenGuard instanceof WikiTokenGuard && !$this->tokenGuard->isTokenTrusted()) {
            return null;
        }

        $user = $this->getUser();

        return $user instanceof UserInterface ? $user : null;
    }

    private function requireAccessibleSpace(UserInterface $user, string $spaceSlug): WikiSpace
    {
        return $this->spaceGuard->requireSpace($user, $spaceSlug);
    }

    /**
     * Restore buttons of the listed revisions, keyed by revision number (none for the current one, none without
     * {@see WikiAction::Restore}).
     *
     * @param list<WikiPageRevision> $revisions
     *
     * @return array<int|string, FormView>
     */
    private function restoreForms(UserInterface $user, WikiSpace $space, WikiPage $page, array $revisions): array
    {
        if (!isset($this->routes['page_restore']) || !$this->spaceGuard->isGranted($user, WikiAction::Restore, $space, $page)) {
            return [];
        }

        $currentId = $page->getCurrentRevision()?->getId();
        $older     = array_filter($revisions, static fn (WikiPageRevision $revision): bool => $revision->getId() !== $currentId);

        return $this->postActionForms(array_values($older), fn (WikiPageRevision $revision): array => [
            $revision->getRevisionNumber(),
            $this->generateUrl($this->routes['page_restore']['name'], [
                'spaceSlug'      => $space->getSlug(),
                'pageSlug'       => $page->getSlug(),
                'revisionNumber' => $revision->getRevisionNumber(),
            ]),
            'wiki_restore_' . $page->getId(),
        ]);
    }

    /**
     * One {@see WikiPostActionType} view per item.
     *
     * @template T of object
     *
     * @param list<T> $items
     * @param callable(T): array{0: int|string, 1: string, 2: string} $describe key, action URL and CSRF token id
     *
     * @return array<int|string, FormView>
     */
    private function postActionForms(array $items, callable $describe): array
    {
        $forms = [];
        foreach ($items as $item) {
            [$key, $action, $tokenId] = $describe($item);
            $forms[$key]              = $this->container->get('form.factory')
                ->createNamed('', WikiPostActionType::class, null, ['action' => $action, 'csrf_token_id' => $tokenId])
                ->createView();
        }

        return $forms;
    }

    /**
     * Revision format written by the page form: Markdown with `editor.type: markdown` (1.11), HTML otherwise.
     */
    private function editorFormat(): string
    {
        return ($this->editor['type'] ?? Configuration::EDITOR_TIPTAP) === Configuration::EDITOR_MARKDOWN
            ? WikiPageRevision::FORMAT_MARKDOWN
            : WikiPageRevision::FORMAT_HTML;
    }

    /**
     * Body shown in the page form: the HTML for the Tiptap editor (the render cache of a Markdown revision), the
     * Markdown for a Markdown editor (an HTML revision converted, saved as Markdown on the next save) (1.11).
     */
    private function editorBody(?WikiPageRevision $revision): string
    {
        if (!$revision instanceof WikiPageRevision) {
            return '';
        }

        if ($this->editorFormat() === WikiPageRevision::FORMAT_HTML) {
            return $revision->getContentHtml();
        }

        return $revision->getContentMarkdown() ?? $this->markdownConverter->htmlToMarkdown($revision->getContentHtml());
    }

    /**
     * Editor options of {@see WikiPageFormType} from `nowo_wiki.editor`.
     *
     * @return array{tiptap_config: string, editor_type: string, markdown_form_type: string|null}
     */
    private function pageFormOptions(): array
    {
        return [
            'tiptap_config'      => $this->editor['tiptap_config'],
            'editor_type'        => $this->editor['type'] ?? Configuration::EDITOR_TIPTAP,
            'markdown_form_type' => $this->editor['markdown_form_type'] ?? null,
        ];
    }

    /**
     * TiptapEditorType options of the page editor: the upload hook when attachments are enabled, then whatever the
     * host adds through {@see WikiEvents::EDITOR_OPTIONS}.
     *
     * @return array<string, mixed>
     */
    private function editorOptions(UserInterface $user, WikiSpace $space, ?WikiPage $page): array
    {
        $options = [];
        if ($page instanceof WikiPage && $this->attachments['enabled'] && isset($this->routes['attachment_upload'])) {
            $options['upload_url'] = $this->generateUrl($this->routes['attachment_upload']['name'], [
                'spaceSlug' => $space->getSlug(),
                'pageSlug'  => $page->getSlug(),
            ]);
            $options['upload_csrf_token'] = $this->container->get('security.csrf.token_manager')
                ->getToken(WikiAttachmentController::csrfTokenId($page))->getValue();
        }

        if (!$this->eventDispatcher instanceof EventDispatcherInterface) {
            return $options;
        }

        $event = new WikiEditorOptionsEvent($space, $page, $user, $options);
        $this->eventDispatcher->dispatch($event, WikiEvents::EDITOR_OPTIONS);

        return $event->options;
    }

    /**
     * Builds the page tree of a space from at most {@code pagination.max_tree_pages} active pages the user may read
     * ({@see WikiAction::View} per page: a hidden page and the pages under it stay out of the tree).
     *
     * @return array{0: list<array{page: WikiPage, children: list<mixed>}>, 1: bool} tree and whether pages were left out
     */
    private function buildTree(UserInterface $user, WikiSpace $space): array
    {
        $max     = max(1, $this->pagination['max_tree_pages']);
        $visible = $this->visiblePages($user, $space, $this->pageRepository->findActiveBySpace($space, $max + 1));

        // The truncation flag counts readable pages only, so it does not reveal that hidden pages exist.
        return [$this->pageTreeBuilder->build(array_slice($visible, 0, $max)), count($visible) > $max];
    }

    /**
     * Pages the user may read with all their ancestors ({@see WikiAction::View} on each page and each parent): a
     * hidden page hides its whole sub-tree. Shared by the sidebar tree and the space export. Input order kept.
     *
     * @param list<WikiPage> $pages
     *
     * @return list<WikiPage>
     */
    private function visiblePages(UserInterface $user, WikiSpace $space, array $pages): array
    {
        /** @var array<string, bool> $decisions page id => readable with its ancestors */
        $decisions = [];
        $visible   = function (WikiPage $page) use (&$visible, &$decisions, $user, $space): bool {
            $id = $page->getId();
            if (!isset($decisions[$id])) {
                $decisions[$id] = false; // a parent cycle resolves to hidden
                $parent         = $page->getParent();
                $decisions[$id] = $this->spaceGuard->isGranted($user, WikiAction::View, $space, $page)
                    && (!$parent instanceof WikiPage || $visible($parent));
            }

            return $decisions[$id];
        };

        return array_values(array_filter($pages, $visible));
    }

    /**
     * Spaces of the user that the space checker lets them view ({@see WikiAction::View} per space), and optionally
     * also the given action (e.g. {@see WikiAction::AskAi} for a question across spaces).
     *
     * @return list<WikiSpace>
     */
    private function readableSpaces(UserInterface $user, ?WikiAction $action = null): array
    {
        return array_values(array_filter(
            $this->spaceAccessResolver->listSpacesForUser($user),
            fn (WikiSpace $space): bool => $this->spaceGuard->isGranted($user, WikiAction::View, $space)
                && (!$action instanceof WikiAction || $this->spaceGuard->isGranted($user, $action, $space)),
        ));
    }

    /**
     * Search hits / AI sources the user may read ({@see WikiAction::View} on the hit's own space and page).
     *
     * @param list<array{page: WikiPage, space: WikiSpace, excerpt: string}> $hits
     *
     * @return list<array{page: WikiPage, space: WikiSpace, excerpt: string}>
     */
    private function readableHits(UserInterface $user, array $hits): array
    {
        return array_values(array_filter($hits, fn (array $hit): bool => $this->spaceGuard->isGranted($user, WikiAction::View, $hit['space'], $hit['page'])));
    }

    private function treeSearchHref(WikiSpace $space): ?string
    {
        return isset($this->routes['search']) ? $this->generateUrl($this->routes['search']['name'], ['space' => $space->getSlug()]) : null;
    }

    /**
     * View models of the pages: the injected factory, else one built from the router and the configured templates.
     */
    private function pages(): WikiPageFactory
    {
        return $this->pageFactory ?? new WikiPageFactory(
            $this->container->get('router'),
            $this->routes,
            new WikiPages($this->templates['layout'] ?? null, $this->templates),
            $this->dashboardRoute,
        );
    }

    /**
     * Renders a page template (`web_ui.templates.<page>` override, else the bundle one) with the shared variables.
     *
     * A submitted, invalid form answers 422 (as AbstractController::render() does for a FormInterface variable).
     *
     * @param array<string, mixed> $vars
     * @param FormInterface<mixed>|null $form form of the page
     */
    private function renderPage(string $page, array $vars, ?FormInterface $form = null): Response
    {
        $pages   = $this->pages();
        $invalid = $form instanceof FormInterface && $form->isSubmitted() && !$form->isValid();

        return new Response(
            $this->renderView($pages->template($page), $pages->context($vars)),
            $invalid ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
        );
    }

    /**
     * Returns the active page of the slug the user may read; a missing, archived or hidden page answers 404 alike.
     *
     * @throws NotFoundHttpException when the page is missing, archived or {@see WikiAction::View} is denied on it
     */
    private function requirePage(UserInterface $user, WikiSpace $space, string $pageSlug): WikiPage
    {
        $page = $this->pageRepository->findBySlug($space, $pageSlug);
        if (!$page instanceof WikiPage || $page->isArchived()) {
            throw new NotFoundHttpException('Page not found.');
        }

        $this->spaceGuard->requireGranted($user, WikiAction::View, $space, $page);

        return $page;
    }
}
