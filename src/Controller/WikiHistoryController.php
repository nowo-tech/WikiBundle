<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Controller;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiRevisionLabel;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Event\WikiQuotaCheckEvent;
use Nowo\WikiBundle\Form\WikiVersionLabelType;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiRevisionLabelRepositoryInterface;
use Nowo\WikiBundle\Routing\WikiRouteLoader;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiQuotaGate;
use Nowo\WikiBundle\Ui\WikiPageFactory;
use Nowo\WikiBundle\Ui\WikiPages;
use Nowo\WikiBundle\Util\WikiSlugger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

use function mb_strlen;
use function mb_substr;
use function preg_replace;
use function rtrim;
use function sprintf;
use function trim;

/**
 * Named versions and Copy to new page (1.15, `history.timeline`).
 *
 * - **Save version** (GET form, POST + CSRF): labels the page's current revision. It asks the role gate canEdit()
 *   and {@see WikiAction::Edit} on the page (D-W7: every editor).
 * - **Copy to new page** (POST + CSRF from the history): a new top-level page with the content of that revision. It
 *   asks canCreate() and {@see WikiAction::Create} on the space, plus {@see WikiAction::History} on the page.
 *
 * A page the user may not read (with every ancestor) answers 404. Without the timeline every route answers 404.
 */
final class WikiHistoryController extends AbstractController
{
    use WikiCsrfTrait;

    /** Length of the page title column. */
    private const TITLE_MAX = 255;

    /** Length of the page slug column. */
    private const SLUG_MAX = 120;

    /** Bidirectional control characters, stripped from version names (audit DT-I4). */
    private const BIDI_CONTROLS = '/[\x{061C}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

    /**
     * @param array<string, array{path: string, name: string}> $routes the nowo_wiki routes
     */
    public function __construct(
        private readonly WikiSpaceGuard $spaceGuard,
        private readonly WikiPageRepositoryInterface $pageRepository,
        private readonly WikiPageRevisionRepositoryInterface $revisionRepository,
        private readonly WikiPageService $pageService,
        private readonly WikiAccessCheckerInterface $accessChecker,
        private readonly WikiPageFactory $pageFactory,
        private readonly WikiPages $pages,
        private readonly WikiSlugger $slugger,
        private readonly array $routes = [],
        private readonly ?WikiRevisionLabelRepositoryInterface $labels = null,
        private readonly ?WikiTokenGuard $tokenGuard = null,
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?WikiQuotaGate $quota = null,
    ) {
    }

    /** CSRF token id of the Copy to new page form of a revision. */
    public static function copyTokenId(WikiPageRevision $revision): string
    {
        return 'wiki_version_copy_' . $revision->getId();
    }

    /**
     * @throws NotFoundHttpException without the timeline, or for a page that is missing, archived or not readable
     * @throws AccessDeniedHttpException without the edit rights
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/versions/new', name: 'nowo_wiki_page_version_save', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG, 'pageSlug' => WikiRouteLoader::PAGE_SLUG], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'page_version_save'], methods: ['GET', 'POST'])]
    public function saveVersion(Request $request, string $spaceSlug, string $pageSlug): Response
    {
        $labels         = $this->requireTimeline();
        $user           = $this->requireUser();
        [$space, $page] = $this->requirePage($user, $spaceSlug, $pageSlug);
        $this->spaceGuard->requireGranted($user, WikiAction::Edit, $space, $page);
        if (!$this->accessChecker->canEdit($user)) {
            throw new AccessDeniedHttpException('Access denied.');
        }
        $revision = $page->getCurrentRevision();
        if (!$revision instanceof WikiPageRevision) {
            throw new NotFoundHttpException('Page not found.');
        }

        $existing = $labels->findForRevision($revision);
        $form     = $this->container->get('form.factory')->createNamed('', WikiVersionLabelType::class, ['label' => $existing?->getLabel()], [
            'csrf_token_id' => WikiVersionLabelType::csrfTokenId($page->getId()),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{label: string} $data */
            $data = $form->getData();
            // The form's trim already drops leading / trailing format characters, so a name of only bidirectional
            // controls fails NotBlank; the ones inside a name are stripped here (audit DT-I4).
            $label = trim((string) preg_replace(self::BIDI_CONTROLS, '', $data['label']));
            if ($existing instanceof WikiRevisionLabel) {
                $existing->setLabel($label);
                $labels->save($existing);
            } else {
                $labels->save(new WikiRevisionLabel($revision, $label));
            }
            $this->flash(new TranslatableMessage('wiki.history.version_saved', ['%label%' => $label, '%number%' => $revision->getRevisionNumber()], WikiPages::DOMAIN));

            return $this->redirectToRoute($this->routes['page_history']['name'] ?? $this->routes['page_view']['name'], ['spaceSlug' => $space->getSlug(), 'pageSlug' => $page->getSlug()]);
        }

        $invalid = $form->isSubmitted();

        return new Response(
            $this->renderView($this->pages->template('page_version'), $this->pages->context(['page' => $this->pageFactory->pageVersion($space, $page, $form->createView())])),
            $invalid ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
        );
    }

    /**
     * @throws NotFoundHttpException without the timeline, or for a page or revision that is missing or not readable
     * @throws AccessDeniedHttpException without the rights or a valid token
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/revisions/{revisionNumber}/copy', name: 'nowo_wiki_page_version_copy', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG, 'pageSlug' => WikiRouteLoader::PAGE_SLUG, 'revisionNumber' => '\d+'], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'page_version_copy'], methods: ['POST'])]
    public function copyVersion(Request $request, string $spaceSlug, string $pageSlug, int $revisionNumber): RedirectResponse
    {
        $this->requireTimeline();
        $user           = $this->requireUser();
        [$space, $page] = $this->requirePage($user, $spaceSlug, $pageSlug);
        $this->spaceGuard->requireGranted($user, WikiAction::History, $space, $page);
        $this->spaceGuard->requireGranted($user, WikiAction::Create, $space);
        if (!$this->accessChecker->canCreate($user)) {
            throw new AccessDeniedHttpException('Access denied.');
        }
        $revision = $this->revisionRepository->findByPageAndNumber($page, $revisionNumber);
        if (!$revision instanceof WikiPageRevision) {
            throw new NotFoundHttpException('Revision not found.');
        }
        if (!$this->isValidCsrf($request, self::copyTokenId($revision))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
        // 1.21: a copy is a new page, so the host may refuse it (plan limits).
        $refusal = $this->quota?->refusal(WikiQuotaCheckEvent::PAGE_CREATE, $user, $space, 1, 0, $page);
        if ($refusal !== null) {
            $this->addFlash('error', $refusal);

            return $this->redirectToRoute($this->routes['page_history']['name'] ?? $this->routes['page_view']['name'], ['spaceSlug' => $space->getSlug(), 'pageSlug' => $page->getSlug()]);
        }

        // The copy keeps the "(vN)" suffix within the title column (audit DT-I2).
        $suffix = sprintf(' (v%d)', $revisionNumber);
        $title  = rtrim(mb_substr($page->getTitle(), 0, self::TITLE_MAX - mb_strlen($suffix))) . $suffix;
        $copy   = $this->pageService->create(
            $space,
            $title,
            $revision->isMarkdown() ? (string) $revision->getContentMarkdown() : $revision->getContentHtml(),
            $user,
            null,
            $this->freeSlug($space, $page->getSlug(), '-v' . $revisionNumber),
            $revision->getFormat(),
        );
        $this->flash(new TranslatableMessage('wiki.history.copied', ['%title%' => $title], WikiPages::DOMAIN));

        return $this->redirectToRoute($this->routes['page_view']['name'], ['spaceSlug' => $space->getSlug(), 'pageSlug' => $copy->getSlug()]);
    }

    /**
     * The first free slug "<stem><tail>", "<stem><tail>-2", …, the stem cut so the whole fits the slug column (DT-I2).
     */
    private function freeSlug(WikiSpace $space, string $stem, string $tail): string
    {
        $stem = $this->slugger->slugify($stem);
        for ($i = 1;; ++$i) {
            $end  = $tail . ($i > 1 ? '-' . $i : '');
            $slug = rtrim(mb_substr($stem, 0, self::SLUG_MAX - mb_strlen($end)), '-') . $end;
            if ($this->pageRepository->countBySpaceAndSlug($space, $slug) === 0) {
                return $slug;
            }
        }
    }

    private function requireTimeline(): WikiRevisionLabelRepositoryInterface
    {
        if (!$this->labels instanceof WikiRevisionLabelRepositoryInterface) {
            throw new NotFoundHttpException('Not found.');
        }

        return $this->labels;
    }

    /**
     * @return array{0: WikiSpace, 1: WikiPage}
     */
    private function requirePage(UserInterface $user, string $spaceSlug, string $pageSlug): array
    {
        $space = $this->spaceGuard->requireSpace($user, $spaceSlug);
        $page  = $this->pageRepository->findBySlug($space, $pageSlug);
        if (!$page instanceof WikiPage || $page->isArchived()) {
            throw new NotFoundHttpException('Page not found.');
        }
        $this->spaceGuard->requireReadablePage($user, $space, $page);

        return [$space, $page];
    }

    private function requireUser(): UserInterface
    {
        $user = $this->tokenGuard instanceof WikiTokenGuard && !$this->tokenGuard->isTokenTrusted() ? null : $this->getUser();
        if (!$user instanceof UserInterface) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        return $user;
    }

    private function flash(TranslatableMessage $message): void
    {
        $this->addFlash('success', $this->translator instanceof TranslatorInterface ? $message->trans($this->translator) : $message);
    }
}
