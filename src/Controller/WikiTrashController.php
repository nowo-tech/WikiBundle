<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Controller;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Entity\WikiTrashEntry;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Event\WikiQuotaCheckEvent;
use Nowo\WikiBundle\Form\WikiPostActionType;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Routing\WikiRouteLoader;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Service\WikiQuotaGate;
use Nowo\WikiBundle\Trash\WikiTrashService;
use Nowo\WikiBundle\Ui\WikiPageFactory;
use Nowo\WikiBundle\Ui\WikiPages;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function max;
use function preg_match;

/**
 * The trash of a space (1.14, T065, `trash.enabled`): Delete on a page (POST + CSRF behind a confirm), the trash list
 * (Restore, Purge), the purge confirmation page and the purge itself (POST + CSRF behind a second confirm).
 *
 * Every action asks a firewall-trusted user and the role gate canArchive(). Delete and Restore ask
 * {@see WikiAction::Delete}, Purge {@see WikiAction::Purge}, both from the host's
 * `WikiSpaceAccessCheckerInterface` (the 1.x mapping: archive roles to delete, import / manage roles to purge, D-W5).
 * A page or deletion the user may not read (with every ancestor) answers 404, like a missing one. Without the trash
 * every route answers 404.
 */
final class WikiTrashController extends AbstractController
{
    use WikiCsrfTrait;

    /** Format of a batch id (UUID). */
    private const BATCH = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    /**
     * @param array<string, array{path: string, name: string}> $routes the nowo_wiki routes
     */
    public function __construct(
        private readonly WikiSpaceGuard $spaceGuard,
        private readonly WikiPageRepositoryInterface $pageRepository,
        private readonly WikiAccessCheckerInterface $accessChecker,
        private readonly WikiPageFactory $pageFactory,
        private readonly WikiPages $pages,
        private readonly array $routes = [],
        private readonly ?WikiTrashService $trash = null,
        private readonly ?WikiTokenGuard $tokenGuard = null,
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?WikiQuotaGate $quota = null,
    ) {
    }

    /** CSRF token id of the Delete form of a page. */
    public static function deleteTokenId(WikiPage $page): string
    {
        return 'wiki_delete_' . $page->getId();
    }

    /**
     * Moves the page and its sub-pages to the trash, then back to the space.
     *
     * @throws NotFoundHttpException without the trash, or for a page that is missing, archived or not readable
     * @throws AccessDeniedHttpException without the rights or a valid token
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/delete', name: 'nowo_wiki_page_delete', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG, 'pageSlug' => WikiRouteLoader::PAGE_SLUG], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'page_delete'], methods: ['POST'])]
    public function delete(Request $request, string $spaceSlug, string $pageSlug): RedirectResponse
    {
        $trash = $this->requireTrash();
        $user  = $this->requireUser();
        $space = $this->spaceGuard->requireSpace($user, $spaceSlug);
        $page  = $this->pageRepository->findBySlug($space, $pageSlug);
        if (!$page instanceof WikiPage || $page->isArchived()) {
            throw new NotFoundHttpException('Page not found.');
        }
        $this->spaceGuard->requireReadablePage($user, $space, $page);
        $this->spaceGuard->requireGranted($user, WikiAction::Delete, $space, $page);
        if (!$this->isValidCsrf($request, self::deleteTokenId($page))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $trash->delete($page, $user);
        $this->flash('success', new TranslatableMessage('wiki.trash.deleted', ['%title%' => $page->getTitle()], WikiPages::DOMAIN));

        return $this->redirectToRoute($this->routes['space']['name'], ['spaceSlug' => $space->getSlug()]);
    }

    /**
     * The trash of the space: the deletions whose page the user may read, newest first.
     *
     * @throws NotFoundHttpException without the trash or a readable space
     * @throws AccessDeniedHttpException without {@see WikiAction::Delete} on the space
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/trash', name: 'nowo_wiki_space_trash', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'space_trash'], methods: ['GET'])]
    public function index(string $spaceSlug): Response
    {
        $trash = $this->requireTrash();
        $user  = $this->requireUser();
        $space = $this->spaceGuard->requireSpace($user, $spaceSlug);
        $this->spaceGuard->requireGranted($user, WikiAction::Delete, $space);
        $purge = $this->spaceGuard->isGranted($user, WikiAction::Purge, $space);

        $rows = [];
        foreach ($trash->roots($space) as $root) {
            $page = $root->getPage();
            if (!$this->spaceGuard->isPageReadable($user, $space, $page)) {
                continue;
            }
            $rows[] = [
                'title'   => $page->getTitle(),
                'deleted' => $root->getDeletedAt(),
                // DO-I2: only the pages the user may read are counted.
                'pages'   => count(array_filter($trash->batch($space, $root->getBatchId()), fn (WikiTrashEntry $entry): bool => $this->spaceGuard->isPageReadable($user, $space, $entry->getPage()))),
                'restore' => $this->postForm('wiki_trash_restore_' . $root->getBatchId()),
                'purge'   => $purge && $this->spaceGuard->isGranted($user, WikiAction::Purge, $space, $page),
                'batch'   => $root->getBatchId(),
            ];
        }
        $view = $this->pageFactory->trash($space, $rows, $trash->retentionDays());

        return $this->renderWikiPage('trash', ['page' => $view['page'], 'items' => $view['items']]);
    }

    /**
     * Restores a deletion (the page and its sub-pages), then shows the page.
     *
     * @throws NotFoundHttpException without the trash, or for a deletion that is missing or not readable
     * @throws AccessDeniedHttpException without the rights or a valid token
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/trash/{batchId}/restore', name: 'nowo_wiki_trash_restore', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG, 'batchId' => '[0-9a-f-]{36}'], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'trash_restore'], methods: ['POST'])]
    public function restore(Request $request, string $spaceSlug, string $batchId): RedirectResponse
    {
        $trash = $this->requireTrash();
        $user  = $this->requireUser();
        $space = $this->spaceGuard->requireSpace($user, $spaceSlug);
        $root  = $this->requireBatch($trash, $user, $space, $batchId);
        $this->spaceGuard->requireGranted($user, WikiAction::Delete, $space, $root->getPage());
        if (!$this->isValidCsrf($request, 'wiki_trash_restore_' . $batchId)) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
        // 1.21: the pages come back, so the host may refuse them (plan limits).
        $refusal = $this->quota?->refusal(WikiQuotaCheckEvent::TRASH_RESTORE, $user, $space, max(1, count($trash->batch($space, $batchId))), 0, $root->getPage());
        if ($refusal !== null) {
            $this->addFlash('error', $refusal);

            return $this->redirectToRoute($this->routes['space_trash']['name'], ['spaceSlug' => $space->getSlug()]);
        }

        $page = $trash->restore($space, $batchId, $user);
        $this->flash('success', new TranslatableMessage('wiki.trash.restored', ['%title%' => $root->getPage()->getTitle()], WikiPages::DOMAIN));

        return $page instanceof WikiPage && isset($this->routes['page_view'])
            ? $this->redirectToRoute($this->routes['page_view']['name'], ['spaceSlug' => $space->getSlug(), 'pageSlug' => $page->getSlug()])
            : $this->redirectToRoute($this->routes['space_trash']['name'], ['spaceSlug' => $space->getSlug()]);
    }

    /**
     * GET: the second confirmation of a purge (what goes for good). POST: purges the deletion, then back to the trash.
     *
     * @throws NotFoundHttpException without the trash, or for a deletion that is missing or not readable
     * @throws AccessDeniedHttpException without {@see WikiAction::Purge} or a valid token
     */
    #[Route(path: '/tools/wiki/spaces/{spaceSlug}/trash/{batchId}/purge', name: 'nowo_wiki_trash_purge', requirements: ['spaceSlug' => WikiRouteLoader::SPACE_SLUG, 'batchId' => '[0-9a-f-]{36}'], options: [WikiRouteLoader::ROUTE_KEY_OPTION => 'trash_purge'], methods: ['GET', 'POST'])]
    public function purge(Request $request, string $spaceSlug, string $batchId): Response
    {
        $trash = $this->requireTrash();
        $user  = $this->requireUser();
        $space = $this->spaceGuard->requireSpace($user, $spaceSlug);
        $root  = $this->requireBatch($trash, $user, $space, $batchId);
        $this->spaceGuard->requireGranted($user, WikiAction::Purge, $space, $root->getPage());
        $tokenId = 'wiki_trash_purge_' . $batchId;
        $batch   = $trash->batch($space, $batchId);
        $titles  = array_values(array_map(
            static fn (WikiTrashEntry $entry): string => $entry->getPage()->getTitle(),
            array_filter($batch, fn (WikiTrashEntry $entry): bool => $this->spaceGuard->isPageReadable($user, $space, $entry->getPage())),
        ));
        // DO-I1: a purge is irreversible; pages the user cannot see may only be purged by a space manager.
        if (count($titles) < count($batch) && !$this->spaceGuard->isGranted($user, WikiAction::Manage, $space)) {
            $this->flash('warning', new TranslatableMessage('wiki.trash.purge_hidden', [], WikiPages::DOMAIN));

            return $this->redirectToRoute($this->routes['space_trash']['name'], ['spaceSlug' => $space->getSlug()]);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isValidCsrf($request, $tokenId)) {
                throw new AccessDeniedHttpException('Invalid CSRF token.');
            }
            $count = $trash->purge($space, $batchId, $user);
            $this->flash('success', new TranslatableMessage('wiki.trash.purged', ['%title%' => $root->getPage()->getTitle(), '%count%' => $count], WikiPages::DOMAIN));

            return $this->redirectToRoute($this->routes['space_trash']['name'], ['spaceSlug' => $space->getSlug()]);
        }

        // Titles of pages the user may not read are not shown (a space manager purging them sees them counted).
        return $this->renderWikiPage('trash_purge', [
            'page'   => $this->pageFactory->trashPurge($space, $batchId, $root->getPage()->getTitle(), count($batch), $this->postForm($tokenId)),
            'titles' => $titles,
        ]);
    }

    private function requireTrash(): WikiTrashService
    {
        if (!$this->trash instanceof WikiTrashService) {
            throw new NotFoundHttpException('Not found.');
        }

        return $this->trash;
    }

    private function requireUser(): UserInterface
    {
        $user = $this->tokenGuard instanceof WikiTokenGuard && !$this->tokenGuard->isTokenTrusted() ? null : $this->getUser();
        if (!$user instanceof UserInterface || !$this->accessChecker->canArchive($user)) {
            throw new AccessDeniedHttpException('Access denied.');
        }

        return $user;
    }

    /**
     * The root entry of a deletion of the space whose page the user may read.
     *
     * @throws NotFoundHttpException otherwise
     */
    private function requireBatch(WikiTrashService $trash, UserInterface $user, WikiSpace $space, string $batchId): WikiTrashEntry
    {
        $root = preg_match(self::BATCH, $batchId) === 1 ? ($trash->batch($space, $batchId)[0] ?? null) : null;
        if (!$root instanceof WikiTrashEntry || !$this->spaceGuard->isPageReadable($user, $space, $root->getPage())) {
            throw new NotFoundHttpException('Not found.');
        }

        return $root;
    }

    private function postForm(string $tokenId): FormView
    {
        return $this->container->get('form.factory')->createNamed('', WikiPostActionType::class, null, ['csrf_token_id' => $tokenId])->createView();
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function renderWikiPage(string $page, array $vars): Response
    {
        return new Response($this->renderView($this->pages->template($page), $this->pages->context($vars)));
    }

    private function flash(string $type, TranslatableMessage $message): void
    {
        $this->addFlash($type, $this->translator instanceof TranslatorInterface ? $message->trans($this->translator) : $message);
    }
}
