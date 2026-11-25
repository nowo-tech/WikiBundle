<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageFavourite;
use Nowo\WikiBundle\Entity\WikiPageVisit;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\LinkPicker\WikiLinkTenantResolverInterface;
use Nowo\WikiBundle\Port\Internal\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Port\Internal\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\QuickAccess\WikiFavouriteResult;
use Nowo\WikiBundle\QuickAccess\WikiQuickAccess;
use Nowo\WikiBundle\QuickAccess\WikiQuickAccessStore;
use Nowo\WikiBundle\QuickAccess\WikiReadableSpaces;
use Nowo\WikiBundle\QuickAccess\WikiViewerScope;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function in_array;
use function sprintf;
use function str_repeat;
use function substr;

/**
 * 1.45: favourites and recents ({@see WikiQuickAccessStore}, {@see WikiQuickAccess}) on SQLite and — through
 * WIKI_TEST_DATABASE_URL — MySQL 8.4, MariaDB 11.4 and PostgreSQL 16: the per-person rows, the per-view scope (current
 * tenant, readable spaces, live pages, page and ancestors) before the cut, hidden favourites never counting toward the
 * limit and removable by id, the bound and the retention of the recents, exact ids, the unique-key race as a domain
 * result, failures of a visit (logged, never a 500; a lock conflict propagated inside a caller's transaction), the
 * export, the erasure and the purge.
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
    private array $listed = ['eng', 'ops', 'other'];

    protected function setUp(): void
    {
        $this->em     = WikiTestDatabase::create();
        $this->runner = new WikiTransactionRunner($this->em, backoffBaseMilliseconds: 0);
        $this->viewer = new TestUser('u-1');
        $this->eng    = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->ops    = new WikiSpace('ops', 'Operations', WikiSpaceOwnerScope::Team, 'team-1');
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

    public function testFavouritesAreAddedOnceRemovedAndBoundedByTheReadableOnes(): void
    {
        $store = $this->store();
        $quick = $this->quick(max: 2);
        $a     = $this->page($this->eng, 'a');
        $b     = $this->page($this->eng, 'b');
        $c     = $this->page($this->eng, 'c');

        self::assertSame(WikiFavouriteResult::Added, $quick->setFavourite($this->scope(), $a, true));
        self::assertSame(WikiFavouriteResult::Unchanged, $quick->setFavourite($this->scope(), $a, true));
        self::assertSame(WikiFavouriteResult::Added, $quick->setFavourite($this->scope(), $b, true));
        self::assertSame(WikiFavouriteResult::LimitReached, $quick->setFavourite($this->scope(), $c, true));
        self::assertTrue($store->isFavourite($a, 'u-1'));
        self::assertFalse($store->isFavourite($a, 'u-2'));

        // Audit VC-1: a favourite the viewer can no longer read (hidden, archived) no longer counts toward the limit.
        $this->hidden = ['a'];
        self::assertSame(WikiFavouriteResult::Added, $quick->setFavourite($this->scope(), $c, true));
        $b->archive();
        $this->em->flush();
        $d = $this->page($this->eng, 'd');
        self::assertSame(WikiFavouriteResult::Added, $quick->setFavourite($this->scope(), $d, true));
        self::assertSame(4, $this->rows('wiki_page_favourites', 'u-1'));

        // A hidden one can be removed by its id; the same outcome for another person's row and an unknown id.
        $id = $this->idOf('wiki_page_favourites', 'u-1', $a);
        $quick->removeFavouriteById($this->viewer, $id);
        self::assertFalse($store->isFavourite($a, 'u-1'));
        $store->addFavourite($a, 'u-2');
        $quick->removeFavouriteById($this->viewer, $this->idOf('wiki_page_favourites', 'u-2', $a));
        $quick->removeFavouriteById($this->viewer, '00000000-0000-4000-8000-000000000000');
        self::assertTrue($store->isFavourite($a, 'u-2'));
        $this->quick(favourites: false)->removeFavouriteById($this->viewer, $this->idOf('wiki_page_favourites', 'u-1', $c));
        self::assertTrue($store->isFavourite($c, 'u-1'));

        self::assertSame(WikiFavouriteResult::Removed, $quick->setFavourite($this->scope(), $c, false));
        self::assertSame(WikiFavouriteResult::Unchanged, $quick->setFavourite($this->scope(), $c, false));
        self::assertSame(WikiFavouriteResult::Unchanged, $quick->setFavourite($this->scope($this->anonymous()), $c, true));
        self::assertSame(WikiFavouriteResult::Unchanged, $this->quick(favourites: false)->setFavourite($this->scope(), $c, true));
    }

    public function testListsAreFilteredForTheViewsTenantBeforeTheyAreCut(): void
    {
        $store   = $this->store();
        $parent  = $this->page($this->eng, 'hidden-parent');
        $child   = $this->page($this->eng, 'child', $parent);
        $ops     = $this->page($this->ops, 'ops-page');
        $foreign = $this->page($this->other, 'foreign');
        $gone    = $this->page($this->eng, 'gone');
        $visible = $this->page($this->eng, 'visible');
        $older   = $this->page($this->eng, 'older');
        $time    = new DateTimeImmutable('2026-11-19 10:00:00');
        foreach ([$older, $visible, $gone, $foreign, $ops, $child] as $i => $page) {
            $store->addFavourite($page, 'u-1', $time->modify('+' . $i . ' minutes'));
        }
        $gone->archive();
        $this->em->flush();
        $this->hidden = ['hidden-parent'];

        // Newest first; the archived page, the other tenant's page (owner decision A: the current tenant only) and the
        // page under a hidden parent are left out before the cut to `shown`.
        self::assertSame(['ops-page', 'visible'], $this->slugs($this->quick(shown: 2)->favourites($this->scope())));
        self::assertSame(['ops-page', 'visible', 'older'], $this->slugs($this->quick()->favourites($this->scope())));
        // Seen from the other tenant: only its own page.
        self::assertSame(['foreign'], $this->slugs($this->quick()->favourites($this->scope(null, $this->other))));

        // A space the viewer lost: its pages go, the rows stay (the page shows again with access).
        $this->listed = ['eng'];
        self::assertSame(['visible', 'older'], $this->slugs($this->quick()->favourites($this->scope())));
        $this->listed = ['eng', 'ops', 'other'];
        self::assertSame(6, $this->rows('wiki_page_favourites', 'u-1'));

        // A space without a tenant key keeps the lists to itself.
        self::assertSame(['visible', 'older'], $this->slugs($this->quick()->favourites($this->scope(null, $this->eng, tenantless: true))));

        // Without the role gate, without a (valid) user id, or with favourites off: nothing.
        self::assertSame([], $this->quick()->favourites($this->scope(null, $this->eng, canList: false)));
        self::assertSame([], $this->quick()->favourites($this->scope($this->anonymous())));
        self::assertSame([], $this->quick()->favourites($this->scope(new TestUser(str_repeat('x', 129)))));
        self::assertSame([], $this->quick(favourites: false)->favourites($this->scope()));
        self::assertFalse($this->quick(favourites: false)->isFavourite($this->viewer, $visible));
        self::assertFalse($this->quick()->isFavourite($this->anonymous(), $visible));
        self::assertTrue($this->quick()->isFavourite($this->viewer, $visible));
        self::assertTrue($this->quick()->canKeepFavourites($this->viewer));
        self::assertFalse($this->quick()->canKeepFavourites($this->anonymous()));
        self::assertTrue($this->quick()->favouritesEnabled());
        self::assertTrue($this->quick()->recentsEnabled());
        self::assertFalse($this->scope()->canRead($foreign));
    }

    public function testRecentsAreBoundedRetainedAndWithoutTheCurrentPage(): void
    {
        $store = $this->store();
        $pages = [];
        foreach (['p1', 'p2', 'p3', 'p4'] as $i => $slug) {
            $pages[$slug] = $this->page($this->eng, $slug);
            $store->recordVisit($pages[$slug], 'u-1', 3, 90, new DateTimeImmutable('2026-11-19 10:0' . $i . ':00'));
        }
        // Visiting p2 again moves it to the top; p1 was pruned (keep: 3).
        $store->recordVisit($pages['p2'], 'u-1', 3, 90, new DateTimeImmutable('2026-11-19 11:00:00'));
        self::assertSame(3, $this->rows('wiki_page_visits', 'u-1'));
        $since = new DateTimeImmutable('2026-01-01');
        self::assertSame(['p2', 'p4', 'p3'], $this->slugs($store->visits('u-1', [$this->eng->getId()], 10, $since)));
        self::assertSame([], $store->visits('u-1', [], 10, $since));
        self::assertSame([], $store->visits('', [$this->eng->getId()], 10, $since));
        self::assertSame([], $store->visits('u-1', [$this->eng->getId()], 0, $since));
        // The retention: older visits are never listed, and the next visit deletes them.
        self::assertSame(['p2'], $this->slugs($store->visits('u-1', [$this->eng->getId()], 10, new DateTimeImmutable('2026-11-19 10:30:00'))));
        $store->recordVisit($pages['p1'], 'u-1', 10, 1, new DateTimeImmutable('2026-11-20 10:45:00'));
        self::assertSame(['p1', 'p2'], $this->slugs($store->visits('u-1', [$this->eng->getId()], 10, $since)));

        $quick = $this->quick(shown: 2, keep: 3, retention: 3650);
        self::assertSame(['p2'], $this->slugs($quick->recents($this->scope(), $pages['p1'])));
        self::assertSame([], $this->quick(recents: false)->recents($this->scope()));
        self::assertSame([], $quick->recents($this->scope($this->anonymous())));
        self::assertSame([], $this->quick(retention: 1)->recents($this->scope(), null, new DateTimeImmutable('2027-06-01')));

        // Through the service: recorded now, first in the list; nothing for an anonymous person or with recents off.
        $quick->recordVisit($this->viewer, $pages['p3']);
        $recent = $this->slugs($quick->recents($this->scope()));
        self::assertContains('p3', $recent);
        self::assertCount(2, $recent);
        $this->quick(recents: false)->recordVisit($this->viewer, $pages['p4']);
        $quick->recordVisit($this->anonymous(), $pages['p4']);
        self::assertNotContains('p4', $this->slugs($quick->recents($this->scope())));
    }

    public function testExportEraseAndPurge(): void
    {
        $store = $this->store();
        $page  = $this->page($this->eng, 'a');
        $store->addFavourite($page, 'u-1', new DateTimeImmutable('2026-11-19 10:00:00'));
        $store->addFavourite($page, 'u-2');
        $store->recordVisit($page, 'u-1', 10, 90, new DateTimeImmutable('2026-11-19 10:00:00'));
        $store->recordVisit($page, 'u-3', 10, 90, new DateTimeImmutable('2020-01-01 10:00:00'));

        $export = $store->exportUser('u-1');
        self::assertSame($page->getId(), $export['favourites'][0]['page_id']);
        self::assertStringStartsWith('2026-11-19 10:00:00', $export['favourites'][0]['created_at']);
        self::assertStringStartsWith('2026-11-19 10:00:00', $export['visits'][0]['visited_at']);

        self::assertSame(2, $store->eraseUser('u-1'));
        self::assertSame(['favourites' => [], 'visits' => []], $store->exportUser('u-1'));
        self::assertSame(1, $this->rows('wiki_page_favourites', 'u-2'));

        self::assertSame(1, $store->purgeVisitsBefore(new DateTimeImmutable('2025-01-01'), 10));
        self::assertSame(0, $this->rows('wiki_page_visits', 'u-3'));
        // Audit VC2-2: a visit refreshed after it was selected is not deleted (the time is checked again).
        $store->recordVisit($page, 'u-4', 10, 3650, new DateTimeImmutable('2026-11-19 10:00:00'));
        $id = $this->idOf('wiki_page_visits', 'u-4', $page);
        self::assertSame(0, (fn (): int => $this->deleteIds($this->connection(), 'wiki_page_visits', [$id], new DateTimeImmutable('2026-01-01')))->call($store));
        self::assertSame(1, $this->rows('wiki_page_visits', 'u-4'));

        // Audit VC-3: the tables that exist are erased whatever the switches; a missing table is skipped.
        $this->em->getConnection()->executeStatement('DROP TABLE wiki_page_visits');
        self::assertSame(1, $store->eraseUser('u-2'));
        self::assertSame(0, $store->purgeVisitsBefore(new DateTimeImmutable(), 10));
        self::assertSame(['favourites' => [], 'visits' => []], $store->exportUser('u-2'));
        $this->em->getConnection()->executeStatement('DROP TABLE wiki_page_favourites');
        self::assertSame(0, $store->eraseUser('u-2'));
    }

    /** The "only one row" race of the unique key, simulated on SQLite by a trigger: the outcome asked for holds. */
    public function testAUniqueViolationIsTheExpectedRaceNotAConflict(): void
    {
        if (WikiTestDatabase::isServer()) {
            self::markTestSkipped('SQLite simulation; the servers run the real two-connection race in WikiQuickAccessRaceTest.');
        }
        $store = $this->store();
        $page  = $this->page($this->eng, 'a');
        $this->em->getConnection()->executeStatement("CREATE TRIGGER wk_fav_race BEFORE INSERT ON wiki_page_favourites BEGIN SELECT RAISE(ABORT, 'UNIQUE constraint failed: wiki_page_favourites.user_id, wiki_page_favourites.page_id'); END");
        $this->em->getConnection()->executeStatement("CREATE TRIGGER wk_visit_race BEFORE INSERT ON wiki_page_visits BEGIN SELECT RAISE(ABORT, 'UNIQUE constraint failed: wiki_page_visits.user_id, wiki_page_visits.page_id'); END");

        self::assertSame(WikiFavouriteResult::Unchanged, $store->addFavourite($page, 'u-1'));
        $store->recordVisit($page, 'u-1', 10, 90);
        self::assertSame(0, $this->rows('wiki_page_visits', 'u-1'));
        self::assertTrue($this->em->isOpen());
    }

    /** Failures of a visit (simulated on SQLite): logged, never a 500; a lock conflict inside a caller's transaction propagates. */
    public function testAFailedVisitIsLoggedOrPropagatedWhenNested(): void
    {
        if (WikiTestDatabase::isServer()) {
            self::markTestSkipped('SQLite simulation; the servers run the real lock conflict in WikiQuickAccessRaceTest.');
        }
        $page = $this->page($this->eng, 'a');
        $this->em->getConnection()->executeStatement("CREATE TRIGGER wk_visit_locked BEFORE INSERT ON wiki_page_visits BEGIN SELECT RAISE(ABORT, 'database is locked'); END");
        $logger = new RecordingLogger();
        $quick  = $this->quick(logger: $logger);

        $quick->recordVisit($this->viewer, $page);
        self::assertStringContainsString('page visit was not recorded (lock conflict)', $logger->lines()[0] ?? '');

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

        // Any other failure: logged, the page is shown.
        $connection->executeStatement('DROP TABLE wiki_page_visits');
        $quick->recordVisit($this->viewer, $page);
        self::assertStringContainsString('page visit was not recorded.', $logger->lines()[1] ?? '');
    }

    /** User ids are compared exactly, and every write acts on the rows of that exact id (audit VC-2). */
    public function testUserIdsAreExact(): void
    {
        $store = $this->store();
        $page  = $this->page($this->eng, 'a');
        $store->addFavourite($page, 'alice');
        $store->recordVisit($page, 'alice', 10, 90);

        self::assertFalse($store->isFavourite($page, 'Alice'));
        self::assertSame([], $store->favourites('ALICE', [$this->eng->getId()], 10));
        self::assertSame(WikiFavouriteResult::Added, $store->addFavourite($page, 'Alice'));
        self::assertSame(WikiFavouriteResult::Removed, $store->removeFavourite($page, 'Alice'));
        self::assertTrue($store->isFavourite($page, 'alice'));
        self::assertSame(0, $store->eraseUser('Alice'));
        self::assertSame(1, $this->rows('wiki_page_visits', 'alice'));
        self::assertSame(['a'], $this->slugs($store->favourites('alice', [$this->eng->getId()], 10)));
    }

    /**
     * Audit VC-2 (simulated on SQLite with a case-folding column, as a PAD SPACE or folding collation on a server):
     * the SQL matches the look-alike rows, the exact comparison keeps them out of every write.
     */
    public function testAFoldingCollationNeverTouchesAnotherPersonsRow(): void
    {
        if (WikiTestDatabase::isServer()) {
            self::markTestSkipped('SQLite simulation of a folding collation.');
        }
        $connection = $this->em->getConnection();
        foreach (['wiki_page_favourites' => 'created_at', 'wiki_page_visits' => 'visited_at'] as $table => $time) {
            $connection->executeStatement('DROP TABLE ' . $table);
            $connection->executeStatement('CREATE TABLE ' . $table . ' (id VARCHAR(36) NOT NULL PRIMARY KEY, page_id VARCHAR(36) NOT NULL, user_id VARCHAR(128) NOT NULL COLLATE NOCASE, ' . $time . ' DATETIME NOT NULL)');
        }
        $store = $this->store();
        $page  = $this->page($this->eng, 'a');
        $store->addFavourite($page, 'alice');
        $store->recordVisit($page, 'alice', 10, 90, new DateTimeImmutable('2026-11-19 10:00:00'));

        self::assertSame(WikiFavouriteResult::Added, $store->addFavourite($page, 'Alice'));
        self::assertSame(WikiFavouriteResult::Unchanged, $store->addFavourite($page, 'Alice'));
        self::assertSame(WikiFavouriteResult::Removed, $store->removeFavourite($page, 'Alice'));
        self::assertTrue($store->isFavourite($page, 'alice'));
        $store->removeFavouriteById($this->idOf('wiki_page_favourites', 'alice', $page), 'Alice');
        self::assertTrue($store->isFavourite($page, 'alice'));
        // A visit of Alice (keep 1) prunes only her own rows, never alice's.
        $store->recordVisit($page, 'Alice', 1, 90, new DateTimeImmutable('2026-11-19 11:00:00'));
        self::assertSame('2026-11-19 10:00:00', substr((string) $connection->fetchOne("SELECT visited_at FROM wiki_page_visits WHERE user_id = 'alice' COLLATE BINARY"), 0, 19));
        self::assertSame(1, $store->eraseUser('Alice'));
        self::assertSame(['favourites' => 1, 'visits' => 1], ['favourites' => count($store->exportUser('alice')['favourites']), 'visits' => count($store->exportUser('alice')['visits'])]);
        self::assertSame([], $store->exportUser('Alice')['visits']);
    }

    /** A visit deletes at most PRUNE_BATCH stale rows; the next visits delete the rest. */
    public function testPruningIsBounded(): void
    {
        $store      = $this->store();
        $page       = $this->page($this->eng, 'a');
        $connection = $this->em->getConnection();
        for ($i = 0; $i < WikiQuickAccessStore::PRUNE_BATCH + 5; ++$i) {
            $other = $this->page($this->eng, 'old-' . $i);
            $connection->insert('wiki_page_visits', ['id' => sprintf('00000000-0000-4000-8000-%012d', $i), 'page_id' => $other->getId(), 'user_id' => 'u-1', 'visited_at' => '2020-01-01 10:00:00']);
        }
        $store->recordVisit($page, 'u-1', 10, 90, new DateTimeImmutable('2026-11-19 10:00:00'));
        self::assertSame(6, $this->rows('wiki_page_visits', 'u-1'));
        $store->recordVisit($page, 'u-1', 10, 90, new DateTimeImmutable('2026-11-19 10:01:00'));
        self::assertSame(1, $this->rows('wiki_page_visits', 'u-1'));
    }

    private function store(): WikiQuickAccessStore
    {
        return new WikiQuickAccessStore($this->runner);
    }

    private function quick(bool $favourites = true, bool $recents = true, int $shown = 10, int $keep = 20, int $max = 100, int $retention = 3650, ?RecordingLogger $logger = null): WikiQuickAccess
    {
        return new WikiQuickAccess(
            $this->store(),
            ['enabled' => $favourites, 'max' => $max, 'shown' => $shown],
            ['enabled' => $recents, 'keep' => $keep, 'shown' => $shown, 'retention_days' => $retention],
            $logger,
        );
    }

    /** The scope of one view of `$current` (Engineering by default); team-1 and team-9 are two tenants. */
    private function scope(?UserInterface $viewer = null, ?WikiSpace $current = null, bool $canList = true, bool $tenantless = false): WikiViewerScope
    {
        $roles = $this->createStub(WikiAccessCheckerInterface::class);
        $roles->method('canList')->willReturn($canList);
        $resolver = $this->createStub(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturnCallback(fn (): array => array_values(array_filter([$this->eng, $this->ops, $this->other], fn (WikiSpace $space): bool => in_array($space->getSlug(), $this->listed, true))));
        $checker = new CallbackSpaceAccessChecker(fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => $action === WikiAction::View && (!$page instanceof WikiPage || !in_array($page->getSlug(), $this->hidden, true)));
        $tenants = new class($tenantless) implements WikiLinkTenantResolverInterface {
            public function __construct(private readonly bool $none)
            {
            }

            public function tenantOf(WikiSpaceSnapshot $space): ?string
            {
                return $this->none ? null : $space->ownerScopeId;
            }
        };

        return (new WikiReadableSpaces($roles, $resolver, $checker, $tenants))->forView($viewer ?? $this->viewer, $current ?? $this->eng);
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

    private function idOf(string $table, string $userId, WikiPage $page): string
    {
        return (string) $this->em->getConnection()->fetchOne('SELECT id FROM ' . $table . ' WHERE user_id = ? AND page_id = ?', [$userId, $page->getId()]);
    }

    private function anonymous(): UserInterface
    {
        return new TestUserEntity('');
    }

    /**
     * @param list<WikiPageFavourite|WikiPageVisit> $rows
     *
     * @return list<string>
     */
    private function slugs(array $rows): array
    {
        return array_map(static fn (WikiPageFavourite|WikiPageVisit $row): string => $row->getPage()->getSlug(), $rows);
    }
}
