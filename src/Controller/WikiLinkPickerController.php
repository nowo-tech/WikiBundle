<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Controller;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\LinkPicker\WikiLinkPickerService;
use Nowo\WikiBundle\Routing\WikiRouteLoader;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

use function in_array;
use function is_string;
use function max;
use function str_contains;
use function strtolower;
use function time;

/**
 * 1.40: the two internal JSON endpoints of the Milkdown link picker (`editor.milkdown.link_picker`, contract "section
 * anchors and link picker" §3.1; session-authenticated UI endpoints, not versioned):
 *
 * | Route key / name | Path | Answer |
 * |---|---|---|
 * | `link_candidates` / `nowo_wiki_link_candidates` | `GET …/spaces/{spaceSlug}/link-candidates?q=` | `{here: [{id, label, url}], others: [{wiki, pages}]}` |
 * | `link_sections` / `nowo_wiki_link_sections` | `GET …/spaces/{spaceSlug}/link-candidates/{pageId}/sections` | `{sections: [{id, level, text}]}` or 404 |
 *
 * Both answer 404 while the feature is off and ask, in order: a firewall-trusted user (403), the same origin (403 for a
 * cross-site `Sec-Fetch-Site` or a foreign `Origin`), `Accept: application/json` (406) and the per-user rate limiter
 * (429 with `Retry-After`). A missing or unreadable space gives the same empty candidates as "no match", and the same 404
 * on sections as a missing page, so neither endpoint is an oracle. Answers are `Cache-Control: private, no-store`,
 * `Vary: Cookie`. The data and every permission check are {@see WikiLinkPickerService}'s. Stateless.
 */
final class WikiLinkPickerController extends AbstractController
{
    /** Headers of every answer. */
    private const HEADERS = ['Cache-Control' => 'no-store, private', 'Vary' => 'Cookie', 'X-Content-Type-Options' => 'nosniff'];

    /** Prefix of the rate limiter key (per user). */
    public const LIMITER_KEY = 'nowo_wiki_link_picker:';

    /**
     * @param WikiSpaceGuard $spaceGuard resolves the space of the route
     * @param WikiLinkPickerService|null $picker the picker (null: the feature is off, 404)
     * @param RateLimiterFactory|null $rateLimiter per-user limiter (required with the feature)
     * @param WikiTokenGuard|null $tokenGuard ignores tokens of a firewall without security
     */
    public function __construct(
        private readonly WikiSpaceGuard $spaceGuard,
        private readonly ?WikiLinkPickerService $picker = null,
        private readonly ?RateLimiterFactory $rateLimiter = null,
        private readonly ?WikiTokenGuard $tokenGuard = null,
    ) {
    }

    /**
     * Pages a link may point at, for a query typed in the picker of a page of the space.
     *
     * @throws NotFoundHttpException while the feature is off
     * @throws AccessDeniedHttpException without a trusted user or from another origin
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/link-candidates', name: 'nowo_wiki_link_candidates', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'link_candidates'], methods: ['GET'])]
    public function candidates(Request $request, string $spaceSlug): Response
    {
        [$picker, $user, $refused] = $this->guard($request);
        if ($refused instanceof Response) {
            return $refused;
        }
        $query = $request->query->all()['q'] ?? '';

        return new JsonResponse($picker->candidates($user, $this->space($user, $spaceSlug), is_string($query) ? $query : ''), Response::HTTP_OK, self::HEADERS);
    }

    /**
     * The sections of a page the picker offers (step 2).
     *
     * @throws NotFoundHttpException while the feature is off, or for a missing, archived or unreadable page
     * @throws AccessDeniedHttpException without a trusted user or from another origin
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/link-candidates/{pageId}/sections', name: 'nowo_wiki_link_sections', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG, 'pageId' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'link_sections'], methods: ['GET'])]
    public function sections(Request $request, string $spaceSlug, string $pageId): Response
    {
        [$picker, $user, $refused] = $this->guard($request);
        if ($refused instanceof Response) {
            return $refused;
        }
        $sections = $picker->sections($user, $this->space($user, $spaceSlug), $pageId);
        if ($sections === null) {
            throw new NotFoundHttpException('Page not found.');
        }

        return new JsonResponse(['sections' => $sections], Response::HTTP_OK, self::HEADERS);
    }

    /**
     * The checks shared by both endpoints.
     *
     * @return array{0: WikiLinkPickerService, 1: UserInterface, 2: Response|null}
     */
    private function guard(Request $request): array
    {
        $picker = $this->picker ?? throw new NotFoundHttpException('Not found.');
        $user   = $this->tokenGuard instanceof WikiTokenGuard && !$this->tokenGuard->isTokenTrusted() ? null : $this->getUser();
        if (!$user instanceof UserInterface) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        $site   = $request->headers->get('Sec-Fetch-Site');
        $origin = $request->headers->get('Origin');
        if (($site !== null && !in_array(strtolower($site), ['same-origin', 'none'], true)) || ($origin !== null && $origin !== $request->getSchemeAndHttpHost())) {
            throw new AccessDeniedHttpException('Cross-origin request refused.');
        }

        if (!str_contains(strtolower((string) $request->headers->get('Accept', '')), 'application/json')) {
            return [$picker, $user, new JsonResponse(['error' => 'not_acceptable'], Response::HTTP_NOT_ACCEPTABLE, self::HEADERS)];
        }

        $limit = $this->rateLimiter?->create(self::LIMITER_KEY . $user->getUserIdentifier())->consume();
        if ($limit !== null && !$limit->isAccepted()) {
            return [$picker, $user, new JsonResponse(['error' => 'rate_limited'], Response::HTTP_TOO_MANY_REQUESTS, self::HEADERS + ['Retry-After' => (string) max(1, $limit->getRetryAfter()->getTimestamp() - time())])];
        }

        return [$picker, $user, null];
    }

    /**
     * The readable space of the slug, or null (missing and hidden read the same).
     */
    private function space(UserInterface $user, string $spaceSlug): ?WikiSpace
    {
        try {
            return $this->spaceGuard->requireSpace($user, $spaceSlug);
        } catch (NotFoundHttpException) {
            return null;
        }
    }
}
