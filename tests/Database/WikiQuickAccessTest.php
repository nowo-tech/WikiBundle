<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\QuickAccess\WikiFavouriteResult;
use Nowo\WikiBundle\QuickAccess\WikiQuickAccess;
use Nowo\WikiBundle\QuickAccess\WikiQuickAccessStore;
use Nowo\WikiBundle\QuickAccess\WikiReadableSpaces;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_map;
use function in_array;

/**
 * 1.45: favourites and recents ({@see WikiQuickAccessStore}, {@see WikiQuickAccess}) on SQLite and — through
 * WIKI_TEST_DATABASE_URL — MySQL 8.4, MariaDB 11.4 and PostgreSQL 16: the per-person rows, the per-viewer filter before
 * the cut (tenant, space, live page, page and ancestors), the bound of the recents, the unique-key race as a domain
 * result, the lock conflict of a visit (logged, or propagated inside a caller's transaction) and the erasure.
 */
final class WikiQuickAccessTest extends TestCase
{
    private EntityManager $em;

    private WikiTransactionRunner $runner;

    private TestUser $viewer;

    private WikiSpace $eng;

    private WikiSpace $ops;

    private WikiSpace $other;

    /** @var list<string> page slugs the checker hides */
    private array $hidden = [];

    /** @var list<string> space slugs the resolver lists */
    private array $listed = ['eng', 'ops'];

    protected function setUp(): void
    {
        $this->em     = WikiTestDatabase::create();
        $this->runner = new WikiTransactionRunner($this->em, backoffBaseMilliseconds: 0);
        $this->viewer = new TestUser('u-1');
        $this->eng    = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->ops    = new WikiSpace('ops', 'Operations', WikiSpaceOwnerScope::Team, 'team-2');
        $this->other  = new WikiSpace('other', 'Other tenant', WikiSpaceOwnerScope::Team, 'team-9');
        foreach ([$this->eng, $this->ops, $this->other] as $space) {
            $this->em->persist($space);
        }
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
    }

    public function testFavouritesAreAddedOnceRemovedAndBounded(): void
    {
        $store = new WikiQuickAccessStore($this->runner);
        $a     = $this->page($this->eng, 'a');
        $b     = $this->page($this->eng, 'b');

        self::assertSame(WikiFavouriteResult::Added, $store->addFavourite($a, 'u-1', 2));
        self::assertSame(WikiFavouriteResult::Unchanged, $store->addFavourite($a, 'u-1', 2));
        self::assertTrue($store->isFavourite($a, 'u-1'));
        self::assertFalse($store->isFavourite($a, 'u-2'));
        self::assertSame(WikiFavouriteResult::Added, $store->addFavourite($b, 'u-1', 2));
        self::assertSame(WikiFavouriteResult::LimitReached, $store->addFavourite($this->page($this->eng, 'c'), 'u-1', 2));
        // Another person's list is their own.
        self::assertSame(WikiFavouriteResult::Added, $store->addFavourite($a, 'u-2', 2));

        self::assertSame(WikiFavouriteResult::Removed, $store->removeFavourite($a, 'u-1'));
        self::assertSame(WikiFavouriteResult::Unchanged, $store->removeFavourite($a, 'u-1'));
        self::assertFalse($store->isFavourite($a, 'u-1'));
        self::assertTrue($store->isFavourite($a, 'u-2'));
        self::assertSame(1, $this->rows('wiki_page_favourites', 'u-1'));
    }

    public function testListsAreFilteredForTheViewerBeforeTheyAreCut(): void
    {
        $store   = new WikiQuickAccessStore($this->runner);
        $parent  = $this->page($this->eng, 'hidden-parent');
        $child   = $this->page($this->eng, 'child', $parent);
        $ops     = $this->page($this->ops, 'ops-page');
        $foreign = $this->page($this->other, 'foreign');
        $gone    = $this->page($this->eng, 'gone');
        $visible = $this->page($this->eng, 'visible');
        $older   = $this->page($this->eng, 'older');
        $time    = new DateTimeImmutable('2026-11-19 10:00:00');
        foreach ([$older, $visible, $gone, $foreign, $ops, $child] as $i => $page) {
            $store->addFavourite($page, 'u-1', 100, $time->modify('+' . $i . ' minutes'));
        }
        $gone->archive();
        $this->em->flush();
        $this->hidden = ['hidden-parent'];

        // Newest first; the archived page, the other tenant's page and the page under a hidden parent are left out
        // before the cut to `shown`, so two readable pages are still listed.
        self::assertSame(['ops-page', 'visible'], $this->slugs($this->quick(shown: 2)->favourites($this->viewer)));
        self::assertSame(['ops-page', 'visible', 'older'], $this->slugs($this->quick()->favourites($this->viewer)));

        // A space the viewer lost: its pages go, the rows stay (the page shows again with access).
        $this->listed = ['eng'];
        self::assertSame(['visible', 'older'], $this->slugs($this->quick()->favourites($this->viewer)));
        $this->listed = ['eng', 'ops'];
        self::assertSame(['ops-page', 'visible', 'older'], $this->slugs($this->quick()->favourites($this->viewer)));
        self::assertSame(6, $this->rows('wiki_page_favourites', 'u-1'));

        // Without the role gate, without a user id, or with favourites off: nothing.
        self::assertSame([], $this->quick(canList: false)->favourites($this->viewer));
        self::assertSame([], $this->quick()->favourites($this->anonymous()));
        self::assertSame([], $this->quick(favourites: false)->favourites($this->viewer));
        self::assertFalse($this->quick(favourites: false)->isFavourite($this->viewer, $visible));
        self::assertFalse($this->quick()->isFavourite($this->anonymous(), $visible));
        self::assertTrue($this->quick()->isFavourite($this->viewer, $visible));
        self::assertTrue($this->quick()->canKeepFavourites($this->viewer));
        self::assertFalse($this->quick()->canKeepFavourites($this->anonymous()));
        self::assertTrue($this->quick()->favouritesEnabled());
        self::assertTrue($this->quick()->recentsEnabled());
    }

    public function testSetFavouriteAddsAndRemovesForTheViewer(): void
    {
        $page  = $this->page($this->eng, 'a');
        $quick = $this->quick();

        self::assertSame(WikiFavouriteResult::Added, $quick->setFavourite($this->viewer, $page, true));
        self::assertSame(WikiFavouriteResult::Removed, $quick->setFavourite($this->viewer, $page, false));
        self::assertSame(WikiFavouriteResult::Unchanged, $quick->setFavourite($this->anonymous(), $page, true));
        self::assertSame(WikiFavouriteResult::Unchanged, $this->quick(favourites: false)->setFavourite($this->viewer, $page, true));
        self::assertSame(0, $this->rows('wiki_page_favourites', 'u-1'));
    }

    public function testRecentsAreTheLastVisitsBoundedAndWithoutTheCurrentPage(): void
    {
        $store = new WikiQuickAccessStore($this->runner);
        $pages = [];
        foreach (['p1', 'p2', 'p3', 'p4'] as $i => $slug) {
            $pages[$slug] = $this->page($this->eng, $slug);
            $store->recordVisit($pages[$slug], 'u-1', 3, new DateTimeImmutable('2026-11-19 10:0' . $i . ':00'));
        }
        // Visiting p2 again moves it to the top; p1 was pruned (keep: 3).
        $store->recordVisit($pages['p2'], 'u-1', 3, new DateTimeImmutable('2026-11-19 11:00:00'));
        self::assertSame(3, $this->rows('wiki_page_visits', 'u-1'));
        self::assertSame(['p2', 'p4', 'p3'], $this->slugs($store->recentPages('u-1', [$this->eng], 10)));
        self::assertSame([], $store->recentPages('u-1', [], 10));
        self::assertSame([], $store->recentPages('', [$this->eng], 10));
        self::assertSame([], $store->recentPages('u-1', [$this->eng], 0));

        $quick = $this->quick(shown: 2, keep: 3);
        self::assertSame(['p4', 'p3'], $this->slugs($quick->recents($this->viewer, $pages['p2'])));
        self::assertSame([], $this->quick(recents: false)->recents($this->viewer));
        self::assertSame([], $quick->recents($this->anonymous()));

        // Through the service: recorded now, first in the list.
        $quick->recordVisit($this->viewer, $pages['p1']);
        self::assertSame(['p1', 'p2'], $this->slugs($quick->recents($this->viewer)));
        $this->quick(recents: false)->recordVisit($this->viewer, $pages['p3']);
        $quick->recordVisit($this->anonymous(), $pages['p3']);
        self::assertSame(['p1', 'p2'], $this->slugs($quick->recents($this->viewer)));
    }

    public function testErasureDeletesAPersonsRows(): void
    {
        $store = new WikiQuickAccessStore($this->runner);
        $page  = $this->page($this->eng, 'a');
        $store->addFavourite($page, 'u-1', 10);
        $store->addFavourite($page, 'u-2', 10);
        $store->recordVisit($page, 'u-1', 10);

        self::assertSame(0, $store->eraseUser('u-1', false, false));
        self::assertSame(1, $store->eraseUser('u-1', true, false));
        self::assertSame(1, $store->eraseUser('u-1', false, true));
        self::assertSame(0, $this->rows('wiki_page_favourites', 'u-1') + $this->rows('wiki_page_visits', 'u-1'));
        self::assertSame(1, $this->rows('wiki_page_favourites', 'u-2'));
    }

    /** The "only one row" race of the unique key, simulated on SQLite by a trigger: the outcome asked for holds. */
    public function testAUniqueViolationIsTheExpectedRaceNotAConflict(): void
    {
        if (WikiTestDatabase::isServer()) {
            self::markTestSkipped('SQLite simulation; the servers run the real two-connection race below.');
        }
        $store = new WikiQuickAccessStore($this->runner);
        $page  = $this->page($this->eng, 'a');
        $this->em->getConnection()->executeStatement("CREATE TRIGGER wk_fav_race BEFORE INSERT ON wiki_page_favourites BEGIN SELECT RAISE(ABORT, 'UNIQUE constraint failed: wiki_page_favourites.user_id, wiki_page_favourites.page_id'); END");
        $this->em->getConnection()->executeStatement("CREATE TRIGGER wk_visit_race BEFORE INSERT ON wiki_page_visits BEGIN SELECT RAISE(ABORT, 'UNIQUE constraint failed: wiki_page_visits.user_id, wiki_page_visits.page_id'); END");

        self::assertSame(WikiFavouriteResult::Unchanged, $store->addFavourite($page, 'u-1', 10));
        $store->recordVisit($page, 'u-1', 10);
        self::assertSame(0, $this->rows('wiki_page_visits', 'u-1'));
        self::assertTrue($this->em->isOpen());
    }

    /** A lock conflict of a visit (simulated on SQLite): logged and skipped; inside a caller's transaction it propagates. */
    public function testALockConflictOfAVisitIsLoggedOrPropagatedWhenNested(): void
    {
        if (WikiTestDatabase::isServer()) {
            self::markTestSkipped('SQLite simulation; the servers run the real lock conflict below.');
        }
        $page = $this->page($this->eng, 'a');
        $this->em->getConnection()->executeStatement("CREATE TRIGGER wk_visit_locked BEFORE INSERT ON wiki_page_visits BEGIN SELECT RAISE(ABORT, 'database is locked'); END");
        $logger = new RecordingLogger();
        $quick  = $this->quick(logger: $logger);

        $quick->recordVisit($this->viewer, $page);
        self::assertStringContainsString('page visit was not recorded', $logger->lines()[0] ?? '');

        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $quick->recordVisit($this->viewer, $page);
            self::fail('Expected the lock conflict.');
        } catch (TransientStorageFailure) {
            self::assertTrue($connection->isTransactionActive());
        } finally {
            $connection->rollBack();
        }
    }

    /** MySQL / MariaDB / PostgreSQL: user ids are compared exactly (a look-alike id never matches). */
    public function testUserIdsAreExact(): void
    {
        $store = new WikiQuickAccessStore($this->runner);
        $page  = $this->page($this->eng, 'a');
        $store->addFavourite($page, 'alice', 10);

        self::assertFalse($store->isFavourite($page, 'Alice'));
        self::assertFalse($store->isFavourite($page, 'alice '));
        self::assertSame([], $store->favouritePages('ALICE', [$this->eng], 10));
        self::assertSame(WikiFavouriteResult::Added, $store->addFavourite($page, 'Alice', 10));
        self::assertSame(['a'], $this->slugs($store->favouritePages('alice', [$this->eng], 10)));
    }

    private function quick(bool $favourites = true, bool $recents = true, int $shown = 10, int $keep = 20, bool $canList = true, ?RecordingLogger $logger = null): WikiQuickAccess
    {
        $roles = $this->createStub(WikiAccessCheckerInterface::class);
        $roles->method('canList')->willReturn($canList);
        $resolver = $this->createStub(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturnCallback(fn (): array => array_values(array_filter([$this->eng, $this->ops, $this->other], fn (WikiSpace $space): bool => in_array($space->getSlug(), $this->listed, true))));
        $checker = new CallbackSpaceAccessChecker(fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => $action === WikiAction::View && (!$page instanceof WikiPage || !in_array($page->getSlug(), $this->hidden, true)));

        return new WikiQuickAccess(
            new WikiQuickAccessStore($this->runner),
            new WikiReadableSpaces($roles, $resolver, $checker),
            ['enabled' => $favourites, 'max' => 100, 'shown' => $shown],
            ['enabled' => $recents, 'keep' => $keep, 'shown' => $shown],
            $logger,
        );
    }

    private function page(WikiSpace $space, string $slug, ?WikiPage $parent = null): WikiPage
    {
        $page = new WikiPage($space, $slug, $slug, $parent);
        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }

    private function rows(string $table, string $userId): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . $table . ' WHERE user_id = ?', [$userId]);
    }

    private function anonymous(): UserInterface
    {
        return new TestUserEntity('');
    }

    /**
     * @param list<WikiPage> $pages
     *
     * @return list<string>
     */
    private function slugs(array $pages): array
    {
        return array_map(static fn (WikiPage $page): string => $page->getSlug(), $pages);
    }
}
