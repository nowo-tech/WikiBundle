<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use DateTimeImmutable;
use Nowo\WikiBundle\Doctrine\WikiManagedEntities;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiPageMovedEvent;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageTreeWriterInterface;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

use function array_filter;
use function array_search;
use function array_slice;
use function array_splice;
use function array_values;
use function count;
use function intdiv;
use function max;
use function min;

/**
 * Moves a page in the page tree of its space (1.14, T049): a new parent (or the root) and an index among the new
 * siblings. The caller checks the permissions; this checks the tree.
 *
 * - Same space only; the page and the new parent must be active (not archived).
 * - No cycles: the new parent may not be the page or one of its descendants.
 * - Depth limit: the deepest page of the moved sub-tree stays within `tree.max_depth` levels.
 * - Ordering: sparse integer positions (steps of {@see self::STEP}); a move writes one row when the neighbours leave
 *   a gap and renumbers the target siblings only when they do not (pages created before 1.14 all share position 0).
 * - One transaction holding the space lock ({@see WikiPageTreeWriterInterface}); `nowo_wiki.page.moved` after the
 *   commit.
 * - 1.35: {@see self::createAt()} and {@see self::createNextTo()} create a page straight at a place (the "New page
 *   above / below / as child" of the tree menu, `parent` / `position` of the API create) under the same lock and with
 *   the same ordering and depth rules.
 */
final readonly class WikiPageMover
{
    /** Gap between two sibling positions after a renumbering. */
    public const STEP = 1024;

    /** Largest absolute position before the siblings are renumbered. */
    public const LIMIT = 1 << 30;

    /**
     * @param WikiPageRepositoryInterface $repository page lookups
     * @param WikiPageTreeWriterInterface $writer the space-locked transaction
     * @param EventDispatcherInterface|null $dispatcher receives {@see WikiPageMovedEvent}
     * @param int $maxDepth tree.max_depth: deepest level of a page (roots are level 1)
     * @param WikiEventDeferral|null $deferral 1.24: holds the event until the caller's scope ends
     * @param WikiManagedEntities|null $managed 1.55.2 (audit ZBO-2): the wiki manager's instance of a space or page
     *                                          another manager loaded
     */
    public function __construct(
        private WikiPageRepositoryInterface $repository,
        private WikiPageTreeWriterInterface $writer,
        private ?EventDispatcherInterface $dispatcher = null,
        private int $maxDepth = 10,
        private ?WikiEventDeferral $deferral = null,
        private ?WikiTransactionRunner $runner = null,
        private ?WikiManagedEntities $managed = null,
    ) {
    }

    /**
     * Moves the page under the parent (null: the root) at the index (clamped) among the parent's other children.
     *
     * The whole sub-tree moves with the page, pages the actor cannot see included (as in Outline and Notion): their
     * readers become the readers of the new branch (audit DI-I3, documented).
     *
     * @param int $index 0-based index among the new siblings, the page left out (with a scope: among the siblings the
     *                   actor may read)
     * @param object|null $actor user who moves it (in the event)
     * @param WikiPageMoveScope|null $scope what the actor sees and the parent they may leave (null: every page, no check)
     *
     * @throws WikiPageMoveException when the page or the parent is gone, the page moved meanwhile (`stale`), or the move
     *                               would build a cycle or go too deep
     */
    public function move(WikiPage $page, ?WikiPage $parent, int $index, ?object $actor = null, ?WikiPageMoveScope $scope = null): WikiPageMoveResult
    {
        // 1.55.2 (audit ZBO-2): pages of another manager (the host's, with the private manager) are read again by id.
        $page                          = $this->managedPage($page, 'page_missing');
        $parent                        = $parent instanceof WikiPage ? $this->managedPage($parent, 'parent_missing') : null;
        [$result, $fromParent, $moved] = $this->unit(function () use ($page, $parent, $index, $scope): array {
            $this->writer->beginSpaceTransaction($page->getSpace());
            try {
                $planned = $this->plan($page, $parent, $index, $scope);
                $this->writer->commitSpaceTransaction();
            } catch (Throwable $exception) {
                $this->writer->rollbackSpaceTransaction();

                throw $exception;
            }

            return $planned;
        });

        if ($moved) {
            $event = new WikiPageMovedEvent($page, $fromParent, $page->getParent(), $result->index, $actor, new DateTimeImmutable());
            if ($this->dispatcher instanceof EventDispatcherInterface && $this->deferral instanceof WikiEventDeferral) {
                // 1.24: after a caller's scope (the REST API), a failing listener does not undo the move (audit IF-L3).
                $this->deferral->dispatch($this->dispatcher, $event, WikiEvents::PAGE_MOVED);
            } else {
                $this->dispatcher?->dispatch($event, WikiEvents::PAGE_MOVED);
            }
        }

        return $result;
    }

    /**
     * 1.35: creates a page and places it at the index among the parent's children (null: the root), in one transaction
     * holding the space lock, so two concurrent inserts of a space run one after the other and never share or garble a
     * position (as two moves). The ordering is the one of {@see self::move()}: sparse positions, the target siblings
     * renumbered only when the neighbours leave no gap. The depth limit applies. No `nowo_wiki.page.moved` event: the
     * page is new; the page service raises its saved and created events (held until the commit when a
     * {@see WikiEventDeferral} is wired).
     *
     * @param WikiSpace $space space of the new page
     * @param WikiPage|null $parent parent of the new page (null: the root); must be active and of the space
     * @param int $index 0-based index among the parent's children, clamped (`PHP_INT_MAX`: last); with a scope among
     *                   those the actor may read
     * @param callable(): WikiPage $create writes the page (the page service), called after the lock and the checks
     * @param WikiPageMoveScope|null $scope what the actor sees (null: every page); its parent id is not used
     *
     * @throws WikiPageMoveException `parent_missing` when the parent is gone, archived or of another space, `too_deep`
     *                               when the new page would be deeper than `tree.max_depth`; nothing is written
     */
    public function createAt(WikiSpace $space, ?WikiPage $parent, int $index, callable $create, ?WikiPageMoveScope $scope = null): WikiPageCreateResult
    {
        $space = $this->managed?->space($space) ?? $space;

        return $this->locked($space, function (array $byId, callable $parentOf) use ($space, $parent, $index, $create, $scope): WikiPageCreateResult {
            $target = null;
            if ($parent instanceof WikiPage) {
                $target = $byId[$parent->getId()] ?? null;
                if (!$target instanceof WikiPage || $target->getSpace()->getId() !== $space->getId()) {
                    throw new WikiPageMoveException('parent_missing');
                }
            }
            $siblings = $this->childrenOf($target, $byId, $parentOf);

            return $this->insert($target, $siblings, $this->slot($siblings, $index, $scope)[2], $create, $byId, $parentOf, $scope);
        });
    }

    /**
     * 1.35: creates a page right above or below a sibling, under that sibling's parent (the "New page above / below" of
     * the tree menu): the place is computed here, after the lock, from the sibling itself, so hidden siblings and a
     * truncated tree never shift it (audit PT-L3). Same transaction, ordering, depth limit and events as
     * {@see self::createAt()}.
     *
     * @param WikiPage $sibling the page to put the new one next to; its parent (as the caller read and checked it) becomes
     *                          the new page's parent
     * @param bool $after true: right below the sibling; false: right above it
     * @param callable(): WikiPage $create writes the page under the sibling's parent
     * @param WikiPageMoveScope|null $scope what the actor sees (for the answer's index only)
     * @param bool $scopeParent 1.41 (audit SM-L1): the scope's parent id is the parent the caller checked (compared after
     *                          the lock, as a move's, DI-I2) instead of the sibling's parent as the caller holds it now
     *
     * @throws WikiPageMoveException `page_missing` when the sibling is gone, archived or of another space, `stale` when its
     *                               parent changed since the caller checked it, `too_deep`; nothing is written
     */
    public function createNextTo(WikiSpace $space, WikiPage $sibling, bool $after, callable $create, ?WikiPageMoveScope $scope = null, bool $scopeParent = false): WikiPageCreateResult
    {
        $space         = $this->managed?->space($space) ?? $space;
        $sibling       = $this->managedPage($sibling, 'page_missing');
        $checkedParent = $scopeParent && $scope instanceof WikiPageMoveScope ? $scope->fromParentId : $sibling->getParent()?->getId();

        return $this->locked($space, function (array $byId, callable $parentOf) use ($space, $sibling, $after, $create, $scope, $checkedParent): WikiPageCreateResult {
            $current = $byId[$sibling->getId()] ?? null;
            if (!$current instanceof WikiPage || $current->getSpace()->getId() !== $space->getId()) {
                throw new WikiPageMoveException('page_missing');
            }
            $parentId = $parentOf($current);
            if ($parentId !== $checkedParent) {
                throw new WikiPageMoveException('stale');
            }
            $target   = $parentId !== null ? $byId[$parentId] : null;
            $siblings = $this->childrenOf($target, $byId, $parentOf);
            $real     = (int) array_search($current, $siblings, true) + ($after ? 1 : 0);

            return $this->insert($target, $siblings, $real, $create, $byId, $parentOf, $scope);
        });
    }

    /**
     * Runs a create inside the space transaction (pages read after the lock), in the deferral scope when wired.
     *
     * @param callable(array<string, WikiPage>, callable(WikiPage): ?string): WikiPageCreateResult $work
     */
    private function locked(WikiSpace $space, callable $work): WikiPageCreateResult
    {
        $run = function () use ($space, $work): WikiPageCreateResult {
            $this->writer->beginSpaceTransaction($space);
            try {
                $byId = [];
                foreach ($this->repository->findActiveBySpace($space) as $active) {
                    $byId[$active->getId()] = $active;
                }
                $parentOf = static fn (WikiPage $p): ?string => isset($byId[$p->getParent()?->getId() ?? '']) && $p->getParent()?->getId() !== $p->getId() ? $p->getParent()?->getId() : null;
                $result   = $work($byId, $parentOf);
                $this->writer->commitSpaceTransaction();
            } catch (Throwable $exception) {
                $this->writer->rollbackSpaceTransaction();

                throw $exception;
            }

            return $result;
        };

        // The page service's events wait for the commit (and are dropped on a rollback) when both share the deferral.
        $unit = fn (): WikiPageCreateResult => $this->unit($run);

        return $this->deferral instanceof WikiEventDeferral ? $this->deferral->run($unit) : $unit();
    }

    /**
     * Runs a tree change as one unit of work of the transaction runner (1.37: the space transaction is then a savepoint
     * of it, a failure leaves no level open and forgets the wiki rows it touched), or as it is without one.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    private function unit(callable $work): mixed
    {
        return $this->runner instanceof WikiTransactionRunner ? $this->runner->run($work) : $work();
    }

    /**
     * The active children of a page (null: the root pages, orphans included), in order.
     *
     * @param array<string, WikiPage> $byId
     * @param callable(WikiPage): ?string $parentOf
     *
     * @return list<WikiPage>
     */
    private function childrenOf(?WikiPage $parent, array $byId, callable $parentOf): array
    {
        $children = [];
        foreach ($byId as $candidate) {
            if ($parentOf($candidate) === $parent?->getId()) {
                $children[] = $candidate;
            }
        }

        return $children;
    }

    /**
     * Checks the depth, writes the page and places it at the real index among the siblings.
     *
     * @param list<WikiPage> $siblings
     * @param callable(): WikiPage $create
     * @param array<string, WikiPage> $byId
     * @param callable(WikiPage): ?string $parentOf
     */
    private function insert(?WikiPage $target, array $siblings, int $real, callable $create, array $byId, callable $parentOf, ?WikiPageMoveScope $scope): WikiPageCreateResult
    {
        $level = 1;
        $seen  = [];
        for ($node = $target; $node instanceof WikiPage && !isset($seen[$node->getId()]); $node = ($id = $parentOf($node)) !== null ? $byId[$id] : null) {
            $seen[$node->getId()] = true;
            ++$level;
        }
        if ($level > $this->maxDepth) {
            throw new WikiPageMoveException('too_deep');
        }

        $page = $create();
        $this->place($page, $target, $siblings, $real);
        // PT-L1: the place among what the actor sees, never the sort key that counts hidden siblings.
        $visible = static fn (WikiPage $p): bool => !$scope instanceof WikiPageMoveScope || isset($scope->visibleIds[$p->getId()]);

        return new WikiPageCreateResult($page, count(array_filter(array_slice($siblings, 0, $real), $visible)), count(array_filter($siblings, $visible)) + 1);
    }

    /**
     * The index among the siblings the actor sees (clamped), their count, and the index among all the siblings (a
     * hidden sibling keeps its place: the page goes right before the visible one at the index, or right after the last
     * visible one).
     *
     * @param list<WikiPage> $siblings the siblings in order, the page left out
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function slot(array $siblings, int $index, ?WikiPageMoveScope $scope): array
    {
        // DI-I1: with a scope the index and the answer count the siblings the actor sees; hidden ones keep their place.
        $seen  = $scope instanceof WikiPageMoveScope ? array_values(array_filter($siblings, static fn (WikiPage $p): bool => isset($scope->visibleIds[$p->getId()]))) : $siblings;
        $index = max(0, min($index, count($seen)));
        $real  = match (true) {
            $index < count($seen) => (int) array_search($seen[$index], $siblings, true),
            $seen !== []          => (int) array_search($seen[count($seen) - 1], $siblings, true) + 1,
            default               => count($siblings),
        };

        return [$index, count($seen), $real];
    }

    /**
     * Checks and writes the move inside the space transaction (pages read after the lock).
     *
     * @return array{0: WikiPageMoveResult, 1: WikiPage|null, 2: bool} result, previous parent, whether anything changed
     */
    private function plan(WikiPage $page, ?WikiPage $parent, int $index, ?WikiPageMoveScope $scope): array
    {
        $byId = [];
        foreach ($this->repository->findActiveBySpace($space = $page->getSpace()) as $active) {
            $byId[$active->getId()] = $active;
        }
        $current = $byId[$page->getId()] ?? null;
        if (!$current instanceof WikiPage) {
            throw new WikiPageMoveException('page_missing');
        }
        // DI-I2: the parent the actor may leave was checked before the lock; another move may have changed it since.
        if ($scope instanceof WikiPageMoveScope && $current->getParent()?->getId() !== $scope->fromParentId) {
            throw new WikiPageMoveException('stale');
        }
        $target = null;
        if ($parent instanceof WikiPage) {
            $target = $byId[$parent->getId()] ?? null;
            if (!$target instanceof WikiPage || $target->getSpace()->getId() !== $space->getId()) {
                throw new WikiPageMoveException('parent_missing');
            }
        }

        $parentOf = static fn (WikiPage $p): ?string => isset($byId[$p->getParent()?->getId() ?? '']) && $p->getParent()?->getId() !== $p->getId() ? $p->getParent()?->getId() : null;
        $this->assertNoCycle($current, $target, $byId, $parentOf);
        $this->assertDepth($current, $target, $byId, $parentOf);

        $fromParent = $parentOf($current) !== null ? $byId[$parentOf($current)] : null;
        $targetId   = $target?->getId();
        /** @var list<WikiPage> $siblings */
        $siblings = [];
        foreach ($byId as $candidate) {
            if ($candidate !== $current && $parentOf($candidate) === $targetId) {
                $siblings[] = $candidate;
            }
        }
        [$index, $count, $real] = $this->slot($siblings, $index, $scope);
        $result                 = new WikiPageMoveResult($index, $count + 1, true);

        if ($targetId === $fromParent?->getId() && $this->indexAmongSiblings($current, $byId, $parentOf) === $real) {
            return [new WikiPageMoveResult($index, $count + 1, false), $fromParent, false];
        }

        $this->place($current, $target, $siblings, $real);

        return [$result, $fromParent, true];
    }

    /**
     * @param array<string, WikiPage> $byId
     * @param callable(WikiPage): ?string $parentOf
     */
    private function assertNoCycle(WikiPage $page, ?WikiPage $target, array $byId, callable $parentOf): void
    {
        $seen = [];
        for ($node = $target; $node instanceof WikiPage && !isset($seen[$node->getId()]); $node = ($id = $parentOf($node)) !== null ? $byId[$id] : null) {
            if ($node === $page) {
                throw new WikiPageMoveException('cycle');
            }
            $seen[$node->getId()] = true;
        }
    }

    /**
     * Level of the new parent + levels of the moved sub-tree ≤ max depth.
     *
     * @param array<string, WikiPage> $byId
     * @param callable(WikiPage): ?string $parentOf
     */
    private function assertDepth(WikiPage $page, ?WikiPage $target, array $byId, callable $parentOf): void
    {
        $level = 0;
        $seen  = [];
        for ($node = $target; $node instanceof WikiPage && !isset($seen[$node->getId()]); $node = ($id = $parentOf($node)) !== null ? $byId[$id] : null) {
            $seen[$node->getId()] = true;
            ++$level;
        }

        $children = [];
        foreach ($byId as $candidate) {
            if (($id = $parentOf($candidate)) !== null) {
                $children[$id][] = $candidate;
            }
        }
        $height = 0;
        $queue  = [[$page, 1]];
        $done   = [];
        while ($queue !== []) {
            [$node, $depth] = array_splice($queue, 0, 1)[0];
            if (isset($done[$node->getId()])) {
                continue;
            }
            $done[$node->getId()] = true;
            $height               = max($height, $depth);
            foreach ($children[$node->getId()] ?? [] as $child) {
                $queue[] = [$child, $depth + 1];
            }
        }

        if ($level + $height > $this->maxDepth) {
            throw new WikiPageMoveException('too_deep');
        }
    }

    /**
     * @param array<string, WikiPage> $byId
     * @param callable(WikiPage): ?string $parentOf
     */
    private function indexAmongSiblings(WikiPage $page, array $byId, callable $parentOf): int
    {
        $siblings = [];
        foreach ($byId as $candidate) {
            if ($parentOf($candidate) === $parentOf($page)) {
                $siblings[] = $candidate;
            }
        }

        return (int) array_search($page, $siblings, true);
    }

    /**
     * Writes the page between its new neighbours; renumbers the siblings when there is no gap or they share
     * positions (sorted by title).
     *
     * @param list<WikiPage> $siblings new siblings in order, the page left out
     */
    private function place(WikiPage $page, ?WikiPage $parent, array $siblings, int $index): void
    {
        $strict = true;
        for ($i = 1, $n = count($siblings); $i < $n; ++$i) {
            $strict = $strict && $siblings[$i - 1]->getPosition() < $siblings[$i]->getPosition();
        }
        $prev     = $siblings[$index - 1] ?? null;
        $next     = $siblings[$index] ?? null;
        $position = self::STEP;
        if ($prev instanceof WikiPage && $next instanceof WikiPage) {
            $position = $next->getPosition() - $prev->getPosition() >= 2 ? intdiv($prev->getPosition() + $next->getPosition(), 2) : null;
        } elseif ($prev instanceof WikiPage) {
            $position = $prev->getPosition() + self::STEP;
        } elseif ($next instanceof WikiPage) {
            $position = $next->getPosition() - self::STEP;
        }

        if ($strict && $position !== null && $position > -self::LIMIT && $position < self::LIMIT) {
            $page->moveTo($parent, $position);

            return;
        }

        $ordered = $siblings;
        array_splice($ordered, $index, 0, [$page]);
        foreach (array_values($ordered) as $k => $sibling) {
            $position = ($k + 1) * self::STEP;
            if ($sibling === $page) {
                $page->moveTo($parent, $position);
            } elseif ($sibling->getPosition() !== $position) {
                $sibling->moveTo($sibling->getParent(), $position);
            }
        }
    }

    /**
     * The wiki manager's instance of a page (as given without {@see WikiManagedEntities}); a page the wiki cannot read is
     * refused like one that is gone: nothing is written.
     *
     * @throws WikiPageMoveException with the reason
     */
    private function managedPage(WikiPage $page, string $reason): WikiPage
    {
        try {
            return $this->managed?->page($page) ?? $page;
        } catch (WikiPageNotFoundException) {
            throw new WikiPageMoveException($reason);
        }
    }
}
