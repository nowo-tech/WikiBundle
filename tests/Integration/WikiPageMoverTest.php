<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiPageMovedEvent;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Service\WikiPageMoveException;
use Nowo\WikiBundle\Service\WikiPageMover;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function array_map;

/**
 * 1.14 (T049): moves in the page tree — positions, cycles, depth, missing pages, the space lock and the event.
 */
final class WikiPageMoverTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    private DoctrineOrmWikiPageRepository $repository;

    /** @var list<array{0: WikiPageMovedEvent, 1: string}> */
    private array $events = [];

    protected function setUp(): void
    {
        $this->em         = SqliteWikiEntityManager::create();
        $this->space      = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $this->repository = new DoctrineOrmWikiPageRepository($this->em);
        $this->em->persist($this->space);
        $this->em->flush();
    }

    public function testReordersAmongSiblingsCreatedBefore114ByRenumberingThemOnce(): void
    {
        [$a, $b, $c] = [$this->page('a'), $this->page('b'), $this->page('c')];

        $result = $this->mover()->move($c, null, 0, new TestUser());

        self::assertSame([0, 3, true], [$result->index, $result->count, $result->moved]);
        self::assertSame(['c', 'a', 'b'], $this->order(null));
        self::assertSame([1024, 2048, 3072], array_map(static fn (WikiPage $p): int => $p->getPosition(), [$c, $a, $b]));

        // The siblings now leave gaps: the next move writes one row (the midpoint).
        $this->mover()->move($b, null, 1);
        self::assertSame(['c', 'b', 'a'], $this->order(null));
        self::assertSame(1536, $b->getPosition());
        self::assertSame(2048, $a->getPosition());

        // First and last places step away from the neighbour.
        $this->mover()->move($a, null, 0);
        self::assertSame(1024 - 1024, $a->getPosition());
        $this->mover()->move($a, null, 99);
        self::assertSame(['c', 'b', 'a'], $this->order(null));
        self::assertSame(1536 + 1024, $a->getPosition());

        self::assertCount(4, $this->events);
        self::assertSame(WikiEvents::PAGE_MOVED, $this->events[0][1]);
        self::assertSame($c, $this->events[0][0]->page);
        self::assertNull($this->events[0][0]->fromParent);
        self::assertNull($this->events[0][0]->toParent);
        self::assertInstanceOf(TestUser::class, $this->events[0][0]->actor);
    }

    public function testMovesUnderAnotherParentAndBackToTheRoot(): void
    {
        $ops   = $this->page('ops');
        $guide = $this->page('guide');
        $run   = $this->page('run', $ops);

        $result = $this->mover()->move($guide, $ops, 5);
        self::assertSame([1, 2], [$result->index, $result->count]);
        self::assertSame($ops, $guide->getParent());
        self::assertSame(['run', 'guide'], $this->order($ops));
        self::assertSame($ops, $this->events[0][0]->toParent);

        $this->mover()->move($run, null, 1);
        self::assertNull($run->getParent());
        self::assertSame(['ops', 'run'], $this->order(null));
        self::assertSame($ops, $this->events[1][0]->fromParent);

        // Into an empty parent: the first position.
        $this->mover()->move($ops, $run, 0);
        self::assertSame(1024, $ops->getPosition());
    }

    public function testNothingIsWrittenWhenThePageIsAlreadyThere(): void
    {
        $a = $this->page('a');
        $this->page('b');

        $result = $this->mover()->move($a, null, 0);

        self::assertFalse($result->moved);
        self::assertSame(0, $a->getPosition());
        self::assertSame([], $this->events);
    }

    public function testAPageUnderAnArchivedParentCountsAsARootPage(): void
    {
        $gone   = $this->page('gone');
        $orphan = $this->page('orphan', $gone);
        $root   = $this->page('root');
        $gone->archive();
        $this->em->flush();

        $this->mover()->move($root, null, 0);

        self::assertSame(['root', 'orphan'], $this->order(null, true));
        self::assertSame($gone, $orphan->getParent());
    }

    public function testRenumbersWhenTheNeighboursLeaveNoGapOrTheLimitIsReached(): void
    {
        $a = $this->page('a', null, 10);
        $b = $this->page('b', null, 11);
        $c = $this->page('c', null, WikiPageMover::LIMIT - 1);

        $this->mover()->move($c, null, 1);
        self::assertSame(['a', 'c', 'b'], $this->order(null));
        self::assertSame([1024, 2048, 3072], [$a->getPosition(), $c->getPosition(), $b->getPosition()]);

        $d = $this->page('d', null, WikiPageMover::LIMIT - 512);
        $this->mover()->move($a, null, 3);
        self::assertSame(['c', 'b', 'd', 'a'], $this->order(null));
        self::assertSame(4096, $a->getPosition());
        self::assertSame(3072, $d->getPosition());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function refusals(): iterable
    {
        yield 'into itself' => ['self'];
        yield 'into a descendant' => ['descendant'];
        yield 'too deep' => ['deep'];
        yield 'archived page' => ['archived'];
        yield 'archived parent' => ['archived_parent'];
        yield 'parent of another space' => ['other_space'];
    }

    #[DataProvider('refusals')]
    public function testRefusedMovesChangeNothing(string $case): void
    {
        $top   = $this->page('top');
        $mid   = $this->page('mid', $top);
        $leaf  = $this->page('leaf', $mid);
        $other = $this->page('other');

        [$page, $parent, $reason] = match ($case) {
            'self'            => [$top, $top, 'cycle'],
            'descendant'      => [$top, $leaf, 'cycle'],
            'deep'            => [$top, $other, 'too_deep'],
            'archived'        => [$this->archived($other), null, 'page_missing'],
            'archived_parent' => [$top, $this->archived($other), 'parent_missing'],
            default           => [$top, $this->foreignPage(), 'parent_missing'],
        };

        try {
            $this->mover(3)->move($page, $parent, 0);
            self::fail('The move was not refused.');
        } catch (WikiPageMoveException $e) {
            self::assertSame($reason, $e->reason);
            self::assertSame('Page move refused: ' . $reason . '.', $e->getMessage());
        }

        self::assertNull($top->getParent());
        self::assertSame($mid, $leaf->getParent());
        self::assertSame([], $this->events);
    }

    public function testARefusedMoveRollsTheTransactionBack(): void
    {
        $page = $this->page('a');

        try {
            $this->mover()->move($page, $page, 0);
            self::fail('The move was not refused.');
        } catch (WikiPageMoveException) {
            self::assertFalse($this->em->getConnection()->isTransactionActive());
        }
    }

    public function testTheTransactionIsRolledBackWhenTheLockFails(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('beginTransaction');
        $em->method('createQueryBuilder')->willThrowException(new RuntimeException('lock'));
        $em->expects(self::once())->method('rollback');

        $this->expectExceptionMessage('lock');
        (new DoctrineOrmWikiPageRepository($em))->beginSpaceTransaction($this->space);
    }

    public function testAPageInACorruptParentCycleCanBeMovedOut(): void
    {
        $a = $this->page('a');
        $b = $this->page('b', $a);
        $a->moveTo($b, 0);
        $this->em->flush();

        $this->mover()->move($a, null, 0);

        self::assertNull($a->getParent());
        self::assertSame($a, $b->getParent());
    }

    public function testWorksWithoutDispatcher(): void
    {
        $a = $this->page('a');
        $b = $this->page('b');

        (new WikiPageMover($this->repository, $this->repository))->move($b, $a, 0);

        self::assertSame($a, $b->getParent());
    }

    private function mover(int $maxDepth = 10): WikiPageMover
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WikiEvents::PAGE_MOVED, function (WikiPageMovedEvent $event, string $name): void {
            $this->events[] = [$event, $name];
        });

        return new WikiPageMover($this->repository, $this->repository, $dispatcher, $maxDepth);
    }

    private function page(string $slug, ?WikiPage $parent = null, int $position = 0): WikiPage
    {
        $page = new WikiPage($this->space, $slug, $slug, $parent);
        $page->setPosition($position);
        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }

    private function archived(WikiPage $page): WikiPage
    {
        $page->archive();
        $this->em->flush();

        return $page;
    }

    private function foreignPage(): WikiPage
    {
        $space = new WikiSpace('ops', 'Ops', WikiSpaceOwnerScope::Team, 't');
        $page  = new WikiPage($space, 'x', 'X');
        $this->em->persist($space);
        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }

    /**
     * Slugs of the active children of a parent (null: the root, with $orphans the pages whose parent is archived).
     *
     * @return list<string>
     */
    private function order(?WikiPage $parent, bool $orphans = false): array
    {
        $slugs = [];
        foreach ($this->repository->findActiveBySpace($this->space) as $page) {
            $p = $page->getParent();
            if ($p === $parent || ($orphans && !$parent instanceof WikiPage && $p?->isArchived() === true)) {
                $slugs[] = $page->getSlug();
            }
        }

        return $slugs;
    }
}
