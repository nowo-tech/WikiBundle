<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiPageDuplicatedEvent;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use Nowo\WikiBundle\Util\WikiSlugger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function rtrim;
use function sprintf;
use function substr;
use function usort;

use const PHP_INT_MAX;

/**
 * 1.41 (`tree.context_menu`): "Duplicate" of the page tree's menu. The copy holds the page's current revision (title
 * given by the caller, same body and format: a new page with its own first revision, so the history starts again),
 * its icon, and goes right below the page under the same parent, written by {@see WikiPageMover::createNextTo()}: the
 * space lock, the sibling ordering and the depth limit of every tree write, the page service's create (revision,
 * events), then {@see WikiEvents::PAGE_DUPLICATED} (1.41, audit SM-I1: the copy has the readers of its place; host access
 * rules are not copied). Its slug is the page's slug with `-copy` (`-copy-2`, `-copy-3`, … when taken), chosen under the space lock.
 *
 * Owner default (1.41): the page only, not its sub-pages. `$withChildren` (not exposed by any endpoint) also copies the
 * active sub-pages the actor may read (`$scope`), each under its copy, last place, at most {@see self::MAX_PAGES} pages;
 * every copy is its own unit of work (a failure stops the walk and keeps the copies already written).
 *
 * Stateless: nothing is kept between calls (FrankenPHP worker mode).
 */
final readonly class WikiPageDuplicator
{
    /** Most pages one duplicate writes (the page included). */
    public const MAX_PAGES = 200;

    /** Longest slug ({@see WikiPage} column). */
    private const MAX_SLUG = 255;

    public function __construct(
        private WikiPageServiceInterface $pages,
        private WikiPageMover $mover,
        private WikiPageRepositoryInterface $repository,
        private WikiSlugger $slugger,
        private ?WikiPageIconService $icons = null,
        private int $maxPages = self::MAX_PAGES,
        private ?EventDispatcherInterface $dispatcher = null,
        private ?WikiTransactionRunner $runner = null,
    ) {
    }

    /**
     * @param WikiPage $page an active page with a current revision
     * @param string $title title of the copy
     * @param object $actor author of the copies' first revisions
     * @param WikiPageMoveScope|null $scope what the actor sees: the answer's place, and the sub-pages copied with
     *                                      `$withChildren` (null: every page)
     * @param bool $withChildren also copy the readable active sub-pages (not exposed in 1.41)
     *
     * @throws WikiPageMoveException `page_missing` / `stale` / `too_deep` from the mover: nothing is written
     * @throws WikiPageNotFoundException when the page has no current revision
     * @throws WikiSlugTakenException when a create outside the space lock took the free slug meanwhile (nothing written)
     *
     * @return WikiPageCreateResult the copy and its place among the siblings the actor sees
     */
    public function duplicate(WikiPage $page, string $title, object $actor, ?WikiPageMoveScope $scope = null, bool $withChildren = false): WikiPageCreateResult
    {
        // 2.0 (W1-2, 1.55.2 audit ZBO-2): a wiki row your manager loaded is loaded again by id in the wiki's private manager.
        $page   = $this->runner?->attach($page) ?? $page;
        $space  = $page->getSpace();
        $create = fn (): WikiPage => $this->copy($page, $title, $page->getParent(), $actor);
        // Audit SM-L1: with a scope, its parent id is the parent the caller checked; a page moved since then is `stale`.
        $result = $this->mover->createNextTo($space, $page, true, $create, $scope, true);
        $this->copied($page, $result->page, $actor);

        if ($withChildren) {
            $written = 1;
            $this->copyChildren($page, $result->page, $actor, $scope, $written, [$page->getId() => true]);
        }

        return $result;
    }

    /**
     * @param array<string, true> $seen pages already walked (a parent cycle in the data stops the walk)
     */
    private function copyChildren(WikiPage $from, WikiPage $to, object $actor, ?WikiPageMoveScope $scope, int &$written, array $seen): void
    {
        foreach ($this->childrenOf($from, $scope) as $child) {
            if ($written >= $this->maxPages || isset($seen[$child->getId()])) {
                return;
            }
            $copy = $this->mover->createAt($to->getSpace(), $to, PHP_INT_MAX, fn (): WikiPage => $this->copy($child, $child->getTitle(), $to, $actor), $scope)->page;
            $this->copied($child, $copy, $actor);
            ++$written;
            $this->copyChildren($child, $copy, $actor, $scope, $written, $seen + [$child->getId() => true]);
        }
    }

    /**
     * The active children of a page the actor may read, in tree order.
     *
     * @return list<WikiPage>
     */
    private function childrenOf(WikiPage $parent, ?WikiPageMoveScope $scope): array
    {
        $children = [];
        foreach ($this->repository->findActiveBySpace($parent->getSpace()) as $candidate) {
            if ($candidate->getParent()?->getId() === $parent->getId() && (!$scope instanceof WikiPageMoveScope || isset($scope->visibleIds[$candidate->getId()]))) {
                $children[] = $candidate;
            }
        }
        usort($children, static fn (WikiPage $a, WikiPage $b): int => [$a->getPosition(), $a->getTitle()] <=> [$b->getPosition(), $b->getTitle()]);

        return $children;
    }

    /** Writes the copy (called by the mover under the space lock, so the free slug stays free). */
    private function copy(WikiPage $page, string $title, ?WikiPage $parent, object $actor): WikiPage
    {
        $revision = $page->getCurrentRevision();
        if (!$revision instanceof WikiPageRevision) {
            throw new WikiPageNotFoundException(sprintf('The page "%s" has no revision to copy.', $page->getId()));
        }
        $content = $revision->isMarkdown() ? (string) $revision->getContentMarkdown() : $revision->getContentHtml();

        return $this->pages->create($page->getSpace(), $title, $content, $actor, $parent, $this->freeSlug($page), $revision->getFormat());
    }

    /** `<slug>-copy`, then `<slug>-copy-2`, … : the first one not taken in the space. */
    private function freeSlug(WikiPage $page): string
    {
        $base = rtrim(substr($page->getSlug(), 0, self::MAX_SLUG - 12), '-') . '-copy';
        if (!$this->slugger->isValid($base)) {
            // A slug written before the slugger's rule (an import, a host): the copy gets a plain one.
            $base = 'page-copy';
        }
        $slug = $base;
        for ($n = 2; $this->repository->countBySpaceAndSlug($page->getSpace(), $slug) > 0; ++$n) {
            $slug = $base . '-' . $n;
        }

        return $slug;
    }

    /** After a copy committed: its icon, then {@see WikiEvents::PAGE_DUPLICATED} (the host may copy its access rules). */
    private function copied(WikiPage $source, WikiPage $copy, object $actor): void
    {
        $this->copyIcon($source, $copy);
        $this->dispatcher?->dispatch(new WikiPageDuplicatedEvent($source, $copy, $actor), WikiEvents::PAGE_DUPLICATED);
    }

    /**
     * The page's icon on the copy, in the icon service's own unit (as the page form, audit QY-L2): the copy is committed,
     * so a refusal there (the copy archived meanwhile, an icon no longer allowed, a lock wait) leaves it without an icon.
     */
    private function copyIcon(WikiPage $page, WikiPage $copy): void
    {
        $icon = $page->getIcon();
        if ($icon === null || !$this->icons instanceof WikiPageIconService) {
            return;
        }
        try {
            // @igor-ignore - WikiPageIconService is stateless: setIcon() writes the page row, the service keeps nothing.
            $this->icons->setIcon($copy, $icon);
        } catch (WikiInvalidIconException|WikiPageArchivedException|WikiPageNotFoundException|TransientStorageFailure) {
            return;
        }
    }
}
