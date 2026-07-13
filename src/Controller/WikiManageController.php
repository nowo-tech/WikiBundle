<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Controller;

use InvalidArgumentException;
use Nowo\WikiBundle\Ai\WikiAiAssistantInterface;
use Nowo\WikiBundle\Dto\WikiPageFormData;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiInterchangeFormat;
use Nowo\WikiBundle\Form\WikiPageFormType;
use Nowo\WikiBundle\Interchange\WikiArchiveHelper;
use Nowo\WikiBundle\Interchange\WikiDocumentExporter;
use Nowo\WikiBundle\Interchange\WikiDocumentImporter;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiPageTreeBuilder;
use Nowo\WikiBundle\Service\WikiRevisionDiffService;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

use function sprintf;

/**
 * Authenticated wiki management (spaces, pages, revisions, search).
 */
#[IsGranted('IS_AUTHENTICATED')]
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

        $form = $this->createForm(WikiPageFormType::class, $data, [
            'tiptap_config' => $this->editor['tiptap_config'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->isValidCsrf($request, 'wiki_edit_' . $page->getId())) {
                throw new AccessDeniedHttpException('Invalid CSRF token.');
            }

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

        $form = $this->createForm(WikiPageFormType::class, $data, [
            'tiptap_config' => $this->editor['tiptap_config'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->isValidCsrf($request, 'wiki_new_' . $space->getId())) {
                throw new AccessDeniedHttpException('Invalid CSRF token.');
            }

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

        if (!$from instanceof \Nowo\WikiBundle\Entity\WikiPageRevision || !$to instanceof \Nowo\WikiBundle\Entity\WikiPageRevision) {
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

    public function archivePage(Request $request, string $spaceSlug, string $pageSlug): \Symfony\Component\HttpFoundation\RedirectResponse
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
        if (!mkdir($workingDir, 0777, true) && !is_dir($workingDir)) {
            throw new RuntimeException('Unable to create export directory.');
        }
        // @codeCoverageIgnoreEnd

        $zipPath = sys_get_temp_dir() . '/wiki-export-' . $space->getSlug() . '-' . bin2hex(random_bytes(4)) . '.zip';
        try {
            $this->documentExporter->export($space, $workingDir, $format);
            $this->archiveHelper->createZipFromDirectory($workingDir, $zipPath);
        } finally {
            $this->archiveHelper->removeDirectory($workingDir);
        }

        $response = new BinaryFileResponse($zipPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $space->getSlug() . '-' . $format->value . '-export.zip',
        );
        $response->deleteFileAfterSend(true);

        return $response;
    }

    public function importSpace(Request $request, string $spaceSlug): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $this->denyUnlessFeature('import');
        if (!$this->importExport['enabled']) {
            throw new NotFoundHttpException('Import/export is disabled.');
        }

        $user  = $this->requireUser();
        $space = $this->requireAccessibleSpace($user, $spaceSlug);

        if (!$this->isValidCsrf($request, 'wiki_import_' . $space->getId())) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        /** @var UploadedFile|null $upload */
        $upload = $request->files->get('archive');
        if (!$upload instanceof UploadedFile) {
            $this->addFlash('error', 'wiki.import.missing_file');

            return $this->redirectToRoute($this->routes['space']['name'], ['spaceSlug' => $space->getSlug()]);
        }

        if ($upload->getSize() > $this->importExport['max_upload_bytes']) {
            $this->addFlash('error', 'wiki.import.file_too_large');

            return $this->redirectToRoute($this->routes['space']['name'], ['spaceSlug' => $space->getSlug()]);
        }

        $format = WikiInterchangeFormat::tryFrom($request->request->getString('format', WikiInterchangeFormat::Auto->value))
            ?? WikiInterchangeFormat::Auto;

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
                $request->request->getBoolean('update_existing'),
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
            if (!$this->isValidCsrf($request, 'wiki_ai_ask')) {
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
            'csrf_token_id'   => 'wiki_ai_ask',
        ]);
    }

    private function denyUnlessFeature(string $feature): void
    {
        $allowed = match ($feature) {
            'list'    => $this->accessChecker->canList(),
            'create'  => $this->accessChecker->canCreate(),
            'edit'    => $this->accessChecker->canEdit(),
            'history' => $this->accessChecker->canViewHistory(),
            'archive' => $this->accessChecker->canArchive(),
            'ai'      => $this->accessChecker->canAskAi(),
            'import'  => $this->accessChecker->canImport(),
            'export'  => $this->accessChecker->canExport(),
            default   => $this->accessChecker->canAccess(),
        };

        if (!$allowed) {
            throw new AccessDeniedHttpException('Access denied.');
        }
    }

    private function requireUser(): UserInterface
    {
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        return $user;
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
