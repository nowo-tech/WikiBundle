<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Nowo\WikiBundle\Backlink\WikiPageReferenceIndex;
use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Markdown\WikiMarkdownLimitsInterface;
use Nowo\WikiBundle\Markdown\WikiMarkdownReferenceExtractor;
use Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\Query\WikiArchiveFilter;
use Nowo\WikiBundle\Query\WikiOwnerUsage;
use Nowo\WikiBundle\Query\WikiPageCriteria;
use Nowo\WikiBundle\Query\WikiPageQuery;
use Nowo\WikiBundle\Query\WikiPageSearchHit;
use Nowo\WikiBundle\Query\WikiPageSort;
use Nowo\WikiBundle\Query\WikiSpaceCriteria;
use Nowo\WikiBundle\Query\WikiSpaceQuery;
use Nowo\WikiBundle\Query\WikiSpaceSort;
use Nowo\WikiBundle\Security\WikiPageAccess;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\LoadReadinessFactory;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_map;
use function in_array;
use function sort;
use function sprintf;
use function strtoupper;

/**
 * 1.51 (DDD-host checklist B7): the read ports {@see WikiSpaceQuery} and {@see WikiPageQuery} on SQLite and — through
 * WIKI_TEST_DATABASE_URL — MySQL 8.4, MariaDB 11.4 and PostgreSQL 16: filters, sorts, paging, counts, usage, page
 * statistics, ancestors, contents, backlinks (index and scan) and search, every id and owner matched byte for byte.
 */
final class WikiReadModelTest extends TestCase
{
    private EntityManager $em;

    private WikiTransactionRunner $runner;

    private WikiPageService $pages;

    private TestUserEntity $author;

    private WikiSpace $alpha;

    private WikiSpace $beta;

    private WikiSpace $gamma;

    private WikiSpace $personal;

    private WikiPage $root;

    private WikiPage $child;

    private WikiPage $grandchild;

    private WikiPage $archived;

    /** @var list<string> page slugs the checker hides */
    private array $hidden = [];

    protected function setUp(): void
    {
        $this->em       = WikiTestDatabase::create();
        $this->runner   = new WikiTransactionRunner($this->em, backoffBaseMilliseconds: 0);
        $this->author   = new TestUserEntity('u-1');
        $this->alpha    = new WikiSpace('alpha', 'Alpha', WikiSpaceOwnerScope::Team, 'team-a');
        $this->beta     = new WikiSpace('beta', 'Beta', WikiSpaceOwnerScope::Team, 'team-a');
        $this->gamma    = new WikiSpace('gamma', 'Gamma', WikiSpaceOwnerScope::Team, 'team-b');
        $this->personal = new WikiSpace('mine', 'Mine', WikiSpaceOwnerScope::User, 'team-a');
        foreach ([$this->author, $this->alpha, $this->beta, $this->gamma, $this->personal] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        $this->pages = LoadReadinessFactory::pages($this->em);
        // An archived space refuses writes: its page first.
        $this->page($this->beta, 'Deploy archived space', 'never searched');
        $this->beta->changeArchived(true);
        $this->root       = $this->page($this->alpha, 'Root', '# Root');
        $this->child      = $this->page($this->alpha, 'Child', 'child text', $this->root);
        $this->grandchild = $this->page($this->alpha, 'Grandchild', 'deep text', $this->child);
        $this->archived   = $this->page($this->alpha, 'Old', 'old text');
        $this->archived->archive();
        // Positions that order the whole space (the tree order sorts by space, then position, then id).
        $this->root->moveTo(null, 0);
        $this->child->moveTo($this->root, 1);
        $this->grandchild->moveTo($this->child, 2);
        $this->archived->moveTo(null, 3);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
    }

    public function testSpacesAreListedByOwnerWithFiltersSortsAndPaging(): void
    {
        $spaces = $this->spaces();
        $teamA  = WikiSpaceCriteria::forOwners(WikiSpaceOwnerScope::Team, ['team-a']);

        self::assertSame(['Alpha', 'Beta'], $this->names($spaces->findSpaces($teamA)));
        self::assertSame(['Alpha'], $this->names($spaces->findSpaces(WikiSpaceCriteria::forOwners(WikiSpaceOwnerScope::Team, ['team-a'], WikiArchiveFilter::Active))));
        self::assertSame(['Beta'], $this->names($spaces->findSpaces(WikiSpaceCriteria::forOwners(WikiSpaceOwnerScope::Team, ['team-a'], WikiArchiveFilter::Archived))));
        self::assertSame(['Beta', 'Alpha'], $this->names($spaces->findSpaces(WikiSpaceCriteria::forOwners(WikiSpaceOwnerScope::Team, ['team-a'], sort: WikiSpaceSort::NameDesc))));
        self::assertSame(['Alpha', 'Beta'], $this->names($spaces->findSpaces(WikiSpaceCriteria::forOwners(WikiSpaceOwnerScope::Team, ['team-a'], sort: WikiSpaceSort::SlugAsc))));
        foreach ([WikiSpaceSort::CreatedAsc, WikiSpaceSort::CreatedDesc, WikiSpaceSort::UpdatedDesc] as $sort) {
            $names = $this->names($spaces->findSpaces(new WikiSpaceCriteria(null, ['team-a', 'team-b'], null, WikiArchiveFilter::Any, $sort)));
            sort($names);
            self::assertSame(['Alpha', 'Beta', 'Gamma', 'Mine'], $names, $sort->value);
        }
        self::assertSame(['Beta'], $this->names($spaces->findSpaces($teamA, 1, 1)), 'paging');
        self::assertSame(['Alpha', 'Beta', 'Mine'], $this->names($spaces->findSpaces(new WikiSpaceCriteria(null, ['team-a']))), 'both owner kinds');
        self::assertSame(['Gamma', 'Mine'], $this->names($spaces->findSpaces(new WikiSpaceCriteria(spaceIds: [$this->gamma->getId(), $this->personal->getId()]))));
        self::assertSame(['Alpha', 'Beta', 'Gamma', 'Mine'], $this->names($spaces->findSpaces(new WikiSpaceCriteria())), 'every owner (trusted callers)');

        // Look-alike owners and ids answer nothing: binary NO PAD on MySQL / MariaDB, exact elsewhere, re-checked in PHP.
        self::assertSame([], $spaces->findSpaces(WikiSpaceCriteria::forOwners(WikiSpaceOwnerScope::Team, ['Team-a'])));
        self::assertSame([], $spaces->findSpaces(new WikiSpaceCriteria(spaceIds: [strtoupper($this->alpha->getId())])));
        self::assertSame(0, $spaces->countSpaces(new WikiSpaceCriteria(spaceIds: [strtoupper($this->alpha->getId())])));

        self::assertSame(2, $spaces->countSpaces($teamA));
        self::assertSame(1, $spaces->countSpaces(WikiSpaceCriteria::forOwners(WikiSpaceOwnerScope::Team, ['team-a'], WikiArchiveFilter::Active)));
        self::assertSame(4, $spaces->countSpaces(new WikiSpaceCriteria()));
        self::assertSame(0, $spaces->countSpaces(WikiSpaceCriteria::forOwners(WikiSpaceOwnerScope::Team, [])));
        self::assertSame([], $spaces->findSpaces(new WikiSpaceCriteria(spaceIds: [])));

        $snapshot = $spaces->findSpaces($teamA, 0, 1)[0];
        self::assertSame($this->alpha->getId(), $snapshot->id);
        self::assertSame('alpha', $snapshot->slug);
        self::assertSame(WikiSpaceOwnerScope::Team, $snapshot->ownerScopeType);
        self::assertSame('team-a', $snapshot->ownerScopeId);
        self::assertFalse($snapshot->isArchived());
        self::assertSame(1, $snapshot->version);
        self::assertTrue($spaces->findSpaces($teamA, 1, 1)[0]->isArchived());

        // Columns only: nothing enters the entity manager.
        $this->em->clear();
        $spaces->findSpaces($teamA);
        $spaces->countSpaces($teamA);
        $spaces->findSpacesByIds([$this->alpha->getId()]);
        self::assertSame([], $this->em->getUnitOfWork()->getIdentityMap());
    }

    public function testPagingIsBounded(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->spaces()->findSpaces(new WikiSpaceCriteria(), 0, 501);
    }

    public function testSpacesByIdsKeepTheOrderAskedAndExactIdsOnly(): void
    {
        $found = $this->spaces()->findSpacesByIds([$this->gamma->getId(), 'missing', $this->alpha->getId(), $this->gamma->getId(), strtoupper($this->beta->getId())]);

        self::assertSame([$this->gamma->getId(), $this->alpha->getId()], array_keys($found));
        self::assertSame('Gamma', $found[$this->gamma->getId()]->name);
        self::assertSame([], $this->spaces()->findSpacesByIds([]));
    }

    public function testUsageCountsSpacesPagesAndBytesPerOwner(): void
    {
        $gammaPage = $this->page($this->gamma, 'Gamma page', 'text');
        $this->page($this->personal, 'Mine page', 'text');
        $this->attach($this->root, $this->alpha, 100);
        $this->attach($this->child, $this->alpha, 50);
        $this->attach($gammaPage, $this->gamma, 7);

        $usage = $this->spaces()->usage(WikiSpaceOwnerScope::Team, ['team-a', 'team-z', 'team-b', 'team-a']);

        self::assertSame(['team-a', 'team-z', 'team-b'], array_keys($usage));
        self::assertEquals(new WikiOwnerUsage('team-a', 1, 1, 4, 1, 150), $usage['team-a'], 'the user space of the same id is another owner kind');
        self::assertEquals(new WikiOwnerUsage('team-z'), $usage['team-z']);
        self::assertEquals(new WikiOwnerUsage('team-b', 1, 0, 1, 0, 7), $usage['team-b']);
        self::assertEquals(new WikiOwnerUsage('', 2, 1, 5, 1, 157), WikiOwnerUsage::total($usage));
        self::assertEquals(new WikiOwnerUsage('team-a', 1, 0, 1, 0, 0), $this->spaces()->usage(WikiSpaceOwnerScope::User, ['team-a'])['team-a']);
        self::assertEquals(new WikiOwnerUsage('Team-a'), $this->spaces()->usage(WikiSpaceOwnerScope::Team, ['Team-a'])['Team-a'], 'a look-alike owner holds nothing');

        // A numeric owner id stays a string id end to end (never an integer array key compared to a string).
        $this->em->persist(new WikiSpace('numbered', 'Numbered', WikiSpaceOwnerScope::Team, '42'));
        $this->em->flush();
        self::assertEquals(new WikiOwnerUsage('42', 1), $this->spaces()->usage(WikiSpaceOwnerScope::Team, ['42'])['42']);
        self::assertSame(['Numbered'], $this->names($this->spaces()->findSpaces(WikiSpaceCriteria::forOwners(WikiSpaceOwnerScope::Team, ['42']))));
        self::assertSame(1, $this->spaces()->countSpaces(WikiSpaceCriteria::forOwners(WikiSpaceOwnerScope::Team, ['42'])));
    }

    public function testUsageRefusesAnUnusableOwnerId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->spaces()->usage(WikiSpaceOwnerScope::Team, ['']);
    }

    public function testPagesAreListedAndCountedWithFiltersSortsAndPaging(): void
    {
        $query  = $this->query();
        $gammaP = $this->page($this->gamma, 'Gamma page', 'text');

        self::assertSame(['Root', 'Child', 'Grandchild', 'Old'], $this->titles($query->findPages(WikiPageCriteria::inSpaces([$this->alpha->getId()]))), 'tree order');
        self::assertSame(['Root', 'Child', 'Grandchild'], $this->titles($query->findPages(WikiPageCriteria::inSpaces([$this->alpha->getId()], WikiArchiveFilter::Active))));
        self::assertSame(['Old'], $this->titles($query->findPages(WikiPageCriteria::inSpaces([$this->alpha->getId()], WikiArchiveFilter::Archived))));
        self::assertSame(['Child', 'Grandchild', 'Old', 'Root'], $this->titles($query->findPages(WikiPageCriteria::inSpaces([$this->alpha->getId()], sort: WikiPageSort::TitleAsc))));
        foreach ([WikiPageSort::CreatedAsc, WikiPageSort::UpdatedDesc] as $sort) {
            $titles = $this->titles($query->findPages(WikiPageCriteria::inSpaces([$this->alpha->getId()], sort: $sort)));
            sort($titles);
            self::assertSame(['Child', 'Grandchild', 'Old', 'Root'], $titles, $sort->value);
        }
        self::assertSame(['Child'], $this->titles($query->findPages(WikiPageCriteria::inSpaces([$this->alpha->getId()], sort: WikiPageSort::TitleAsc), 0, 1)));
        self::assertSame(['Grandchild'], $this->titles($query->findPages(WikiPageCriteria::inSpaces([$this->alpha->getId()], sort: WikiPageSort::TitleAsc), 1, 1)));
        self::assertSame(['Gamma page'], $this->titles($query->findPages(WikiPageCriteria::ofOwners(WikiSpaceOwnerScope::Team, ['team-b']))));
        self::assertSame(['Root'], $this->titles($query->findPages(new WikiPageCriteria(pageIds: [$this->root->getId(), strtoupper($this->child->getId())]))));
        self::assertSame([], $query->findPages(WikiPageCriteria::ofOwners(WikiSpaceOwnerScope::Team, ['Team-b'])), 'look-alike owner');
        self::assertSame([], $query->findPages(WikiPageCriteria::inSpaces([])));

        self::assertSame(4, $query->countPages(WikiPageCriteria::inSpaces([$this->alpha->getId()])));
        self::assertSame(4, $query->countPages(WikiPageCriteria::ofOwners(WikiSpaceOwnerScope::Team, ['team-a'], WikiArchiveFilter::Active)));
        self::assertSame(6, $query->countPages(new WikiPageCriteria(null, null, ['team-a', 'team-b'])));
        self::assertSame(0, $query->countPages(WikiPageCriteria::ofOwners(WikiSpaceOwnerScope::Team, ['Team-a'])));
        self::assertSame(0, $query->countPages(WikiPageCriteria::inSpaces([strtoupper($this->alpha->getId())])));
        self::assertSame(1, $query->countPages(new WikiPageCriteria(pageIds: [$gammaP->getId(), strtoupper($this->root->getId()), 'missing'])));
        self::assertSame(0, $query->countPages(new WikiPageCriteria(pageIds: [])));

        $snapshot = $query->findPages(new WikiPageCriteria(pageIds: [$this->child->getId()]))[0];
        self::assertSame($this->alpha->getId(), $snapshot->spaceId);
        self::assertSame($this->root->getId(), $snapshot->parentId);
        self::assertSame($this->child->getCurrentRevision()?->getId(), $snapshot->currentRevisionId);
        self::assertSame('child', $snapshot->slug);
        self::assertFalse($snapshot->isArchived());
    }

    public function testPagesByIdsStatsAncestorsAndContents(): void
    {
        $query = $this->query();
        $bare  = new WikiPage($this->gamma, 'bare', 'Bare');
        $this->em->persist($bare);
        $this->em->flush();

        self::assertSame([$this->grandchild->getId(), $this->root->getId()], array_keys($query->findPagesByIds([$this->grandchild->getId(), 'missing', $this->root->getId(), strtoupper($this->child->getId())])));

        $stats = $query->pageStats([$this->gamma->getId(), $this->alpha->getId(), 'missing']);
        self::assertSame([$this->gamma->getId(), $this->alpha->getId(), 'missing'], array_keys($stats));
        self::assertSame(3, $stats[$this->alpha->getId()]->pages, 'live pages only');
        self::assertInstanceOf(DateTimeImmutable::class, $stats[$this->alpha->getId()]->lastUpdatedAt);
        self::assertSame(1, $stats[$this->gamma->getId()]->pages);
        self::assertSame(0, $stats['missing']->pages);
        self::assertNull($stats['missing']->lastUpdatedAt);

        $ancestors = $query->ancestors([$this->grandchild->getId(), $this->root->getId(), 'missing', $this->child->getId()]);
        self::assertSame([$this->grandchild->getId(), $this->root->getId(), $this->child->getId()], array_keys($ancestors));
        self::assertSame(['Root', 'Child'], $this->titles($ancestors[$this->grandchild->getId()]));
        self::assertSame([], $ancestors[$this->root->getId()]);
        self::assertSame(['Root'], $this->titles($ancestors[$this->child->getId()]));

        // A parent cycle in the data stops at the first page met twice.
        $this->root->moveTo($this->grandchild, 0);
        $this->em->flush();
        self::assertSame(['Child', 'Grandchild'], $this->titles($query->ancestors([$this->root->getId()])[$this->root->getId()]));

        $contents = $query->pageContents([$this->grandchild->getId(), $bare->getId(), $this->root->getId()]);
        self::assertSame([$this->grandchild->getId(), $this->root->getId()], array_keys($contents), 'a page without revision is left out');
        self::assertSame('deep text', $contents[$this->grandchild->getId()]->markdown);
        self::assertSame(WikiPageRevision::FORMAT_MARKDOWN, $contents[$this->grandchild->getId()]->format);
        self::assertSame(1, $contents[$this->grandchild->getId()]->revisionNumber);
        self::assertSame($this->grandchild->getCurrentRevision()?->getId(), $contents[$this->grandchild->getId()]->revisionId);
        self::assertStringContainsString('deep text', $contents[$this->grandchild->getId()]->html);
        self::assertSame('deep text', $contents[$this->grandchild->getId()]->text);
    }

    public function testBacklinksWithAndWithoutTheIndexFollowTheViewerAndTheSpaces(): void
    {
        $target = $this->grandchild;
        $link   = sprintf('See [[wiki:%s]].', $target->getId());
        $this->page($this->alpha, 'zulu', $link);
        $this->page($this->alpha, 'Alpha source', sprintf('[[wiki:%s#intro]]', $target->getId()));
        $this->page($this->alpha, 'Below hidden', $link, $this->archivedParent());
        $this->page($this->gamma, 'Other space', $link);
        $this->page($this->alpha, 'In code', sprintf("```\n[[wiki:%s]]\n```", $target->getId()));
        $this->page($this->alpha, 'Prefix only', sprintf('[[wiki:%sX]]', $target->getId()));
        $this->page($this->alpha, 'Self', sprintf('[[wiki:%s]]', $target->getId()))->archive();
        $this->em->flush();
        $this->hidden = ['hidden-parent'];
        $viewer       = WikiUserRefs::fromUser($this->author);

        $index = new WikiPageReferenceIndex($this->runner, new WikiMarkdownReferenceExtractor());
        foreach ($this->em->getRepository(WikiPage::class)->findAll() as $page) {
            $index->reindex($page->getId());
        }
        foreach ([false, true] as $withIndex) {
            $query = $this->query($withIndex);
            self::assertSame(['Alpha source', 'Below hidden', 'Other space', 'zulu'], $this->titles($query->backlinks($target->getId())), $withIndex ? 'index' : 'scan');
            self::assertSame(['Alpha source', 'Other space', 'zulu'], $this->titles($query->backlinks($target->getId(), $viewer)), 'only what the viewer may read');
            self::assertSame(['Alpha source', 'zulu'], $this->titles($query->backlinks($target->getId(), $viewer, [$this->alpha->getId()])), 'only these spaces');
            self::assertCount(1, $query->backlinks($target->getId(), $viewer, null, 1), 'limit');
            self::assertSame([], $query->backlinks($target->getId(), null, []));
            self::assertSame([], $query->backlinks(''));
            self::assertSame([], $query->backlinks(strtoupper($target->getId())), 'look-alike target');
        }

        // The candidate budget bounds the scan.
        self::assertCount(1, $this->query(true, 1)->backlinks($target->getId()));
    }

    public function testSearchFindsTitlesFirstThenTextOnlyWhereTheViewerMayRead(): void
    {
        $this->page($this->alpha, 'Deploy guide', 'how to ship');
        $this->page($this->alpha, 'Runbook', 'deploy on friday');
        $this->page($this->alpha, 'Secret deploy', 'hidden', $this->archivedParent());
        $this->page($this->gamma, 'Deploy elsewhere', 'other space');
        $this->hidden = ['hidden-parent'];
        $viewer       = WikiUserRefs::fromUser($this->author);
        $query        = $this->query();

        $all = $query->searchPages([$this->alpha->getId(), $this->beta->getId()], 'deploy');
        self::assertTrue($all->complete);
        $titles = $this->hitTitles($all->hits);
        self::assertSame('Runbook', $titles[2], 'titles first, archived space never');
        self::assertSame(['Deploy guide', 'Secret deploy'], $this->sortedHitTitles([$all->hits[0], $all->hits[1]]));
        self::assertSame('', $all->hits[0]->excerpt);
        self::assertStringContainsString('deploy on friday', $all->hits[2]->excerpt);
        self::assertInstanceOf(WikiSpaceSnapshot::class, $all->hits[0]->space);
        self::assertSame('alpha', $all->hits[0]->space->slug);

        $readable = $query->searchPages([$this->alpha->getId(), $this->gamma->getId()], 'deploy', $viewer);
        self::assertSame(['Deploy elsewhere', 'Deploy guide', 'Runbook'], $this->sortedHitTitles($readable->hits));

        $cut = $query->searchPages([$this->alpha->getId()], 'deploy', null, 1);
        self::assertFalse($cut->complete);
        self::assertCount(1, $cut->hits);

        self::assertSame([], $query->searchPages([$this->alpha->getId()], '  ')->hits);
        self::assertSame([], $query->searchPages(['missing', strtoupper($this->alpha->getId())], 'deploy')->hits);
        self::assertFalse($this->query(false, 200, 1)->searchPages([$this->alpha->getId()], 'deploy')->complete, 'the budget bounds each scan');
    }

    public function testSearchOverTheRenderersLimitsMatchesTitlesOnly(): void
    {
        $this->page($this->alpha, 'Huge', 'needle in a huge body');
        $renderer = new class implements WikiMarkdownRendererInterface, WikiMarkdownLimitsInterface {
            public function render(string $markdown): string
            {
                return $markdown;
            }

            public function exceedsLimits(string $markdown): bool
            {
                return true;
            }
        };
        $query = new WikiPageQuery($this->runner, $this->access(), new WikiSearchService($this->em), new WikiMarkdownReferenceExtractor(), renderer: $renderer);

        self::assertSame([], $query->searchPages([$this->alpha->getId()], 'needle')->hits);
    }

    private function spaces(): WikiSpaceQuery
    {
        return new WikiSpaceQuery($this->runner);
    }

    private function query(bool $index = false, int $candidates = 200, int $searchCandidates = 200): WikiPageQuery
    {
        return new WikiPageQuery($this->runner, $this->access(), new WikiSearchService($this->em), new WikiMarkdownReferenceExtractor(), $index, $candidates, null, $searchCandidates);
    }

    private function access(): WikiPageAccess
    {
        return new WikiPageAccess($this->runner, new CallbackSpaceAccessChecker(fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => $action === WikiAction::View && !in_array($page?->getSlug(), $this->hidden, true)));
    }

    private function archivedParent(): WikiPage
    {
        return $this->page($this->alpha, 'Hidden parent', 'nothing', null, 'hidden-parent');
    }

    private function page(WikiSpace $space, string $title, string $content, ?WikiPage $parent = null, ?string $slug = null): WikiPage
    {
        return $this->pages->create($space, $title, $content, $this->author, $parent, $slug, WikiPageRevision::FORMAT_MARKDOWN);
    }

    private function attach(WikiPage $page, WikiSpace $space, int $size): void
    {
        $this->em->persist(new WikiAttachment($page, $space, 'f.txt', 'key-' . $page->getId() . '-' . $size, 'text/plain', $size, 'abc'));
        $this->em->flush();
    }

    /**
     * @param list<WikiSpaceSnapshot> $spaces
     *
     * @return list<string>
     */
    private function names(array $spaces): array
    {
        return array_map(static fn (WikiSpaceSnapshot $space): string => $space->name, $spaces);
    }

    /**
     * @param list<WikiPageSnapshot> $pages
     *
     * @return list<string>
     */
    private function titles(array $pages): array
    {
        return array_map(static fn (WikiPageSnapshot $page): string => $page->title, $pages);
    }

    /**
     * @param list<WikiPageSearchHit> $hits
     *
     * @return list<string>
     */
    private function hitTitles(array $hits): array
    {
        return array_map(static fn (WikiPageSearchHit $hit): string => $hit->page->title, $hits);
    }

    /**
     * @param list<WikiPageSearchHit> $hits
     *
     * @return list<string>
     */
    private function sortedHitTitles(array $hits): array
    {
        $titles = $this->hitTitles($hits);
        sort($titles);

        return $titles;
    }
}
