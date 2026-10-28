<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Controller;

use InvalidArgumentException;
use Nowo\WikiBundle\Ai\WikiAiAssistantInterface;
use Nowo\WikiBundle\Dto\WikiPageFormData;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiInterchangeFormat;
use Nowo\WikiBundle\Form\WikiAiAskType;
use Nowo\WikiBundle\Form\WikiImportType;
use Nowo\WikiBundle\Form\WikiPageFormType;
use Nowo\WikiBundle\Form\WikiSearchType;
use Nowo\WikiBundle\Interchange\WikiArchiveHelper;
use Nowo\WikiBundle\Interchange\WikiDocumentExporter;
use Nowo\WikiBundle\Interchange\WikiDocumentImporter;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiPageTreeBuilder;
use Nowo\WikiBundle\Service\WikiRevisionDiffService;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
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
use Symfony\Component\Security\Core\User\UserInterface;
use Throwable;

use function is_string;
use function sprintf;

/**
 * Authenticated wiki management (spaces, pages, revisions, search).
 *
 * Soft-gated via {@see WikiAccessCheckerInterface}; set security.allow_unauthenticated
 * for trusted demos only (never in production).
 */
final class WikiManageController extends AbstractController
{
    use WikiCsrfTrait;

    /**
     * @param array<string, array{path: string, name: string}> $routes
     * @param array<string, string> $templates
     * @param array{tiptap_config: string} $editor
     * @param array{enabled: bool, max_upload_bytes: int} $importExport
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
    ) {
    }

    public function index(): Response
    {
        $this->denyUnlessFeature('list');
        $user   = $this->requireUser();
        $spaces = $this->spaceAccessResolver->listSpacesForUser($user);

        return $this->render($this->templates['index'], [
            'layout'          => $this->templates['layout'],
            'spaces'          => $spaces,
            'routes'          => $this->routes,
            'dashboard_route' => $this->dashboardRoute,
            'can_create'      => $this->accessChecker->canCreate($user),
            'can_ask_ai'      => $this->accessChecker->canAskAi($user) && $this->aiAssistant->isAvailable(),
            'search_form'     => $this->createSearchForm($spaces, [], ['space' => 'wiki-index-search-space', 'q' => 'wiki-index-search-q']),
            'ai_form'         => $this->createAiAskForm($spaces, [], ['space' => 'wiki-ai-modal-space', 'q' => 'wiki-ai-modal-question'], ['autofocus' => true])->createView(),
        ]);
    }

    public function space(string $spaceSlug): Response
    {
        $this->denyUnlessFeature('list');
        $user  = $this->requireUser();
        $space = $this->requireAccessibleSpace($user, $spaceSlug);
        $pages = $this->pageRepository->findActiveBySpace($space);
        $tree  = $this->pageTreeBuilder->build($pages);

        return $this->render($this->templates['space'], [
            'layout'          => $this->templates['layout'],
            'space'           => $space,
            'tree'            => $tree,
            'routes'          => $this->routes,
            'dashboard_route' => $this->dashboardRoute,
            'can_create'      => $this->accessChecker->canCreate($user),
            'can_import'      => $this->importExport['enabled'] && $this->accessChecker->canImport($user),
            'can_export'      => $this->importExport['enabled'] && $this->accessChecker->canExport($user),
            'search_form'     => $this->createSearchForm(null, ['space' => $space->getSlug()], ['q' => 'wiki-space-search'], false),
            'import_form'     => $this->createImportForm($space),
        ]);
    }

    public function viewPage(string $spaceSlug, string $pageSlug): Response
    {
        $this->denyUnlessFeature('list');
        $user     = $this->requireUser();
        $space    = $this->requireAccessibleSpace($user, $spaceSlug);
        $page     = $this->requirePage($space, $pageSlug);
        $revision = $page->getCurrentRevision();
        $tree     = $this->pageTreeBuilder->build($this->pageRepository->findActiveBySpace($space));

        return $this->render($this->templates['page_view'], [
            'layout'          => $this->templates['layout'],
            'space'           => $space,
            'page'            => $page,
            'revision'        => $revision,
            'tree'            => $tree,
            'routes'          => $this->routes,
            'dashboard_route' => $this->dashboardRoute,
            'can_edit'        => $this->accessChecker->canEdit($user),
            'can_history'     => $this->accessChecker->canViewHistory($user),
        ]);
    }

    public function editPage(Request $request, string $spaceSlug, string $pageSlug): Response
    {
        $this->denyUnlessFeature('edit');
        $user  = $this->requireUser();
        $space = $this->requireAccessibleSpace($user, $spaceSlug);
        $page  = $this->requirePage($space, $pageSlug);

        $data = new WikiPageFormData(
            $page->getTitle(),
            $page->getCurrentRevision()?->getContentHtml() ?? '',
        );

        // REQ-TWIG-005: the Form's own CSRF field (token id wiki_edit_<page id>) replaces the hand-rolled top-level _token.
        $form = $this->createForm(WikiPageFormType::class, $data, [
            'tiptap_config' => $this->editor['tiptap_config'],
            'csrf_token_id' => 'wiki_edit_' . $page->getId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var WikiPageFormData $payload */
            $payload = $form->getData();
            $this->pageService->saveRevision($page, $payload->title, $payload->content, $user);

            return $this->redirectToRoute($this->routes['page_view']['name'], [
                'spaceSlug' => $space->getSlug(),
                'pageSlug'  => $page->getSlug(),
            ]);
        }

        return $this->render($this->templates['page_edit'], [
            'layout'          => $this->templates['layout'],
            'space'           => $space,
            'page'            => $page,
            'form'            => $form,
            'routes'          => $this->routes,
            'dashboard_route' => $this->dashboardRoute,
            'csrf_token_id'   => 'wiki_edit_' . $page->getId(),
        ]);
    }

    public function newPage(Request $request, string $spaceSlug): Response
    {
        $this->denyUnlessFeature('create');
        $user  = $this->requireUser();
        $space = $this->requireAccessibleSpace($user, $spaceSlug);
        $data  = new WikiPageFormData();

        // REQ-TWIG-005: the Form's own CSRF field (token id wiki_new_<space id>) replaces the hand-rolled top-level _token.
        $form = $this->createForm(WikiPageFormType::class, $data, [
            'tiptap_config' => $this->editor['tiptap_config'],
            'csrf_token_id' => 'wiki_new_' . $space->getId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var WikiPageFormData $payload */
            $payload = $form->getData();

            try {
                $page = $this->pageService->create($space, $payload->title, $payload->content, $user);
            } catch (InvalidArgumentException $exception) {
                $this->addFlash('error', $exception->getMessage());

                return $this->render($this->templates['page_edit'], [
                    'layout'          => $this->templates['layout'],
                    'space'           => $space,
                    'page'            => null,
                    'form'            => $form,
                    'routes'          => $this->routes,
                    'dashboard_route' => $this->dashboardRoute,
                    'csrf_token_id'   => 'wiki_new_' . $space->getId(),
                ]);
            }

            return $this->redirectToRoute($this->routes['page_view']['name'], [
                'spaceSlug' => $space->getSlug(),
                'pageSlug'  => $page->getSlug(),
            ]);
        }

        return $this->render($this->templates['page_edit'], [
            'layout'          => $this->templates['layout'],
            'space'           => $space,
            'page'            => null,
            'form'            => $form,
            'routes'          => $this->routes,
            'dashboard_route' => $this->dashboardRoute,
            'csrf_token_id'   => 'wiki_new_' . $space->getId(),
        ]);
    }

    public function pageHistory(string $spaceSlug, string $pageSlug): Response
    {
        $this->denyUnlessFeature('history');
        $user      = $this->requireUser();
        $space     = $this->requireAccessibleSpace($user, $spaceSlug);
        $page      = $this->requirePage($space, $pageSlug);
        $revisions = $this->revisionRepository->findByPage($page);

        return $this->render($this->templates['page_history'], [
            'layout'          => $this->templates['layout'],
            'space'           => $space,
            'page'            => $page,
            'revisions'       => $revisions,
            'routes'          => $this->routes,
            'dashboard_route' => $this->dashboardRoute,
        ]);
    }

    public function pageDiff(Request $request, string $spaceSlug, string $pageSlug): Response
    {
        $this->denyUnlessFeature('history');
        $user  = $this->requireUser();
        $space = $this->requireAccessibleSpace($user, $spaceSlug);
        $page  = $this->requirePage($space, $pageSlug);

        $fromNumber = $request->query->getInt('from');
        $toNumber   = $request->query->getInt('to');
        $from       = $this->revisionRepository->findByPageAndNumber($page, $fromNumber);
        $to         = $this->revisionRepository->findByPageAndNumber($page, $toNumber);

        if (!$from instanceof WikiPageRevision || !$to instanceof WikiPageRevision) {
            throw new NotFoundHttpException('Revisions not found.');
        }

        $diff = $this->revisionDiffService->diff($from, $to);

        return $this->render($this->templates['page_diff'], [
            'layout'          => $this->templates['layout'],
            'space'           => $space,
            'page'            => $page,
            'from_revision'   => $from,
            'to_revision'     => $to,
            'diff'            => $diff,
            'routes'          => $this->routes,
            'dashboard_route' => $this->dashboardRoute,
        ]);
    }

    public function archivePage(Request $request, string $spaceSlug, string $pageSlug): RedirectResponse
    {
        $this->denyUnlessFeature('archive');
        $user  = $this->requireUser();
        $space = $this->requireAccessibleSpace($user, $spaceSlug);
        $page  = $this->requirePage($space, $pageSlug);

        if (!$this->isValidCsrf($request, 'wiki_archive_' . $page->getId())) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $this->pageService->archive($page);

        return $this->redirectToRoute($this->routes['space']['name'], ['spaceSlug' => $space->getSlug()]);
    }

    public function search(Request $request): Response
    {
        $this->denyUnlessFeature('list');
        $user      = $this->requireUser();
        $query     = $request->query->getString('q');
        $spaceSlug = $request->query->getString('space');
        $results   = [];
        $space     = null;
        $spaces    = $this->spaceAccessResolver->listSpacesForUser($user);
        $global    = false;

        if ($spaceSlug !== '') {
            $space   = $this->requireAccessibleSpace($user, $spaceSlug);
            $results = $query !== '' ? $this->searchService->search($space, $query) : [];
        } elseif ($query !== '') {
            $results = $this->searchService->searchAcrossSpaces($spaces, $query);
            $global  = true;
        }

        return $this->render($this->templates['search'], [
            'layout'          => $this->templates['layout'],
            'query'           => $query,
            'space'           => $space,
            'results'         => $results,
            'spaces'          => $spaces,
            'global_search'   => $global,
            'routes'          => $this->routes,
            'dashboard_route' => $this->dashboardRoute,
            'search_form'     => $this->createSearchForm(
                $spaces,
                ['space' => $space?->getSlug(), 'q' => $query],
                ['space' => 'wiki-search-space', 'q' => 'wiki-search-q'],
            ),
        ]);
    }

    public function exportSpace(Request $request, string $spaceSlug): BinaryFileResponse
    {
        $this->denyUnlessFeature('export');
        if (!$this->importExport['enabled']) {
            throw new NotFoundHttpException('Import/export is disabled.');
        }

        $user   = $this->requireUser();
        $space  = $this->requireAccessibleSpace($user, $spaceSlug);
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
            $this->documentExporter->export($space, $workingDir, $format);
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

    public function importSpace(Request $request, string $spaceSlug): RedirectResponse
    {
        $this->denyUnlessFeature('import');
        if (!$this->importExport['enabled']) {
            throw new NotFoundHttpException('Import/export is disabled.');
        }

        $user  = $this->requireUser();
        $space = $this->requireAccessibleSpace($user, $spaceSlug);

        $form = $this->createImportForm($space);
        if (!$this->isValidSubmittedForm($form, $request)) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $upload = $form->get('archive')->getData();
        if (!$upload instanceof UploadedFile) {
            $this->addFlash('error', 'wiki.import.missing_file');

            return $this->redirectToRoute($this->routes['space']['name'], ['spaceSlug' => $space->getSlug()]);
        }

        if ($upload->getSize() > $this->importExport['max_upload_bytes']) {
            $this->addFlash('error', 'wiki.import.file_too_large');

            return $this->redirectToRoute($this->routes['space']['name'], ['spaceSlug' => $space->getSlug()]);
        }

        $formatValue = $form->get('format')->getData();
        $format      = WikiInterchangeFormat::tryFrom(is_string($formatValue) ? $formatValue : '') ?? WikiInterchangeFormat::Auto;

        if (strtolower($upload->getClientOriginalExtension()) !== 'zip') {
            $this->addFlash('error', 'wiki.import.invalid_archive');

            return $this->redirectToRoute($this->routes['space']['name'], ['spaceSlug' => $space->getSlug()]);
        }

        $zipTempPath = sys_get_temp_dir() . '/wiki-upload-' . bin2hex(random_bytes(8)) . '.zip';
        if (!copy($upload->getPathname(), $zipTempPath)) {
            $this->addFlash('error', 'wiki.import.invalid_archive');

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
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute($this->routes['space']['name'], ['spaceSlug' => $space->getSlug()]);
        } finally {
            if (is_file($zipTempPath)) {
                unlink($zipTempPath);
            }
        }

        $this->addFlash('success', 'wiki.import.success');
        $this->addFlash('info', sprintf(
            '%d created, %d updated, %d skipped, %d failed.',
            $report->created,
            $report->updated,
            $report->skipped,
            $report->failed,
        ));

        foreach ($report->messages as $message) {
            $this->addFlash('warning', $message);
        }

        return $this->redirectToRoute($this->routes['space']['name'], ['spaceSlug' => $space->getSlug()]);
    }

    public function askAi(Request $request): Response
    {
        $this->denyUnlessFeature('ai');
        $user      = $this->requireUser();
        $spaces    = $this->spaceAccessResolver->listSpacesForUser($user);
        $question  = trim($request->request->getString('q', $request->query->getString('q')));
        $spaceSlug = $request->request->getString('space', $request->query->getString('space'));
        $space     = null;
        $answer    = null;
        $sources   = [];
        $error     = null;

        if ($spaceSlug !== '') {
            $space = $this->requireAccessibleSpace($user, $spaceSlug);
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

        return $this->render($this->templates['ai_ask'], [
            'layout'          => $this->templates['layout'],
            'question'        => $question,
            'space'           => $space,
            'spaces'          => $spaces,
            'answer'          => $answer,
            'sources'         => $sources,
            'error'           => $error,
            'routes'          => $this->routes,
            'dashboard_route' => $this->dashboardRoute,
            'csrf_token_id'   => WikiAiAskType::CSRF_TOKEN_ID,
            'ai_form'         => $this->createAiAskForm(
                $spaces,
                ['space' => $space?->getSlug(), 'q' => $question],
                ['space' => 'wiki-ai-space', 'q' => 'wiki-ai-question'],
            )->createView(),
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
        $spaces = $this->spaceAccessResolver->listSpacesForUser($user);
        foreach ($spaces as $space) {
            if ($space->getSlug() === $spaceSlug) {
                return $space;
            }
        }

        throw new NotFoundHttpException('Space not found.');
    }

    private function requirePage(WikiSpace $space, string $pageSlug): WikiPage
    {
        $page = $this->pageRepository->findBySlug($space, $pageSlug);
        if (!$page instanceof WikiPage || $page->isArchived()) {
            throw new NotFoundHttpException('Page not found.');
        }

        return $page;
    }
}
