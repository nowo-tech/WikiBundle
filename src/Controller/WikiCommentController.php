<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Controller;

use JsonException;
use Nowo\WikiBundle\Comment\WikiCommentException;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Panel\WikiPanelStateStoreInterface;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Routing\WikiRouteLoader;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Ui\WikiPages;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_keys;
use function array_values;
use function is_array;
use function is_bool;
use function is_string;
use function json_decode;
use function preg_match;
use function str_starts_with;
use function strlen;

use const JSON_THROW_ON_ERROR;

/**
 * Comment endpoints of a page (1.16, `comments.enabled`) under one base URL, the route key `page_comments`
 * (`…/pages/{pageSlug}/comments`, the Milkdown `comments.url`):
 *
 * | Method + path | Answer |
 * |---|---|
 * | `GET /threads` | `{threads, page, truncated?}` |
 * | `GET /threads/{id}` | Thread |
 * | `POST /threads` `{anchor \| null, body}` | 201 Thread |
 * | `POST /threads/{id}/comments` `{body}` | 201 Thread |
 * | `PATCH /threads/{id}` `{resolved}` or `{anchor, base}` | Thread (409: stale base) |
 * | `PATCH /comments/{cid}` `{body}` / `DELETE /comments/{cid}` | Thread (204: thread gone) |
 * | `PUT` / `DELETE /comments/{cid}/reactions/{emoji}` | Thread |
 * | `GET /` (no JavaScript) | 303 to the page with the panel open |
 * | `POST /new` (no JavaScript, form `_token` + `body`) | 303 back to the page |
 *
 * Every request asks a firewall-trusted user and a page readable with every ancestor (404 otherwise); the rights are
 * checked by {@see WikiCommentService} (403). Writes must come from the same origin and carry the page's CSRF token
 * ({@see WikiCommentService::csrfTokenId()}) in `X-CSRF-Token` (403); JSON bodies are bounded ({@see self::MAX_BODY},
 * 413) and well-formed (400 / 422). Answers are never cached.
 *
 * Also `POST …/spaces/{spaceSlug}/panel-state` `{panel: "comments", open}` (route key `space_panel_state`, CSRF token
 * {@see self::panelTokenId()}, 204): the open / closed state of the panel for the next render.
 */
final class WikiCommentController extends AbstractController
{
    /** Largest accepted JSON body, in bytes. */
    public const MAX_BODY = 16384;

    /** Headers of every answer: never cached. */
    private const NO_STORE = ['Cache-Control' => 'no-store, private'];

    /** Request path (after the base) => [method => handler]. */
    private const PATHS = [
        '#^threads$#'                                                => ['GET' => 'list', 'POST' => 'create'],
        '#^threads/([A-Za-z0-9_-]{1,64})$#'                          => ['GET' => 'thread', 'PATCH' => 'patchThread'],
        '#^threads/([A-Za-z0-9_-]{1,64})/comments$#'                 => ['POST' => 'reply'],
        '#^comments/([A-Za-z0-9_-]{1,64})$#'                         => ['PATCH' => 'editComment', 'DELETE' => 'deleteComment'],
        '#^comments/([A-Za-z0-9_-]{1,64})/reactions/([^/]{1,64})$#u' => ['PUT' => 'react', 'DELETE' => 'unreact'],
        '#^$#'                                                       => ['GET' => 'fallbackList'],
        '#^new$#'                                                    => ['POST' => 'fallbackCreate'],
    ];

    /**
     * @param array<string, array{path: string, name: string}> $routes the nowo_wiki routes (no-JavaScript redirects)
     * @param WikiCommentService|null $comments the comments (null: disabled, every endpoint 404)
     * @param WikiPanelStateStoreInterface|null $panelState the panel state store (null: disabled)
     */
    public function __construct(
        private readonly WikiSpaceGuard $spaceGuard,
        private readonly WikiPageRepositoryInterface $pageRepository,
        private readonly array $routes = [],
        private readonly ?WikiCommentService $comments = null,
        private readonly ?WikiPanelStateStoreInterface $panelState = null,
        private readonly ?WikiTokenGuard $tokenGuard = null,
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    /**
     * CSRF token id of the panel state endpoint of a space.
     */
    public static function panelTokenId(WikiSpace $space): string
    {
        return 'wiki_panel_' . $space->getId();
    }

    /**
     * Dispatches one comment request.
     *
     * @throws NotFoundHttpException when comments are disabled, the path is unknown or the page is not readable
     * @throws AccessDeniedHttpException without a trusted user, a valid CSRF token or the same origin
     * @throws MethodNotAllowedHttpException when the path does not take the method
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/comments/{path}', name: 'nowo_wiki_page_comments', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG, 'pageSlug' => WikiRouteLoader::PAGE_SLUG, 'path' => '(?:new|threads(?:/[A-Za-z0-9_-]{1,64}(?:/comments)?)?|comments/[A-Za-z0-9_-]{1,64}(?:/reactions/[^/]+)?)?'], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'page_comments'], defaults: ['path' => ''], methods: ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'])]
    public function comments(Request $request, string $spaceSlug, string $pageSlug, string $path = ''): Response
    {
        $service          = $this->comments ?? throw new NotFoundHttpException('Not found.');
        [$handler, $args] = $this->route($request->getMethod(), $path);
        $user             = $this->requireUser();
        $space            = $this->spaceGuard->requireSpace($user, $spaceSlug);
        $page             = $this->requireReadablePage($user, $space, $pageSlug);

        if ($handler === 'fallbackList') {
            return $this->toPage($space, $page);
        }
        if ($handler === 'fallbackCreate') {
            return $this->fallbackCreate($request, $service, $user, $space, $page);
        }

        if ($request->getMethod() !== 'GET') {
            $this->requireSameOrigin($request);
            if (!$this->isCsrfTokenValid(WikiCommentService::csrfTokenId($page), (string) $request->headers->get('X-CSRF-Token', ''))) {
                throw new AccessDeniedHttpException('Invalid CSRF token.');
            }
        }

        try {
            return match ($handler) {
                'list'          => $this->json200($service->list($user, $space, $page)),
                'thread'        => $this->json200($service->thread($user, $space, $page, $args[0])),
                'create'        => $this->json201($service->create($user, $space, $page, $this->field($request, 'anchor'), $this->field($request, 'body'))),
                'reply'         => $this->json201($service->reply($user, $space, $page, $args[0], $this->field($request, 'body'))),
                'patchThread'   => $this->json200($service->patchThread($user, $space, $page, $args[0], $this->body($request))),
                'editComment'   => $this->json200($service->editComment($user, $space, $page, $args[0], $this->field($request, 'body'))),
                'deleteComment' => $this->maybeGone($service->deleteComment($user, $space, $page, $args[0])),
                'react'         => $this->json200($service->react($user, $space, $page, $args[0], $args[1], true)),
                default         => $this->json200($service->react($user, $space, $page, $args[0], $args[1], false)),
            };
        } catch (WikiCommentException $exception) {
            return new JsonResponse(['error' => $exception->reason], $exception->status, self::NO_STORE);
        }
    }

    /**
     * Records the open / closed state of a side panel (`{panel: "comments", open: bool}`).
     *
     * @throws NotFoundHttpException when comments are disabled or the space is not readable
     * @throws AccessDeniedHttpException without a trusted user, a valid CSRF token or the same origin
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/panel-state', name: 'nowo_wiki_space_panel_state', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'space_panel_state'], methods: ['POST'])]
    public function panelState(Request $request, string $spaceSlug): Response
    {
        $store = $this->panelState ?? throw new NotFoundHttpException('Not found.');
        $user  = $this->requireUser();
        $this->requireSameOrigin($request);
        $space = $this->spaceGuard->requireSpace($user, $spaceSlug);
        try {
            $body = $this->body($request);
        } catch (WikiCommentException $exception) {
            return new JsonResponse(['error' => $exception->reason], $exception->status, self::NO_STORE);
        }
        $token = $request->headers->get('X-CSRF-Token') ?? (is_string($body['_token'] ?? null) ? $body['_token'] : '');
        if (!$this->isCsrfTokenValid(self::panelTokenId($space), $token)) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
        if (($body['panel'] ?? null) !== WikiPanelStateStoreInterface::COMMENTS || !is_bool($body['open'] ?? null)) {
            return new JsonResponse(['error' => 'invalid'], Response::HTTP_UNPROCESSABLE_ENTITY, self::NO_STORE);
        }
        // @igor-ignore - The store writes to the request's session (or the host's storage), not to itself.
        $store->setOpen($user, WikiPanelStateStoreInterface::COMMENTS, $body['open']);

        return new Response(null, Response::HTTP_NO_CONTENT, self::NO_STORE);
    }

    /**
     * @return array{0: string, 1: list<string>} handler and path arguments
     */
    private function route(string $method, string $path): array
    {
        foreach (self::PATHS as $pattern => $methods) {
            if (preg_match($pattern, $path, $matches) !== 1) {
                continue;
            }
            if (!isset($methods[$method])) {
                throw new MethodNotAllowedHttpException(array_keys($methods));
            }
            unset($matches[0]);

            return [$methods[$method], array_values($matches)];
        }

        throw new NotFoundHttpException('Not found.');
    }

    /** The no-JavaScript form: a page-level comment, then back to the page with the panel open. */
    private function fallbackCreate(Request $request, WikiCommentService $service, UserInterface $user, WikiSpace $space, WikiPage $page): RedirectResponse
    {
        $this->requireSameOrigin($request);
        if (!$this->isCsrfTokenValid(WikiCommentService::csrfTokenId($page), $request->request->getString('_token'))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
        try {
            $service->create($user, $space, $page, null, $request->request->getString('body'));
            $this->addFlash('success', $this->trans('wiki.comments.added'));
        } catch (WikiCommentException $exception) {
            if ($exception->status === Response::HTTP_FORBIDDEN) {
                throw new AccessDeniedHttpException('Access denied.', $exception);
            }
            $this->addFlash('warning', $this->trans('wiki.comments.error.' . $exception->reason));
        }

        return $this->toPage($space, $page);
    }

    /** Flash messages are translated when they are added (the layout prints them as text). */
    private function trans(string $key): string
    {
        return $this->translator?->trans($key, [], WikiPages::DOMAIN) ?? $key;
    }

    private function toPage(WikiSpace $space, WikiPage $page): RedirectResponse
    {
        $url = $this->generateUrl($this->routes['page_view']['name'] ?? 'nowo_wiki_page_view', ['spaceSlug' => $space->getSlug(), 'pageSlug' => $page->getSlug(), 'comments' => 1]);

        return new RedirectResponse($url . '#nowo-wiki-comments', Response::HTTP_SEE_OTHER, self::NO_STORE);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Request $request): array
    {
        if (!str_starts_with((string) $request->headers->get('Content-Type'), 'application/json')) {
            throw new WikiCommentException(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, 'json_required');
        }
        $content = $request->getContent();
        if (strlen($content) > self::MAX_BODY) {
            throw new WikiCommentException(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, 'too_large');
        }
        try {
            $body = json_decode($content, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new WikiCommentException(Response::HTTP_BAD_REQUEST, 'invalid_json');
        }
        if (!is_array($body)) {
            throw new WikiCommentException(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid');
        }

        /* @var array<string, mixed> $body */
        return $body;
    }

    private function field(Request $request, string $name): mixed
    {
        return $this->body($request)[$name] ?? null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json200(array $data): JsonResponse
    {
        return new JsonResponse($data, Response::HTTP_OK, self::NO_STORE);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json201(array $data): JsonResponse
    {
        return new JsonResponse($data, Response::HTTP_CREATED, self::NO_STORE);
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function maybeGone(?array $data): Response
    {
        return $data === null ? new Response(null, Response::HTTP_NO_CONTENT, self::NO_STORE) : $this->json200($data);
    }

    private function requireReadablePage(UserInterface $user, WikiSpace $space, string $pageSlug): WikiPage
    {
        $page = $this->pageRepository->findBySlug($space, $pageSlug);
        // An archived or trashed page takes its threads with it (404), as does a hidden ancestor (1.13.2).
        if (!$page instanceof WikiPage || $page->isArchived() || !$this->spaceGuard->isPageReadable($user, $space, $page)) {
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
}
