<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use Closure;
use Doctrine\ORM\EntityManager;
use Nowo\SearchKitBundle\Model\SearchQuery;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Markdown\WikiMarkdownLimitsInterface;
use Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiSpaceRepository;
use Nowo\WikiBundle\Search\SearchKit\WikiPageSearchKitProvider;
use Nowo\WikiBundle\Search\WikiReadablePageFinder;
use Nowo\WikiBundle\Search\WikiReadablePageHits;
use Nowo\WikiBundle\Search\WikiSearchViewerResolverInterface;
use Nowo\WikiBundle\Security\AllowAllWikiAccessChecker;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiTeamMembershipResolverInterface;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolver;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Translation\IdentityTranslator;

use function array_map;
use function array_unique;
use function array_values;
use function count;
use function in_array;
use function sort;
use function sprintf;
use function str_starts_with;

require_once __DIR__ . '/../Stub/SearchKit/SearchKit.php';

/**
 * 1.44 (Beacon KIT-85): {@see WikiReadablePageFinder}, the read-rule port of the SearchKit provider, on SQLite and —
 * through WIKI_TEST_DATABASE_URL — MySQL and PostgreSQL: the role gate, the tenant / space rule, the scope modes, the
 * page-plus-ancestors rule, the candidate bound and the recents lookup.
 */
final class WikiReadablePageFinderTest extends TestCase
{
    private EntityManager $em;

    private WikiPageService $pages;

    private TestUserEntity $user;

    private WikiSpace $eng;

    private WikiSpace $ops;

    /** @var list<string> page slugs the checker hides */
    private array $hidden = [];

    protected function setUp(): void
    {
        $this->em   = WikiTestDatabase::create();
        $this->user = new TestUserEntity('u-1');
        $this->eng  = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->ops  = new WikiSpace('ops', 'Operations', WikiSpaceOwnerScope::Team, 'team-2');
        foreach ([$this->user, $this->eng, $this->ops, new WikiSpace('other', 'Other tenant', WikiSpaceOwnerScope::Team, 'team-9')] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        $this->pages = new WikiPageService(new DoctrineOrmWikiPageRepository($this->em), new DoctrineOrmWikiPageRevisionRepository($this->em), new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher());
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
    }

    public function testSpacesFollowTheTenantTheSpaceCheckerTheScopeAndTheSlugs(): void
    {
        $finder = $this->finder();

        self::assertSame(['eng', 'ops'], $this->slugs($finder->spaces($this->user)));
        self::assertSame(['eng'], $this->slugs($finder->spaces($this->user, 'team-1')));
        self::assertSame([], $this->slugs($finder->spaces($this->user, 'team-9')));
        self::assertSame([], $this->slugs($finder->spaces($this->user, 'eng')));
        self::assertSame(['ops'], $this->slugs($finder->spaces($this->user, null, ['ops', 'other'])));
        self::assertSame(['eng'], $this->slugs($finder->spaces($this->user, null, [], ['ops'])));
        self::assertSame(['eng'], $this->slugs($this->finder(scopeMode: WikiReadablePageFinder::SCOPE_SLUG)->spaces($this->user, 'eng')));
        self::assertSame([], $this->slugs($this->finder(scopeMode: WikiReadablePageFinder::SCOPE_SLUG)->spaces($this->user, 'team-1')));

        // The space checker's View, and the role gate.
        self::assertSame(['eng'], $this->slugs($this->finder(static fn (WikiAction $action, WikiSpace $space): bool => $space->getSlug() === 'eng')->spaces($this->user)));
        $noList = $this->createMock(WikiAccessCheckerInterface::class);
        $noList->method('canList')->willReturn(false);
        self::assertSame([], $this->finder(roles: $noList)->spaces($this->user));
        self::assertEquals(new WikiReadablePageHits([], true), $this->finder(roles: $noList)->find($this->user, [], 'x'));
    }

    public function testFindKeepsOnlyReadablePagesAndSaysWhenTheScanIsComplete(): void
    {
        $parent = $this->page($this->eng, 'Hidden parent', 'nothing');
        $this->page($this->eng, 'Lumen child', 'lumen', $parent);
        $this->page($this->eng, 'Lumen visible', 'lumen');
        $this->page($this->ops, 'Lumen ops', 'lumen');
        $gone = $this->page($this->eng, 'Lumen archived', 'lumen');
        $this->pages->archive($gone);
        $this->hidden = [$parent->getSlug()];
        $this->em->clear();

        $finder = $this->finder();
        $hits   = $finder->find($this->user, $finder->spaces($this->user), 'LUMEN');
        self::assertTrue($hits->complete);
        self::assertSame(['Lumen ops', 'Lumen visible'], $this->titles($hits));
        self::assertSame(['Lumen visible'], $this->titles($finder->find($this->user, $finder->spaces($this->user, 'team-1'), 'lumen')));
        self::assertEquals(new WikiReadablePageHits([], true), $finder->find($this->user, $finder->spaces($this->user), '  '));
        self::assertEquals(new WikiReadablePageHits([], true), $finder->find($this->user, [], 'lumen'));
    }

    public function testTheCandidateScanIsBounded(): void
    {
        for ($i = 0; $i < 25; ++$i) {
            $page = $this->page($this->eng, sprintf('Flux %02d', $i), 'flux');
            if ($i % 2 === 0) {
                $this->hidden[] = $page->getSlug();
            }
        }
        $this->em->clear();

        $bounded = $this->finder(candidates: 10);
        $hits    = $bounded->find($this->user, $bounded->spaces($this->user), 'flux');
        // Ten title rows, then ten rows of every match (page-id order), the readable ones among them only, and the scan
        // says it stopped early.
        self::assertFalse($hits->complete);
        self::assertLessThanOrEqual(20, count($hits->hits));
        foreach ($hits->hits as $hit) {
            self::assertNotContains($hit['page']->getSlug(), $this->hidden);
        }

        $all  = $this->finder(candidates: 1000);
        $hits = $all->find($this->user, $all->spaces($this->user), 'flux');
        self::assertTrue($hits->complete);
        self::assertCount(12, $hits->hits);
    }

    /** 1.44 (audit TI-L2): title matches are read first, so body matches and hidden pages never crowd them out. */
    public function testTitleMatchesAreReadBeforeTheBudgetIsSpentOnBodyMatches(): void
    {
        for ($i = 0; $i < 15; ++$i) {
            $this->hidden[] = $this->page($this->eng, sprintf('Secret %02d', $i), 'garnet in the body')->getSlug();
            $this->page($this->eng, sprintf('Body %02d', $i), 'garnet in the body');
        }
        $this->page($this->eng, 'Garnet', 'title page');
        $this->em->clear();

        $finder = $this->finder(candidates: 10);
        $hits   = $finder->find($this->user, $finder->spaces($this->user), 'garnet');
        self::assertContains('Garnet', $this->titles($hits));
        self::assertFalse($hits->complete);
        // No page twice: the title pass and the body pass share what they read.
        $ids = array_map(static fn (array $hit): string => $hit['page']->getId(), $hits->hits);
        self::assertSame($ids, array_values(array_unique($ids)));
        foreach ($hits->hits as $hit) {
            self::assertNotContains($hit['page']->getSlug(), $this->hidden);
        }

        $search = new WikiSearchService($this->em);
        $spaces = $finder->spaces($this->user);
        self::assertSame([], $search->searchTitles($spaces, ' ', 10));
        self::assertSame([], $search->searchTitles([], 'garnet', 10));
        self::assertSame([], $search->searchTitles($spaces, 'garnet', 0));
        $first = $search->searchTitles($spaces, 'GARNET', 10, null, $last, $rows);
        self::assertSame(1, $rows);
        self::assertSame('', $first[0]['excerpt']);
        self::assertSame([], $search->searchTitles($spaces, 'garnet', 10, $last));
    }

    public function testAPageOverTheRenderersLimitsMatchesOnItsTitleOnly(): void
    {
        $this->page($this->eng, 'Huge page', 'needle in a huge body');
        $this->page($this->eng, 'Needle title', 'needle');
        $this->em->clear();
        $renderer = $this->createMockForIntersectionOfInterfaces([WikiMarkdownRendererInterface::class, WikiMarkdownLimitsInterface::class]);
        $renderer->method('exceedsLimits')->willReturnCallback(static fn (string $markdown): bool => str_starts_with($markdown, 'needle in'));

        $finder = $this->finder(renderer: $renderer);
        self::assertSame(['Needle title'], $this->titles($finder->find($this->user, $finder->spaces($this->user), 'needle')));
    }

    public function testPageIsTheLiveReadablePageOfAReadableSpaceOnly(): void
    {
        $page   = $this->page($this->eng, 'Recent', 'r');
        $parent = $this->page($this->eng, 'Closed parent', 'p');
        $child  = $this->page($this->eng, 'Under closed', 'c', $parent);
        $gone   = $this->page($this->eng, 'Archived one', 'a');
        $this->pages->archive($gone);
        $this->hidden = [$parent->getSlug()];
        $this->em->clear();
        $finder = $this->finder();

        self::assertSame($page->getId(), $finder->page($this->user, 'eng', $page->getSlug())?->getId());
        self::assertNull($finder->page($this->user, 'eng', $child->getSlug()));
        self::assertNull($finder->page($this->user, 'eng', $gone->getSlug()));
        self::assertNull($finder->page($this->user, 'eng', 'missing'));
        self::assertNull($finder->page($this->user, 'ops', $page->getSlug()));
        self::assertNull($finder->page($this->user, 'other', $page->getSlug()));
    }

    public function testWithASpaceScopedSearchPageHitsAcrossSpacesGetNoSeeAllLink(): void
    {
        foreach (['eng' => $this->eng, 'ops' => $this->ops] as $slug => $space) {
            $this->page($space, 'Ion ' . $slug, 'ion');
        }
        $this->em->clear();
        $routes = new RouteCollection();
        $routes->add('nowo_wiki_page_view', new Route('/wiki/{spaceSlug}/{pageSlug}'));
        $routes->add('nowo_wiki_search', new Route('/wiki/search'));
        $viewers = $this->createMock(WikiSearchViewerResolverInterface::class);
        $viewers->method('viewer')->willReturn($this->user);
        $urls  = new UrlGenerator($routes, new RequestContext());
        $query = new SearchQuery('ion', null, 1, userIdentifier: 'u-1');

        $scoped = new WikiPageSearchKitProvider($this->finder(), $viewers, $urls, new IdentityTranslator(), 'nowo_wiki_page_view', 'nowo_wiki_search', false);
        self::assertNull($scoped->search($query)?->moreUrl);
        $global = new WikiPageSearchKitProvider($this->finder(), $viewers, $urls, new IdentityTranslator(), 'nowo_wiki_page_view', 'nowo_wiki_search', true);
        self::assertSame('/wiki/search?q=ion', $global->search($query)?->moreUrl);
        $noRoute = new WikiPageSearchKitProvider($this->finder(), $viewers, $urls, new IdentityTranslator(), 'nowo_wiki_page_view');
        self::assertNull($noRoute->search($query)?->moreUrl);
    }

    /**
     * @param (Closure(WikiAction, WikiSpace): bool)|null $spaces the space-level decision (null: every space)
     */
    private function finder(?Closure $spaces = null, ?WikiAccessCheckerInterface $roles = null, int $candidates = 200, string $scopeMode = WikiReadablePageFinder::SCOPE_OWNER, ?WikiMarkdownRendererInterface $renderer = null): WikiReadablePageFinder
    {
        $readable = fn (WikiPage $page): bool => !in_array($page->getSlug(), $this->hidden, true);
        $checker  = new class($spaces, $readable) implements WikiSpaceAccessCheckerInterface {
            /**
             * @param (Closure(WikiAction, WikiSpace): bool)|null $spaces
             * @param Closure(WikiPage): bool $readable
             */
            public function __construct(private readonly ?Closure $spaces, private readonly Closure $readable)
            {
            }

            public function isGranted(UserInterface $user, WikiAction $action, WikiSpace $space, ?WikiPage $page = null): bool
            {
                if ($page instanceof WikiPage) {
                    return ($this->readable)($page);
                }

                return !$this->spaces instanceof Closure || ($this->spaces)($action, $space);
            }
        };
        $teams = $this->createMock(WikiTeamMembershipResolverInterface::class);
        $teams->method('getTeamIdsForUser')->willReturn(['team-1', 'team-2']);

        return new WikiReadablePageFinder(
            $roles ?? new AllowAllWikiAccessChecker(),
            new WikiSpaceAccessResolver(new DoctrineOrmWikiSpaceRepository($this->em), $teams, 'team'),
            $checker,
            new WikiSearchService($this->em),
            new DoctrineOrmWikiPageRepository($this->em),
            $renderer,
            $candidates,
            $scopeMode,
        );
    }

    private function page(WikiSpace $space, string $title, string $markdown, ?WikiPage $parent = null): WikiPage
    {
        $space  = $this->em->find(WikiSpace::class, $space->getId()) ?? $space;
        $author = $this->em->find(TestUserEntity::class, 'u-1') ?? $this->user;

        return $this->pages->create($space, $title, $markdown, $author, $parent instanceof WikiPage ? $this->em->find(WikiPage::class, $parent->getId()) : null, null, WikiPageRevision::FORMAT_MARKDOWN);
    }

    /**
     * @param list<WikiSpace> $spaces
     *
     * @return list<string>
     */
    private function slugs(array $spaces): array
    {
        $slugs = array_map(static fn (WikiSpace $space): string => $space->getSlug(), $spaces);
        sort($slugs);

        return $slugs;
    }

    /**
     * @return list<string>
     */
    private function titles(WikiReadablePageHits $hits): array
    {
        $titles = array_map(static fn (array $hit): string => $hit['page']->getTitle(), $hits->hits);
        sort($titles);

        return $titles;
    }
}
