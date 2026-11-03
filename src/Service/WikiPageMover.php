<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use DateTimeImmutable;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiPageMovedEvent;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageTreeWriterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

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
     */
    public function __construct(
        private WikiPageRepositoryInterface $repository,
        private WikiPageTreeWriterInterface $writer,
        private ?EventDispatcherInterface $dispatcher = null,
        private int $maxDepth = 10,
    ) {
    }

    /**
     * Moves the page under the parent (null: the root) at the index (clamped) among the parent's other children.
     *
     * @param int $index 0-based index among the new siblings, the page left out
     * @param object|null $actor user who moves it (in the event)
     *
     * @throws WikiPageMoveException when the page or the parent is gone, or the move would build a cycle or go too deep
     */
    public function move(WikiPage $page, ?WikiPage $parent, int $index, ?object $actor = null): WikiPageMoveResult
    {
        $this->writer->beginSpaceTransaction($page->getSpace());
        try {
            [$result, $fromParent, $moved] = $this->plan($page, $parent, $index);
            $this->writer->commitSpaceTransaction();
        } catch (Throwable $exception) {
            $this->writer->rollbackSpaceTransaction();

            throw $exception;
        }

        if ($moved) {
            $this->dispatcher?->dispatch(new WikiPageMovedEvent($page, $fromParent, $page->getParent(), $result->index, $actor, new DateTimeImmutable()), WikiEvents::PAGE_MOVED);
        }

        return $result;
    }

    /**
     * Checks and writes the move inside the space transaction (pages read after the lock).
     *
     * @return array{0: WikiPageMoveResult, 1: WikiPage|null, 2: bool} result, previous parent, whether anything changed
     */
    private function plan(WikiPage $page, ?WikiPage $parent, int $index): array
    {
        $byId = [];
        foreach ($this->repository->findActiveBySpace($space = $page->getSpace()) as $active) {
            $byId[$active->getId()] = $active;
        }
        $current = $byId[$page->getId()] ?? null;
        if (!$current instanceof WikiPage) {
            throw new WikiPageMoveException('page_missing');
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
        $index = max(0, min($index, count($siblings)));

        if ($targetId === $fromParent?->getId() && $this->indexAmongSiblings($current, $byId, $parentOf) === $index) {
            return [new WikiPageMoveResult($index, count($siblings) + 1, false), $fromParent, false];
        }

        $this->place($current, $target, $siblings, $index);

        return [new WikiPageMoveResult($index, count($siblings) + 1, true), $fromParent, true];
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
