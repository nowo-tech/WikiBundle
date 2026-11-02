<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown\Milkdown;

use Nowo\MilkdownEditorBundle\Markdown\Resolver\PageResolverInterface;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\ResolvedPage;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Repository\WikiPageBatchFinderInterface;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Resolves `[[wiki:<uuid>]]` page links of wiki pages for the current viewer (1.13, default
 * `nowo_wiki.editor.milkdown.page_resolver`): a page is returned — current title and `page_view` URL — only when it
 * is not archived, the viewer passes the general list gate and {@see WikiAction::View} is granted on its space, on it
 * and on each of its ancestors (the rule of the sidebar tree). Anything else is left out, so the renderer shows
 * *Content not visible* and never a title or an id. Without an authenticated, firewall-trusted user nothing resolves.
 */
final readonly class WikiMilkdownPageResolver implements PageResolverInterface
{
    /**
     * @param WikiPageRepositoryInterface $pages page lookup (batched when it implements {@see WikiPageBatchFinderInterface})
     * @param WikiSpaceAccessCheckerInterface $spaceAccess space- and page-aware decision
     * @param WikiAccessCheckerInterface $accessChecker general gate (list)
     * @param TokenStorageInterface|null $tokenStorage current user
     * @param UrlGeneratorInterface|null $urlGenerator page URLs (none: titles without link)
     * @param array<string, array{name: string, path?: string}> $routes the nowo_wiki routes
     * @param WikiTokenGuard|null $tokenGuard ignores tokens of a firewall without security
     */
    public function __construct(
        private WikiPageRepositoryInterface $pages,
        private WikiSpaceAccessCheckerInterface $spaceAccess,
        private WikiAccessCheckerInterface $accessChecker,
        private ?TokenStorageInterface $tokenStorage = null,
        private ?UrlGeneratorInterface $urlGenerator = null,
        private array $routes = [],
        private ?WikiTokenGuard $tokenGuard = null,
    ) {
    }

    public function resolvePages(array $ids): array
    {
        $user = $this->user();
        if ($ids === [] || !$user instanceof UserInterface || !$this->accessChecker->canList($user)) {
            return [];
        }

        $resolved = [];
        /** @var array<string, bool> $decisions */
        $decisions = [];
        /** @var array<string, bool> $spaces */
        $spaces = [];
        foreach ($this->find($ids) as $page) {
            if (!$page->isArchived() && $this->visible($user, $page, $decisions, $spaces)) {
                $resolved[$page->getId()] = new ResolvedPage($page->getTitle(), $this->url($page));
            }
        }

        return $resolved;
    }

    /**
     * @param list<string> $ids
     *
     * @return list<WikiPage>
     */
    private function find(array $ids): array
    {
        if ($this->pages instanceof WikiPageBatchFinderInterface) {
            return $this->pages->findByIds($ids);
        }

        $found = [];
        foreach ($ids as $id) {
            $page = $this->pages->findById($id);
            if ($page instanceof WikiPage) {
                $found[] = $page;
            }
        }

        return $found;
    }

    /**
     * Each page and each space is decided once per document (CV-L1: a document with many links to one space asks the
     * space checker once).
     *
     * @param array<string, bool> $decisions page id => readable with its ancestors (a parent cycle reads as hidden)
     * @param array<string, bool> $spaces space id => the space itself is readable
     */
    private function visible(UserInterface $user, WikiPage $page, array &$decisions, array &$spaces): bool
    {
        $id = $page->getId();
        if (!isset($decisions[$id])) {
            $decisions[$id] = false;
            $space          = $page->getSpace();
            $parent         = $page->getParent();
            $spaces[$space->getId()] ??= $this->spaceAccess->isGranted($user, WikiAction::View, $space);
            $decisions[$id] = $spaces[$space->getId()]
                && $this->spaceAccess->isGranted($user, WikiAction::View, $space, $page)
                && (!$parent instanceof WikiPage || $this->visible($user, $parent, $decisions, $spaces));
        }

        return $decisions[$id];
    }

    private function url(WikiPage $page): string
    {
        $route = $this->routes['page_view']['name'] ?? null;
        if ($route === null || !$this->urlGenerator instanceof UrlGeneratorInterface) {
            return '';
        }

        try {
            return $this->urlGenerator->generate($route, ['spaceSlug' => $page->getSpace()->getSlug(), 'pageSlug' => $page->getSlug()]);
        } catch (RoutingException) {
            return '';
        }
    }

    private function user(): ?UserInterface
    {
        if ($this->tokenGuard instanceof WikiTokenGuard && !$this->tokenGuard->isTokenTrusted()) {
            return null;
        }

        $user = $this->tokenStorage?->getToken()?->getUser();

        return $user instanceof UserInterface ? $user : null;
    }
}
