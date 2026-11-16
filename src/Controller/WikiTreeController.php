<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Controller;

use InvalidArgumentException;
use JsonException;
use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Routing\WikiRouteLoader;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Service\WikiPageIconService;
use Nowo\WikiBundle\Service\WikiPageMoveException;
use Nowo\WikiBundle\Service\WikiPageMover;
use Nowo\WikiBundle\Service\WikiPageMoveScope;
use Nowo\WikiBundle\Service\WikiPageServiceInterface;
use Nowo\WikiBundle\Service\WikiRevisionCollisionException;
use Nowo\WikiBundle\Service\WikiRevisionConflictException;
use Nowo\WikiBundle\Tree\WikiTreeStateStoreInterface;
use Nowo\WikiBundle\Ui\WikiPages;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function json_decode;
use function mb_strlen;
use function preg_match;
use function str_starts_with;
use function strlen;
use function trim;

use const JSON_THROW_ON_ERROR;

/**
 * Page tree endpoints of a space (1.14, `tree.enabled`): `POST move` {page, parent (null: top level), index, _token}
 * moves a page ({@see WikiPageMover}) and answers {index, count, moved, message} (the sentence the tree announces);
 * `POST state` {page, expanded, _token} records a fold change for the next render.
 *
 * Both ask a firewall-trusted user, the same origin (`Origin`, or `Sec-Fetch-Site`), a JSON body of at most
 * {@see self::MAX_BODY} bytes and the CSRF token {@see self::csrfTokenId()} of the space. A move also asks the role
 * gate canEdit() and the rights of {@see WikiTreeMoveTrait}; a fold change {@see WikiAction::View} on the page. Pages
 * the user may not read with every ancestor answer 404 (DI-L1); the index and the answer count the siblings the user
 * sees (DI-I1). Without the tree every endpoint answers 404.
 */
final class WikiTreeController extends AbstractController
{
    use WikiTreeMoveTrait;

    /** 1.39: longest title of a rename (the column's length). */
    public const MAX_TITLE = 255;

    /** Largest accepted body. */
    public const MAX_BODY = 4096;

    /** Headers of every answer: never cached. */
    private const NO_STORE = ['Cache-Control' => 'no-store, private'];

    public function __construct(
        private readonly WikiSpaceGuard $spaceGuard,
        private readonly WikiPageRepositoryInterface $pageRepository,
        private readonly WikiAccessCheckerInterface $accessChecker,
        private readonly ?WikiPageMover $mover = null,
        private readonly ?WikiTreeStateStoreInterface $stateStore = null,
        private readonly ?WikiTokenGuard $tokenGuard = null,
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?WikiPageServiceInterface $pageService = null,
        private readonly ?WikiPageIconService $pageIcons = null,
        private readonly ?WikiCollabService $collab = null,
        private readonly bool $pageIconField = false,
        private readonly bool $contextMenu = false,
    ) {
    }

    /**
     * CSRF token id of the tree endpoints of a space.
     */
    public static function csrfTokenId(WikiSpace $space): string
    {
        return 'wiki_tree_' . $space->getId();
    }

    /**
     * @throws NotFoundHttpException without the tree, or for a page that is missing, archived, in another space or not readable
     * @throws AccessDeniedHttpException without a trusted user, a valid token, the same origin or the rights
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/tree/{action}', name: 'nowo_wiki_space_tree', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG, 'action' => 'move|state|rename|icon'], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'space_tree'], methods: ['POST'])]
    public function tree(Request $request, string $spaceSlug, string $action): Response
    {
        if (!$this->mover instanceof WikiPageMover || !$this->stateStore instanceof WikiTreeStateStoreInterface) {
            throw new NotFoundHttpException('Not found.');
        }

        $user = $this->requireUser();
        $this->requireSameOrigin($request);
        $space = $this->spaceGuard->requireSpace($user, $spaceSlug);
        $body  = $this->body($request);
        if (!$this->isCsrfTokenValid(self::csrfTokenId($space), is_string($body['_token'] ?? null) ? $body['_token'] : '')) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $page = $this->requireReadablePage($user, $space, $body['page'] ?? null);
        if ($action === 'rename' || $action === 'icon') {
            return $this->menuWrite($request, $user, $space, $page, $action, $body);
        }
        if ($action === 'state') {
            if (!is_bool($body['expanded'] ?? null)) {
                throw new BadRequestHttpException('Invalid body.');
            }
            // @igor-ignore - The store writes the fold state to the request's session (or the host's storage), not to itself.
            $this->stateStore->setExpanded($user, $space, $page->getId(), $body['expanded']);

            return new Response(null, Response::HTTP_NO_CONTENT, self::NO_STORE);
        }

        if (!$this->accessChecker->canEdit($user)) {
            throw new AccessDeniedHttpException('Access denied.');
        }
        $parentId = $body['parent'] ?? null;
        $index    = $body['index'] ?? null;
        if (($parentId !== null && !is_string($parentId)) || !is_int($index)) {
            throw new BadRequestHttpException('Invalid body.');
        }
        $parent = $parentId === null ? null : $this->requireReadablePage($user, $space, $parentId);
        // DI-I6: the parent is read once; the check and the scope's stale guard use the same one.
        $from = $page->getParent();
        $this->requireMovable($user, $space, $page, $from, $parent);

        $scope = new WikiPageMoveScope($this->readableIds($user, $space, $this->pageRepository->findActiveBySpace($space)), $from?->getId());
        try {
            $result = $this->mover->move($page, $parent, $index, $user, $scope);
        } catch (WikiPageMoveException $exception) {
            return new JsonResponse(['error' => $exception->reason, 'message' => $this->trans('wiki.tree.error.' . $exception->reason)], Response::HTTP_UNPROCESSABLE_ENTITY, self::NO_STORE);
        }

        $params  = ['%title%' => $page->getTitle(), '%position%' => $result->index + 1, '%count%' => $result->count];
        $message = $parent instanceof WikiPage
            ? $this->trans('wiki.tree.moved_under', $params + ['%parent%' => $parent->getTitle()])
            : $this->trans('wiki.tree.moved_root', $params);

        return new JsonResponse(['index' => $result->index, 'count' => $result->count, 'moved' => $result->moved, 'message' => $message], Response::HTTP_OK, self::NO_STORE);
    }

    /**
     * 1.39 (`tree.context_menu`): Rename (`{page, title, _token}`: a new revision with the current body, as the edit
     * form's save; the revision the server read is the expected one, so a save in between answers 409, and with
     * co-editing a log holding unsaved text answers 409 as the edit form would ask) and Change icon (`{page, icon,
     * _token}`, {@see WikiPageIconService}: no revision; `page_icon.form_field` only). Both need the edit role and
     * {@see WikiAction::Edit} on the page; a transient storage failure answers 503 + Retry-After (the bundle's
     * subscriber). Answers {title} / {icon, icon_html} and the sentence to announce.
     *
     * @param array<string, mixed> $body
     */
    private function menuWrite(Request $request, UserInterface $user, WikiSpace $space, WikiPage $page, string $action, array $body): Response
    {
        if (!$this->contextMenu || !$this->accessChecker->canEdit($user) || !$this->spaceGuard->isGranted($user, WikiAction::Edit, $space, $page)) {
            throw new AccessDeniedHttpException('Access denied.');
        }

        if ($action === 'icon') {
            if (!$this->pageIconField || !$this->pageIcons instanceof WikiPageIconService) {
                throw new NotFoundHttpException('Not found.');
            }
            $icon = $body['icon'] ?? null;
            if ($icon !== null && !is_string($icon)) {
                throw new BadRequestHttpException('Invalid body.');
            }
            if (!$this->pageIcons->isAllowed($icon)) {
                return $this->refusal(Response::HTTP_UNPROCESSABLE_ENTITY, 'icon_invalid', 'wiki.space.icon_invalid');
            }
            try {
                // @igor-ignore - WikiPageIconService is stateless: setIcon() writes the page row, the service keeps nothing.
                $this->pageIcons->setIcon($page, $icon);
            } catch (InvalidArgumentException) {
                // The value was checked above: the page was archived or deleted between the read and the lock (RS-I1).
                return $this->pageGone();
            }
            $stored = $page->getIcon();

            return new JsonResponse([
                'icon'      => $stored,
                'icon_html' => trim($this->renderView('@NowoWikiBundle/manage/_page_icon.html.twig', ['icon' => $stored])),
                'message'   => $this->trans('wiki.tree_menu.icon_saved', ['%title%' => $page->getTitle()]),
            ], Response::HTTP_OK, self::NO_STORE);
        }

        if (!$this->pageService instanceof WikiPageServiceInterface) {
            throw new NotFoundHttpException('Not found.');
        }
        $title = is_string($body['title'] ?? null) ? trim($body['title']) : null;
        if ($title === null || $title === '' || mb_strlen($title) > self::MAX_TITLE || preg_match('/[\p{Cc}\p{Cf}\x{2028}\x{2029}]/u', $title) !== 0) {
            return $this->refusal(Response::HTTP_UNPROCESSABLE_ENTITY, 'title_invalid', 'wiki.tree_menu.error.title_invalid');
        }
        $revision = $page->getCurrentRevision();
        if (!$revision instanceof WikiPageRevision) {
            throw new NotFoundHttpException('Page not found.');
        }
        if ($title === $page->getTitle()) {
            return new JsonResponse(['title' => $title, 'message' => ''], Response::HTTP_OK, self::NO_STORE);
        }
        $from    = $page->getTitle();
        $content = $revision->isMarkdown() ? (string) $revision->getContentMarkdown() : $revision->getContentHtml();
        $format  = $revision->getFormat();
        $number  = $revision->getRevisionNumber();
        $save    = function () use ($page, $title, $content, $user, $format, $number): void {
            // The revision the server read is the expected one: a save in between is a conflict, nothing is written.
            $this->pageService?->saveRevision($page, $title, $content, $user, $format, $number);
        };
        try {
            if ($this->collab instanceof WikiCollabService) {
                // Co-editing: a log holding text not saved yet would be dropped by this save; the edit form asks, the
                // tree refuses (409) and the person renames from the edit view.
                if (!$this->collab->saveOutsideSession($request, $page, $save)) {
                    return $this->refusal(Response::HTTP_CONFLICT, 'being_edited', 'wiki.tree_menu.error.being_edited');
                }
            } else {
                $save();
            }
        } catch (WikiRevisionConflictException|WikiRevisionCollisionException) {
            return $this->refusal(Response::HTTP_CONFLICT, 'conflict', 'wiki.tree_menu.error.conflict');
        } catch (InvalidArgumentException) {
            // The title was checked above: the page was archived between the read and the lock (RS-I1).
            return $this->pageGone();
        }

        return new JsonResponse([
            'title'   => $page->getTitle(),
            'message' => $this->trans('wiki.tree_menu.renamed', ['%from%' => $from, '%title%' => $page->getTitle()]),
        ], Response::HTTP_OK, self::NO_STORE);
    }

    /** 410: the page left between the read and the write (archived, trashed or deleted meanwhile). */
    private function pageGone(): JsonResponse
    {
        return $this->refusal(Response::HTTP_GONE, 'page_gone', 'wiki.tree_menu.error.page_gone');
    }

    private function refusal(int $status, string $error, string $key): JsonResponse
    {
        return new JsonResponse(['error' => $error, 'message' => $this->trans($key)], $status, self::NO_STORE);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Request $request): array
    {
        if (!str_starts_with((string) $request->headers->get('Content-Type'), 'application/json')) {
            throw new UnsupportedMediaTypeHttpException('JSON expected.');
        }
        $content = $request->getContent();
        if (strlen($content) > self::MAX_BODY) {
            throw new BadRequestHttpException('Body too large.');
        }
        try {
            $body = json_decode($content, true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BadRequestHttpException('Invalid JSON.');
        }
        if (!is_array($body)) {
            throw new BadRequestHttpException('Invalid body.');
        }

        /* @var array<string, mixed> $body */
        return $body;
    }

    private function requireReadablePage(UserInterface $user, WikiSpace $space, mixed $id): WikiPage
    {
        $page = is_string($id) && preg_match('/^[A-Za-z0-9-]{1,36}$/', $id) === 1 ? $this->pageRepository->findById($id) : null;
        // DI-L1: the page and every ancestor must be readable (a hidden branch is hidden whole, as in the sidebar).
        if (!$page instanceof WikiPage || $page->isArchived() || $page->getSpace()->getId() !== $space->getId()
            || !$this->isReadableBranch($user, $space, $page)) {
            throw new NotFoundHttpException('Page not found.');
        }

        return $page;
    }

    private function requireUser(): UserInterface
    {
        $user = $this->tokenGuard instanceof WikiTokenGuard && !$this->tokenGuard->isTokenTrusted() ? null : $this->getUser();
        if (!$user instanceof UserInterface) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        return $user;
    }

    private function requireSameOrigin(Request $request): void
    {
        $origin = $request->headers->get('Origin');
        if ($origin !== null ? $origin !== $request->getSchemeAndHttpHost() : $request->headers->get('Sec-Fetch-Site', 'same-origin') !== 'same-origin') {
            throw new AccessDeniedHttpException('Cross-origin request refused.');
        }
    }

    /**
     * @param array<string, int|string> $params
     */
    private function trans(string $key, array $params = []): string
    {
        return $this->translator?->trans($key, $params, WikiPages::DOMAIN) ?? $key;
    }
}
