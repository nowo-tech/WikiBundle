<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Controller;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\QuickAccess\WikiFavouriteResult;
use Nowo\WikiBundle\QuickAccess\WikiQuickAccess;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Routing\WikiRouteLoader;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Transaction\WikiConflictAnswer;
use Nowo\WikiBundle\Transaction\WikiStorageFailureSubscriber;
use Nowo\WikiBundle\Ui\WikiPages;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * 1.45 (`favourites.enabled`): "Add to favourites" / "Remove from favourites" of the page view (POST + CSRF).
 *
 * Reads the same rules as the page view: the role gate (`list`), the space and {@see WikiAction::View} on the page and
 * every ancestor; a page the person may not read, an archived one and an unknown one answer 404 alike. `favourite=1`
 * adds, `favourite=0` removes; both are idempotent (a concurrent request of the same person that did it first is the
 * expected unique-key race, answered as done). A full list (`favourites.max`) is refused with a message (HTML) or
 * `422 {"error": "limit_reached"}` (JSON). A lock conflict answers 409 + `X-Conflict-Code` (CONFLICT_001 with
 * `Retry-After`, CONFLICT_002 without; {@see WikiConflictAnswer}). HTML forms are redirected back to the page; JSON
 * requests get `{"favourite": bool, "result": "added" | "removed" | "unchanged"}`. Without favourites every call is 404.
 */
final class WikiFavouriteController extends AbstractController
{
    use WikiCsrfTrait;

    /**
     * @param array<string, array{path: string, name: string}> $routes the nowo_wiki routes
     */
    public function __construct(
        private readonly WikiSpaceGuard $spaceGuard,
        private readonly WikiPageRepositoryInterface $pageRepository,
        private readonly WikiAccessCheckerInterface $accessChecker,
        private readonly array $routes = [],
        private readonly ?WikiQuickAccess $quickAccess = null,
        private readonly ?WikiConflictAnswer $conflicts = null,
        private readonly ?WikiTokenGuard $tokenGuard = null,
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    /** CSRF token id of the favourite form of a page. */
    public static function tokenId(WikiPage $page): string
    {
        return 'wiki_favourite_' . $page->getId();
    }

    /**
     * @throws NotFoundHttpException without favourites, or for a page that is missing, archived or not readable
     * @throws AccessDeniedHttpException without a user or with an invalid CSRF token
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/favourite', name: 'nowo_wiki_page_favourite', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG, 'pageSlug' => WikiRouteLoader::PAGE_SLUG], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'page_favourite'], methods: ['POST'])]
    public function toggle(Request $request, string $spaceSlug, string $pageSlug): Response
    {
        $quickAccess = $this->quickAccess;
        if (!$quickAccess instanceof WikiQuickAccess || !$quickAccess->favouritesEnabled()) {
            throw new NotFoundHttpException('Not found.');
        }
        $user = $this->requireUser();
        if (!$this->accessChecker->canList($user) || !$quickAccess->canKeepFavourites($user)) {
            throw new NotFoundHttpException('Not found.');
        }
        $space = $this->spaceGuard->requireSpace($user, $spaceSlug);
        $page  = $this->pageRepository->findBySlug($space, $pageSlug);
        if (!$page instanceof WikiPage || $page->isArchived() || $page->getSlug() !== $pageSlug) {
            throw new NotFoundHttpException('Page not found.');
        }
        $this->spaceGuard->requireReadablePage($user, $space, $page);
        if (!$this->isValidCsrf($request, self::tokenId($page))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $asked = $request->request->getString('favourite', '1') !== '0';
        try {
            // @igor-ignore - a write to the database through a stateless service (rows, not service state); nothing is kept between requests.
            $result = $quickAccess->setFavourite($user, $page, $asked);
        } catch (Throwable $failure) {
            $answer = ($this->conflicts ?? new WikiConflictAnswer())->answer($request, $failure);
            if ($answer instanceof Response) {
                return $answer;
            }

            throw $failure;
        }

        if (WikiStorageFailureSubscriber::wantsJson($request)) {
            return $result === WikiFavouriteResult::LimitReached
                ? new JsonResponse(['error' => 'limit_reached'], Response::HTTP_UNPROCESSABLE_ENTITY, ['Cache-Control' => 'no-store'])
                : new JsonResponse(['favourite' => $result->isFavourite($asked), 'result' => $result->value], Response::HTTP_OK, ['Cache-Control' => 'no-store']);
        }
        $message = match ($result) {
            WikiFavouriteResult::LimitReached => new TranslatableMessage('wiki.favourites.limit_reached', [], WikiPages::DOMAIN),
            default                           => new TranslatableMessage($result->isFavourite($asked) ? 'wiki.favourites.added' : 'wiki.favourites.removed', ['%title%' => $page->getTitle()], WikiPages::DOMAIN),
        };
        $this->addFlash($result === WikiFavouriteResult::LimitReached ? 'warning' : 'success', $this->translator instanceof TranslatorInterface ? $message->trans($this->translator) : $message);

        return $this->redirectToRoute($this->routes['page_view']['name'] ?? 'nowo_wiki_page_view', ['spaceSlug' => $space->getSlug(), 'pageSlug' => $page->getSlug()]);
    }

    private function requireUser(): UserInterface
    {
        $user = $this->tokenGuard instanceof WikiTokenGuard && !$this->tokenGuard->isTokenTrusted() ? null : $this->getUser();
        if (!$user instanceof UserInterface) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        return $user;
    }
}
