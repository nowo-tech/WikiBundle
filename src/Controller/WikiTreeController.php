<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Controller;

use JsonException;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Routing\WikiRouteLoader;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Service\WikiPageMoveException;
use Nowo\WikiBundle\Service\WikiPageMover;
use Nowo\WikiBundle\Service\WikiPageMoveScope;
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
use function preg_match;
use function str_starts_with;
use function strlen;

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
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/tree/{action}', name: 'nowo_wiki_space_tree', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG, 'action' => 'move|state'], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'space_tree'], methods: ['POST'])]
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
