<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Controller;

use JsonException;
use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Event\WikiQuotaCheckEvent;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Routing\WikiRouteLoader;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Service\WikiPageArchivedException;
use Nowo\WikiBundle\Service\WikiPageDuplicator;
use Nowo\WikiBundle\Service\WikiPageIconService;
use Nowo\WikiBundle\Service\WikiPageMoveException;
use Nowo\WikiBundle\Service\WikiPageMover;
use Nowo\WikiBundle\Service\WikiPageMoveScope;
use Nowo\WikiBundle\Service\WikiPageNotFoundException;
use Nowo\WikiBundle\Service\WikiPageServiceInterface;
use Nowo\WikiBundle\Service\WikiPageTreeBuilder;
use Nowo\WikiBundle\Service\WikiQuotaGate;
use Nowo\WikiBundle\Service\WikiRevisionCollisionException;
use Nowo\WikiBundle\Service\WikiRevisionConflictException;
use Nowo\WikiBundle\Service\WikiSlugTakenException;
use Nowo\WikiBundle\Trash\WikiTrashService;
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

use function array_shift;
use function count;
use function in_array;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function json_decode;
use function max;
use function mb_strlen;
use function mb_substr;
use function preg_match;
use function rtrim;
use function str_repeat;
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
 *
 * 1.41 (`tree.context_menu`): the menu's other writes, each with the rights of the page that does it elsewhere —
 * `archive` / `unarchive` (the role gate canArchive() and {@see WikiAction::Archive}; an unarchive answers 404 for a
 * page that is not archived, in the trash, or not readable), `delete` (the trash, `trash.enabled`: canArchive() and
 * {@see WikiAction::Delete}; `preview: true` only counts the pages that would go), `duplicate` ({@see WikiPageDuplicator}:
 * canCreate() and canEdit(), Create on the space and Edit on the parent, as a placed new page) and `parents` (the
 * parents the "Move to…" dialog offers: the "Move" form's rules). A move that lost a race (`stale`) answers 409.
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

    /**
     * @param array<string, array{path: string, name: string}> $routes the nowo_wiki routes (1.41)
     * @param array<string, int> $pagination the nowo_wiki.pagination config (1.41: max_tree_pages caps the parents list)
     */
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
        private readonly ?WikiTrashService $trash = null,
        private readonly ?WikiPageDuplicator $duplicator = null,
        private readonly ?WikiQuotaGate $quota = null,
        private readonly ?WikiPageTreeBuilder $treeBuilder = null,
        private readonly array $routes = [],
        private readonly array $pagination = [],
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
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/tree/{action}', name: 'nowo_wiki_space_tree', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG, 'action' => 'move|state|rename|icon|archive|unarchive|delete|duplicate|parents'], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'space_tree'], methods: ['POST'])]
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

        if ($action === 'unarchive') {
            return $this->unarchive($user, $space, $body['page'] ?? null);
        }
        $page = $this->requireReadablePage($user, $space, $body['page'] ?? null);
        if ($action === 'rename' || $action === 'icon') {
            return $this->menuWrite($request, $user, $space, $page, $action, $body);
        }
        if (in_array($action, ['archive', 'delete', 'duplicate', 'parents'], true)) {
            if (!$this->contextMenu) {
                throw new AccessDeniedHttpException('Access denied.');
            }

            return match ($action) {
                'archive'   => $this->archive($user, $space, $page),
                'delete'    => $this->delete($user, $space, $page, ($body['preview'] ?? false) === true),
                'duplicate' => $this->duplicate($user, $space, $page),
                default     => $this->parents($user, $space, $page),
            };
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
            return $this->moveRefusal($exception);
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
    private function menuWrite(Request $request, UserInterface $user, WikiSpace $space, WikiPage $page, string $action, array $body): JsonResponse
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
            } catch (WikiPageArchivedException|WikiPageNotFoundException) {
                // The value was checked above: the page was archived or deleted between the read and the lock (RS-I1).
                // 1.41: only those refusals (audit SH-L1); an ORM error is not a page that left.
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
        } catch (WikiPageArchivedException) {
            // The title was checked above: the page was archived between the read and the lock (RS-I1).
            return $this->pageGone();
        }

        return new JsonResponse([
            'title'   => $page->getTitle(),
            'message' => $this->trans('wiki.tree_menu.renamed', ['%from%' => $from, '%title%' => $page->getTitle()]),
        ], Response::HTTP_OK, self::NO_STORE);
    }

    /**
     * 1.41: Archive (the page view's Archive: no confirmation of the sub-pages, they stay where they are). Answers the
     * sentence to announce; the menu offers Unarchive right after it.
     */
    private function archive(UserInterface $user, WikiSpace $space, WikiPage $page): JsonResponse
    {
        if (!isset($this->routes['page_archive']) || !$this->pageService instanceof WikiPageServiceInterface) {
            throw new NotFoundHttpException('Not found.');
        }
        if (!$this->accessChecker->canArchive($user) || !$this->spaceGuard->isGranted($user, WikiAction::Archive, $space, $page)) {
            throw new AccessDeniedHttpException('Access denied.');
        }
        // @igor-ignore - The page service is stateless: archive() writes the page row under its lock, nothing is kept.
        $this->pageService->archive($page, $user);

        return new JsonResponse(['page' => $page->getId(), 'message' => $this->trans('wiki.tree_menu.archived', ['%title%' => $page->getTitle()])], Response::HTTP_OK, self::NO_STORE);
    }

    /**
     * 1.41: Unarchive (the menu's undo of Archive), with the checks of the space page's Unarchive: an archived page of
     * the space the user may read with every ancestor, not in the trash (a trashed page comes back with its deletion),
     * {@see WikiAction::Archive}, and the host's quota. Anything else answers 404, so no archived page is revealed.
     */
    private function unarchive(UserInterface $user, WikiSpace $space, mixed $id): JsonResponse
    {
        if (!$this->contextMenu) {
            throw new AccessDeniedHttpException('Access denied.');
        }
        if (!isset($this->routes['page_unarchive']) || !$this->pageService instanceof WikiPageServiceInterface) {
            throw new NotFoundHttpException('Not found.');
        }
        $page = is_string($id) && preg_match('/^[A-Za-z0-9-]{1,36}$/', $id) === 1 ? $this->pageRepository->findById($id) : null;
        if (!$page instanceof WikiPage || !$page->isArchived() || $page->getSpace()->getId() !== $space->getId()
            || isset($this->trash?->trashedIds($space)[$page->getId()]) || !$this->isReadableBranch($user, $space, $page)) {
            throw new NotFoundHttpException('Page not found.');
        }
        if (!$this->accessChecker->canArchive($user) || !$this->spaceGuard->isGranted($user, WikiAction::Archive, $space, $page)) {
            throw new AccessDeniedHttpException('Access denied.');
        }
        $refusal = $this->quota?->refusal(WikiQuotaCheckEvent::PAGE_UNARCHIVE, $user, $space, 1, 0, $page);
        if ($refusal !== null) {
            return new JsonResponse(['error' => 'quota', 'message' => $refusal], Response::HTTP_UNPROCESSABLE_ENTITY, self::NO_STORE);
        }
        // @igor-ignore - The page service is stateless: unarchive() writes the page row under its lock, nothing is kept.
        $this->pageService->unarchive($page, $user);

        return new JsonResponse(['page' => $page->getId(), 'message' => $this->trans('wiki.tree_menu.unarchived', ['%title%' => $page->getTitle()])], Response::HTTP_OK, self::NO_STORE);
    }

    /**
     * 1.41: Delete (`trash.enabled`): the page and its sub-pages go to the trash in one deletion, as the page view's
     * Delete. `$preview` writes nothing and answers the count and the confirmation sentence. The count holds the pages
     * the user may read (as the trash list, audit DO-I2): sub-pages hidden from them go too, uncounted.
     */
    private function delete(UserInterface $user, WikiSpace $space, WikiPage $page, bool $preview): JsonResponse
    {
        if (!$this->trash instanceof WikiTrashService) {
            throw new NotFoundHttpException('Not found.');
        }
        if (!$this->accessChecker->canArchive($user) || !$this->spaceGuard->isGranted($user, WikiAction::Delete, $space, $page)) {
            throw new AccessDeniedHttpException('Access denied.');
        }
        $count  = $this->readableSubtreeCount($user, $space, $page);
        $params = ['%title%' => $page->getTitle(), '%count%' => $count - 1];
        if ($preview) {
            return new JsonResponse(['count' => $count, 'message' => $this->trans('wiki.tree_menu.delete_confirm', $params)], Response::HTTP_OK, self::NO_STORE);
        }
        try {
            // @igor-ignore - WikiTrashService is stateless: delete() writes the deletion under the space lock.
            $this->trash->delete($page, $user);
        } catch (WikiPageArchivedException) {
            // Another request put the page in the trash between the read and the lock.
            return $this->pageGone();
        }

        return new JsonResponse(['page' => $page->getId(), 'count' => $count, 'message' => $this->trans('wiki.tree_menu.deleted', $params)], Response::HTTP_OK, self::NO_STORE);
    }

    /**
     * 1.41: Duplicate ({@see WikiPageDuplicator}: the page only, right below it). The rights of a new page placed next
     * to another (1.35, audit PT-L2): canCreate() and canEdit(), Create on the space, Edit on the parent (the space at
     * the top level), View on the page (already checked), and the host's quota. Answers the copy's address; the flash
     * says what happened on the page the menu opens next.
     */
    private function duplicate(UserInterface $user, WikiSpace $space, WikiPage $page): JsonResponse
    {
        // Audit SM-I3: a host without the new-page route (no page creation in the UI) gets no copies either.
        if (!$this->duplicator instanceof WikiPageDuplicator || !isset($this->routes['page_view'], $this->routes['page_new'])) {
            throw new NotFoundHttpException('Not found.');
        }
        $parent = $page->getParent();
        if (!$this->accessChecker->canCreate($user) || !$this->accessChecker->canEdit($user)
            || !$this->spaceGuard->isGranted($user, WikiAction::Create, $space) || !$this->spaceGuard->isGranted($user, WikiAction::Edit, $space, $parent)) {
            throw new AccessDeniedHttpException('Access denied.');
        }
        $refusal = $this->quota?->refusal(WikiQuotaCheckEvent::PAGE_CREATE, $user, $space, 1, 0, $page);
        if ($refusal !== null) {
            return new JsonResponse(['error' => 'quota', 'message' => $refusal], Response::HTTP_UNPROCESSABLE_ENTITY, self::NO_STORE);
        }
        // The copy keeps the suffix within the title column (as the history's copy, audit DT-I2).
        $suffix = $this->trans('wiki.tree_menu.copy_suffix');
        $title  = rtrim(mb_substr($page->getTitle(), 0, self::MAX_TITLE - mb_strlen($suffix) - 1)) . ' ' . $suffix;
        // Audit SM-L1: the scope carries the parent checked above; a page moved since then answers 409 `stale`.
        $scope = new WikiPageMoveScope($this->readableIds($user, $space, $this->pageRepository->findActiveBySpace($space)), $parent?->getId());
        try {
            $copy = $this->duplicator->duplicate($page, $title, $user, $scope)->page;
        } catch (WikiPageMoveException $exception) {
            return $this->moveRefusal($exception);
        } catch (WikiSlugTakenException) {
            return $this->refusal(Response::HTTP_CONFLICT, 'conflict', 'wiki.tree_menu.error.conflict');
        } catch (WikiPageNotFoundException) {
            return $this->pageGone();
        }
        $message = $this->trans('wiki.tree_menu.duplicated', ['%title%' => $page->getTitle(), '%copy%' => $copy->getTitle()]);
        $this->addFlash('success', $message);

        return new JsonResponse([
            'page'    => $copy->getId(),
            'url'     => $this->generateUrl($this->routes['page_view']['name'], ['spaceSlug' => $space->getSlug(), 'pageSlug' => $copy->getSlug()]),
            'message' => $message,
        ], Response::HTTP_OK, self::NO_STORE);
    }

    /**
     * 1.41: the parents "Move to…" offers, with the rules of the "Move" form: the user may move the page (canEdit(),
     * Edit on the page and on its parent), and each parent is a page of the tree (readable with every ancestor, active,
     * within `pagination.max_tree_pages`) they may edit, outside the page's own sub-tree; `root` when they may edit the
     * space. Answers `{parents: [{id, label, depth}], root, parent}` in tree order; the move itself is the `move` action.
     */
    private function parents(UserInterface $user, WikiSpace $space, WikiPage $page): JsonResponse
    {
        $from = $page->getParent();
        if (!$this->accessChecker->canEdit($user)) {
            throw new AccessDeniedHttpException('Access denied.');
        }
        $this->requireMovable($user, $space, $page, $from, $from);

        $decisions = [];
        $visible   = [];
        foreach ($this->pageRepository->findActiveBySpace($space) as $candidate) {
            if ($this->spaceGuard->isPageReadable($user, $space, $candidate, $decisions)) {
                $visible[] = $candidate;
            }
        }
        $max = max(1, $this->pagination['max_tree_pages'] ?? 500);
        // Audit SM-I4: the whole tree first, then the cap, so the page's own sub-tree is always left out.
        $tree    = ($this->treeBuilder ?? new WikiPageTreeBuilder())->build($visible);
        $parents = [];
        $walk    = function (array $nodes, int $depth) use (&$walk, &$parents, $user, $space, $page, $max): void {
            foreach ($nodes as $node) {
                /** @var array{page: WikiPage, children: list<array{page: WikiPage, children: list<mixed>}>} $node */
                if ($node['page'] === $page || count($parents) >= $max) {
                    // The page's own sub-tree: a move there would be a cycle.
                    continue;
                }
                if ($this->spaceGuard->isGranted($user, WikiAction::Edit, $space, $node['page'])) {
                    $parents[] = ['id' => $node['page']->getId(), 'label' => str_repeat('— ', $depth) . $node['page']->getTitle(), 'depth' => $depth];
                }
                $walk($node['children'], $depth + 1);
            }
        };
        $walk($tree, 0);

        return new JsonResponse([
            'parents' => $parents,
            'root'    => $this->spaceGuard->isGranted($user, WikiAction::Edit, $space),
            'parent'  => $from?->getId(),
        ], Response::HTTP_OK, self::NO_STORE);
    }

    /** Pages of the sub-tree (the page included) the user may read: active pages, with every ancestor readable. */
    private function readableSubtreeCount(UserInterface $user, WikiSpace $space, WikiPage $page): int
    {
        $children = [];
        foreach ($this->pageRepository->findActiveBySpace($space) as $candidate) {
            $parentId = $candidate->getParent()?->getId();
            if ($parentId !== null) {
                $children[$parentId][] = $candidate;
            }
        }
        $decisions = [];
        $count     = 0;
        $queue     = [$page];
        while ($queue !== []) {
            $node = array_shift($queue);
            // A hidden page hides its whole sub-tree (DI-L1); a parent cycle in the data reads as hidden, so the walk
            // never comes back to a page it counted.
            if (!$this->spaceGuard->isPageReadable($user, $space, $node, $decisions)) {
                continue;
            }
            ++$count;
            foreach ($children[$node->getId()] ?? [] as $child) {
                $queue[] = $child;
            }
        }

        return $count;
    }

    /** A refused tree write: 409 when it lost a race (`stale`: moved by someone else meanwhile), 422 otherwise. */
    private function moveRefusal(WikiPageMoveException $exception): JsonResponse
    {
        return new JsonResponse(
            ['error' => $exception->reason, 'message' => $this->trans('wiki.tree.error.' . $exception->reason)],
            $exception->reason === 'stale' ? Response::HTTP_CONFLICT : Response::HTTP_UNPROCESSABLE_ENTITY,
            self::NO_STORE,
        );
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
