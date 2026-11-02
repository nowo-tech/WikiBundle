<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Controller;

use Nowo\WikiBundle\Collaboration\WikiCollabException;
use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Routing\WikiRouteLoader;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Collaboration endpoints of a page (1.13, Milkdown on Mercure, FR-076): `POST token`, `GET state`, `POST update`,
 * `POST awareness`, `POST compact`, `POST corrupt` under one base URL (the `collaboration.url` of MilkdownEditorType),
 * and `POST reset`, the editor's CSRF-protected "Reset collaboration" form.
 *
 * Every request — the token, the state and each update — asks a firewall-trusted user, the role gate canEdit() and
 * {@see WikiAction::View} + {@see WikiAction::Edit} on the space and the page (the host's
 * `WikiSpaceAccessCheckerInterface` maps them to e.g. `project.wiki.edit` / `wiki.edit`); a page the user may not
 * read answers 404, one they may read but not edit 403. POST requests must come from the same origin (`Origin`, or
 * `Sec-Fetch-Site` when there is no `Origin`); bodies are JSON (415 otherwise) and bounded (413). Answers are never
 * cached.
 */
final class WikiCollabController extends AbstractController
{
    /** Headers of every answer: never cached. */
    private const NO_STORE = ['Cache-Control' => 'no-store, private'];

    /** Endpoint => HTTP method. */
    public const ACTIONS = ['token' => 'POST', 'state' => 'GET', 'update' => 'POST', 'awareness' => 'POST', 'compact' => 'POST', 'corrupt' => 'POST', 'reset' => 'POST'];

    /**
     * @param WikiSpaceGuard $spaceGuard space resolution and access
     * @param WikiPageRepositoryInterface $pageRepository page lookups
     * @param WikiAccessCheckerInterface $accessChecker role gate (canEdit)
     * @param WikiCollabService|null $collab the collaboration service (null: collaboration disabled, every endpoint 404)
     * @param WikiTokenGuard|null $tokenGuard firewall-trust check of the security token
     * @param array<string, array{path: string, name: string}> $routes the nowo_wiki routes (redirect after a reset)
     */
    public function __construct(
        private readonly WikiSpaceGuard $spaceGuard,
        private readonly WikiPageRepositoryInterface $pageRepository,
        private readonly WikiAccessCheckerInterface $accessChecker,
        private readonly ?WikiCollabService $collab = null,
        private readonly ?WikiTokenGuard $tokenGuard = null,
        private readonly array $routes = [],
    ) {
    }

    /**
     * Dispatches one collaboration request.
     *
     * @throws NotFoundHttpException when collaboration is disabled or the page is missing, archived or not readable
     * @throws AccessDeniedHttpException without a trusted user, without the edit permission or from another origin
     * @throws MethodNotAllowedHttpException when the method does not match the endpoint
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/collab/{action}', name: 'nowo_wiki_page_collab', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG, 'pageSlug' => WikiRouteLoader::PAGE_SLUG, 'action' => 'token|state|update|awareness|compact|corrupt|reset'], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'page_collab'], methods: ['GET', 'POST'])]
    public function collab(Request $request, string $spaceSlug, string $pageSlug, string $action): Response
    {
        if (!$this->collab instanceof WikiCollabService || !isset(self::ACTIONS[$action])) {
            throw new NotFoundHttpException('Not found.');
        }

        if ($request->getMethod() !== self::ACTIONS[$action]) {
            throw new MethodNotAllowedHttpException([self::ACTIONS[$action]]);
        }

        $user = $this->requireUser();
        $page = $this->requireEditablePage($user, $spaceSlug, $pageSlug);
        if ($request->getMethod() === 'POST') {
            $this->requireSameOrigin($request);
        }

        if ($action === 'reset') {
            return $this->manualReset($request, $page, $spaceSlug);
        }

        try {
            return match ($action) {
                'state'     => new JsonResponse($this->collab->state($page, $user, $request), Response::HTTP_OK, self::NO_STORE),
                'corrupt'   => $this->noContent(fn () => $this->collab->corrupt($request, $page, $user)),
                'token'     => $this->noContent(fn () => $this->collab->token($request, $page, $user)),
                'update'    => $this->noContent(fn () => $this->collab->update($request, $page, $user)),
                'awareness' => $this->noContent(fn () => $this->collab->awareness($request, $page, $user)),
                default     => $this->noContent(fn () => $this->collab->compact($request, $page, $user)),
            };
        } catch (WikiCollabException $exception) {
            return new JsonResponse(['error' => $exception->reason], $exception->status, self::NO_STORE);
        }
    }

    /**
     * The editor's "Reset collaboration" form (CSRF token {@see WikiCollabService::resetTokenId()}): drops the log, the
     * live editors reload; back to the edit form.
     */
    private function manualReset(Request $request, WikiPage $page, string $spaceSlug): Response
    {
        if (!$this->isCsrfTokenValid(WikiCollabService::resetTokenId($page), $request->request->getString('_token'))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        try {
            $this->collab?->reset($page, $this->requireUser());
        } catch (WikiCollabException) {
            // Rate-limited or capped (CW-L3): nothing is reset, back to the editor.
        }

        $edit = $this->routes['page_edit']['name'] ?? null;

        return $edit !== null
            ? $this->redirectToRoute($edit, ['spaceSlug' => $spaceSlug, 'pageSlug' => $page->getSlug()])
            : new Response(null, Response::HTTP_NO_CONTENT, self::NO_STORE);
    }

    /**
     * @param callable(): void $call
     */
    private function noContent(callable $call): Response
    {
        $call();

        return new Response(null, Response::HTTP_NO_CONTENT, self::NO_STORE);
    }

    private function requireUser(): UserInterface
    {
        $user = $this->tokenGuard instanceof WikiTokenGuard && !$this->tokenGuard->isTokenTrusted() ? null : $this->getUser();
        if (!$user instanceof UserInterface) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        return $user;
    }

    private function requireEditablePage(UserInterface $user, string $spaceSlug, string $pageSlug): WikiPage
    {
        $space = $this->spaceGuard->requireSpace($user, $spaceSlug);
        $page  = $this->pageRepository->findBySlug($space, $pageSlug);
        if (!$page instanceof WikiPage || $page->isArchived()) {
            throw new NotFoundHttpException('Page not found.');
        }

        $this->spaceGuard->requireGranted($user, WikiAction::View, $space, $page);
        $this->spaceGuard->requireGranted($user, WikiAction::Edit, $space, $page);
        if (!$this->accessChecker->canEdit($user)) {
            throw new AccessDeniedHttpException('Access denied.');
        }

        return $page;
    }

    private function requireSameOrigin(Request $request): void
    {
        $origin = $request->headers->get('Origin');
        if ($origin !== null ? $origin !== $request->getSchemeAndHttpHost() : $request->headers->get('Sec-Fetch-Site', 'same-origin') !== 'same-origin') {
            throw new AccessDeniedHttpException('Cross-origin request refused.');
        }
    }
}
