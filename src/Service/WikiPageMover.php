<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use DateTimeImmutable;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiPageMovedEvent;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageTreeWriterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

use function array_filter;
use function array_search;
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
 * - 1.35: {@see self::createAt()} creates a page straight at a place (the "New page above / below / as child" of the
 *   tree menu, `position` of the API create) under the same lock and with the same ordering rules.
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
     */
    public function __construct(
        private WikiPageRepositoryInterface $repository,
        private WikiPageTreeWriterInterface $writer,
        private ?EventDispatcherInterface $dispatcher = null,
        private int $maxDepth = 10,
        private ?WikiEventDeferral $deferral = null,
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
        $this->writer->beginSpaceTransaction($page->getSpace());
        try {
            [$result, $fromParent, $moved] = $this->plan($page, $parent, $index, $scope);
            $this->writer->commitSpaceTransaction();
        } catch (Throwable $exception) {
            $this->writer->rollbackSpaceTransaction();

            throw $exception;
        }

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
     * renumbered only when the neighbours leave no gap. No `nowo_wiki.page.moved` event: the page is new, the page
     * service raises its saved and created events (held until the commit when a {@see WikiEventDeferral} is wired).
     *
     * @param WikiSpace $space space of the new page
     * @param WikiPage|null $parent parent of the new page (null: a root page); must be active and of the space
     * @param int $index 0-based index among the parent's children, clamped (with a scope: among those the actor may read)
     * @param callable(): WikiPage $create writes the page (the page service), called after the lock and the checks
     * @param WikiPageMoveScope|null $scope what the actor sees (null: every page); its parent id is not used
     *
     * @throws WikiPageMoveException `parent_missing` when the parent is gone, archived or of another space, `too_deep`
     *                               when the new page would be deeper than `tree.max_depth`; nothing is written
     *
     * @return WikiPage the page $create returned, placed
     */
    public function createAt(WikiSpace $space, ?WikiPage $parent, int $index, callable $create, ?WikiPageMoveScope $scope = null): WikiPage
    {
        $work = function () use ($space, $parent, $index, $create, $scope): WikiPage {
            $this->writer->beginSpaceTransaction($space);
            try {
                $page = $this->insert($space, $parent, $index, $create, $scope);
                $this->writer->commitSpaceTransaction();
            } catch (Throwable $exception) {
                $this->writer->rollbackSpaceTransaction();

                throw $exception;
            }

            return $page;
        };

        // The page service's events wait for the commit (and are dropped on a rollback) when both share the deferral.
        return $this->deferral instanceof WikiEventDeferral ? $this->deferral->run($work) : $work();
    }

    /**
     * Checks the parent, writes the page and places it (inside the space transaction).
     *
     * @param callable(): WikiPage $create
     */
    private function insert(WikiSpace $space, ?WikiPage $parent, int $index, callable $create, ?WikiPageMoveScope $scope): WikiPage
    {
        $byId = [];
        foreach ($this->repository->findActiveBySpace($space) as $active) {
            $byId[$active->getId()] = $active;
        }
        $target = null;
        if ($parent instanceof WikiPage) {
            $target = $byId[$parent->getId()] ?? null;
            if (!$target instanceof WikiPage || $target->getSpace()->getId() !== $space->getId()) {
                throw new WikiPageMoveException('parent_missing');
            }
        }
        $parentOf = static fn (WikiPage $p): ?string => isset($byId[$p->getParent()?->getId() ?? '']) && $p->getParent()?->getId() !== $p->getId() ? $p->getParent()?->getId() : null;
        $level    = 1;
        $seen     = [];
        for ($node = $target; $node instanceof WikiPage && !isset($seen[$node->getId()]); $node = ($id = $parentOf($node)) !== null ? $byId[$id] : null) {
            $seen[$node->getId()] = true;
            ++$level;
        }
        if ($level > $this->maxDepth) {
            throw new WikiPageMoveException('too_deep');
        }

        $targetId = $target?->getId();
        /** @var list<WikiPage> $siblings */
        $siblings = [];
        foreach ($byId as $candidate) {
            if ($parentOf($candidate) === $targetId) {
                $siblings[] = $candidate;
            }
        }
        [, , $real] = $this->slot($siblings, $index, $scope);

        $page = $create();
        $this->place($page, $target, $siblings, $real);

        return $page;
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
}
