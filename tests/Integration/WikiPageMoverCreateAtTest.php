<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use Doctrine\ORM\EntityManager;
use LogicException;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageTreeWriterInterface;
use Nowo\WikiBundle\Service\WikiEventDeferral;
use Nowo\WikiBundle\Service\WikiPageMoveException;
use Nowo\WikiBundle\Service\WikiPageMover;
use Nowo\WikiBundle\Service\WikiPageMoveScope;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function array_map;

/**
 * 1.35: {@see WikiPageMover::createAt()} — a new page created straight at a place (above, below, as child, first,
 * last), under the space lock, with the ordering of the moves; concurrent inserts never share a position.
 */
final class WikiPageMoverCreateAtTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    private DoctrineOrmWikiPageRepository $repository;

    /** @var list<string> */
    private array $events = [];

    protected function setUp(): void
    {
        $this->em         = SqliteWikiEntityManager::create();
        $this->space      = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $this->repository = new DoctrineOrmWikiPageRepository($this->em);
        $this->em->persist($this->space);
        $this->em->flush();
    }

    /**
     * @return iterable<string, array{0: string, 1: int, 2: list<string>}>
     */
    public static function places(): iterable
    {
        // Siblings a, b, c at 1024, 2048, 3072; the 1-based position of the UI is index + 1.
        yield 'first (above a)' => ['root', 0, ['new', 'a', 'b', 'c']];
        yield 'above b' => ['root', 1, ['a', 'new', 'b', 'c']];
        yield 'below b' => ['root', 2, ['a', 'b', 'new', 'c']];
        yield 'last (below c)' => ['root', 3, ['a', 'b', 'c', 'new']];
        yield 'past the last' => ['root', 99, ['a', 'b', 'c', 'new']];
        yield 'negative index: first' => ['root', -5, ['new', 'a', 'b', 'c']];
        yield 'child of a parent without children' => ['b', 0, ['new']];
    }

    #[DataProvider('places')]
    public function testCreatesAtThePlace(string $under, int $index, array $expected): void
    {
        $a = $this->page('a', null, 1024);
        $b = $this->page('b', null, 2048);
        $this->page('c', null, 3072);
        $parent = $under === 'b' ? $b : null;

        $page = $this->mover()->createAt($this->space, $parent, $index, fn (): WikiPage => $this->page('new', $parent));

        self::assertSame($parent, $page->getParent());
        self::assertSame($expected, $this->order($parent));
        // Sparse positions: one row written when the neighbours leave a gap.
        self::assertSame(1024, $a->getPosition());
        self::assertSame([], $this->events);
    }

    public function testAChildGoesLastAmongTheVisibleChildrenAndHiddenOnesKeepTheirPlace(): void
    {
        $ops    = $this->page('ops');
        $run    = $this->page('run', $ops, 1024);
        $hidden = $this->page('hidden', $ops, 2048);
        $seen   = [$ops->getId() => true, $run->getId() => true];

        // Position 2 of the visible children (after "run") = right after it, before the hidden page.
        $this->mover()->createAt($this->space, $ops, 1, fn (): WikiPage => $this->page('new', $ops), new WikiPageMoveScope($seen, null));
        self::assertSame(['run', 'new', 'hidden'], $this->order($ops));
        self::assertSame(2048, $hidden->getPosition());

        // Only hidden children: the end.
        $only = $this->page('only');
        $this->page('h2', $only, 1024);
        $this->mover()->createAt($this->space, $only, 0, fn (): WikiPage => $this->page('new2', $only), new WikiPageMoveScope($seen + [$only->getId() => true], null));
        self::assertSame(['h2', 'new2'], $this->order($only));
    }

    public function testSiblingsCreatedBefore114AreRenumberedOnce(): void
    {
        [$a, $b] = [$this->page('a'), $this->page('b')];

        $page = $this->mover()->createAt($this->space, null, 1, fn (): WikiPage => $this->page('new'));

        self::assertSame(['a', 'new', 'b'], $this->order(null));
        self::assertSame([1024, 2048, 3072], array_map(static fn (WikiPage $p): int => $p->getPosition(), [$a, $page, $b]));
    }

    public function testConcurrentInsertsAtOnePlaceNeverSharePositions(): void
    {
        $this->page('a', null, 1024);
        $this->page('b', null, 1025);

        // Two inserts asked for the same place (after a): the first finds no gap and renumbers the siblings; the second
        // reads the first one's row after the lock and takes the midpoint, so the positions stay distinct and ordered.
        $first  = $this->mover()->createAt($this->space, null, 1, fn (): WikiPage => $this->page('x'));
        $second = $this->mover()->createAt($this->space, null, 1, fn (): WikiPage => $this->page('y'));

        self::assertSame(['a', 'y', 'x', 'b'], $this->order(null));
        $positions = array_map(static fn (WikiPage $p): int => $p->getPosition(), $this->repository->findActiveBySpace($this->space));
        self::assertSame([1024, 1536, 2048, 3072], $positions);
        self::assertNotSame($first->getPosition(), $second->getPosition());
    }

    public function testTheLockIsTakenBeforeTheSiblingsAreReadAndThePageIsWritten(): void
    {
        $log    = [];
        $writer = new class($log) implements WikiPageTreeWriterInterface {
            /** @param list<string> $log */
            public function __construct(private array &$log)
            {
            }

            public function beginSpaceTransaction(WikiSpace $space): void
            {
                $this->log[] = 'lock';
            }

            public function commitSpaceTransaction(): void
            {
                $this->log[] = 'commit';
            }

            public function rollbackSpaceTransaction(): void
            {
                $this->log[] = 'rollback';
            }
        };
        $repository = $this->createStub(WikiPageRepositoryInterface::class);
        $repository->method('findActiveBySpace')->willReturnCallback(static function () use (&$log): array {
            $log[] = 'read';

            return [];
        });

        (new WikiPageMover($repository, $writer))->createAt($this->space, null, 0, function () use (&$log): WikiPage {
            $log[] = 'create';

            return new WikiPage($this->space, 'n', 'n');
        });
        self::assertSame(['lock', 'read', 'create', 'commit'], $log);

        $log = [];
        try {
            (new WikiPageMover($repository, $writer))->createAt($this->space, null, 0, static fn (): WikiPage => throw new LogicException('slug taken'));
            self::fail('The create did not throw.');
        } catch (LogicException) {
            self::assertSame(['lock', 'read', 'rollback'], $log);
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function refusals(): iterable
    {
        yield 'archived parent' => ['archived', 'parent_missing'];
        yield 'parent of another space' => ['other_space', 'parent_missing'];
        yield 'too deep' => ['deep', 'too_deep'];
    }

    #[DataProvider('refusals')]
    public function testRefusalsWriteNothing(string $case, string $reason): void
    {
        $top  = $this->page('top');
        $mid  = $this->page('mid', $top);
        $leaf = $this->page('leaf', $mid);

        $parent = match ($case) {
            'archived'    => $this->archived($leaf),
            'other_space' => $this->foreignPage(),
            default       => $leaf,
        };
        $created = false;

        try {
            $this->mover(3)->createAt($this->space, $parent, 0, function () use (&$created): WikiPage {
                $created = true;

                return $this->page('new');
            });
            self::fail('The create was not refused.');
        } catch (WikiPageMoveException $e) {
            self::assertSame($reason, $e->reason);
        }

        self::assertFalse($created);
        self::assertFalse($this->em->getConnection()->isTransactionActive());
        self::assertNull($this->repository->findBySlug($this->space, 'new'));
    }

    public function testAtTheDepthLimitAChildFitsOneLevelAbove(): void
    {
        $top = $this->page('top');
        $mid = $this->page('mid', $top);

        $page = $this->mover(3)->createAt($this->space, $mid, 0, fn (): WikiPage => $this->page('leaf', $mid));

        self::assertSame($mid, $page->getParent());
    }

    public function testThePageServiceEventsWaitForTheCommit(): void
    {
        $deferral   = new WikiEventDeferral();
        $dispatcher = new EventDispatcher();
        $heard      = [];
        $dispatcher->addListener(WikiEvents::PAGE_CREATED, function () use (&$heard): void {
            $heard[] = $this->em->getConnection()->isTransactionActive() ? 'inside' : 'after';
        });
        $mover = new WikiPageMover($this->repository, $this->repository, $dispatcher, 10, $deferral);

        $mover->createAt($this->space, null, 0, function () use ($deferral, $dispatcher): WikiPage {
            $page = $this->page('new');
            $deferral->dispatch($dispatcher, new stdClass(), WikiEvents::PAGE_CREATED);

            return $page;
        });

        self::assertSame(['after'], $heard);
    }

    private function mover(int $maxDepth = 10): WikiPageMover
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WikiEvents::PAGE_MOVED, function (): void {
            $this->events[] = WikiEvents::PAGE_MOVED;
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
     * @return list<string>
     */
    private function order(?WikiPage $parent): array
    {
        $slugs = [];
        foreach ($this->repository->findActiveBySpace($this->space) as $page) {
            if ($page->getParent() === $parent) {
                $slugs[] = $page->getSlug();
            }
        }

        return $slugs;
    }
}
