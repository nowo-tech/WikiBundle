<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\SearchKitBundle\Model\SearchFilter;
use Nowo\SearchKitBundle\Model\SearchGroup;
use Nowo\SearchKitBundle\Model\SearchItem;
use Nowo\SearchKitBundle\Model\SearchQuery;
use Nowo\SearchKitBundle\Model\SearchReference;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Search\SearchKit\WikiPageSearchKitProvider;
use Nowo\WikiBundle\Tests\Api\App\ApiTeamMembership;
use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Trash\WikiTrashService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Twig\Environment;

use function array_map;
use function implode;
use function json_encode;
use function sort;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * 1.44 (Beacon KIT-85, SearchKit CONTRACT-240), 1.46 (SearchKit >= 1.10.1 provider base): the optional SearchKit group
 * through a host kernel (SearchKit's bundle, the security user provider, the wiki's tenant resolver and space checker,
 * the router, the translator), on SQLite and — through WIKI_TEST_DATABASE_URL — MySQL, MariaDB and PostgreSQL. Ana reads
 * `eng` (tenant owner-1) and `ops` (owner-2); Bob only `eng`. Every provider call runs outside any request, on one
 * kept-alive container: no `kernel.reset`, no services resetter (FrankenPHP worker mode with kernel reset false).
 */
final class SearchKitProviderTest extends ApiTestCase
{
    use SearchKitTestRequirement;

    /** @var array<string, mixed> */
    protected static array $wikiConfig = [
        'search_kit_provider'      => ['enabled' => true, 'candidates' => 50],
        'team_membership_resolver' => 'app.teams',
        'trash'                    => ['enabled' => true],
    ];

    protected function setUp(): void
    {
        self::requireSearchKit();
        parent::setUp();
        ApiTeamMembership::$teams              = ['u-ana' => ['owner-1', 'owner-2'], 'u-bob' => ['owner-1']];
        ApiTestHost::$grants['u-ana']['ops'][] = WikiAction::View;
    }

    public function testFindsReadablePagesWithTheirPathExcerptAndWikiLink(): void
    {
        $handbook = $this->page($this->eng, 'Ops handbook', 'Start here.');
        $guide    = $this->page($this->eng, 'Rollout guide', 'How to deploy the service safely.', $handbook);

        $group = $this->search('u-bob', 'deploy');

        self::assertInstanceOf(SearchGroup::class, $group);
        self::assertSame('wiki', $group->key);
        self::assertSame('Wiki', $group->label);
        self::assertSame(1, $group->total);
        self::assertTrue($group->exactTotal);
        // 1.46: SearchKit's provider base always offers the group's "see all" link: here the search page across spaces.
        self::assertSame($this->url('nowo_wiki_search', ['q' => 'deploy']), $group->moreUrl);
        $item = $group->items[0];
        self::assertSame('Rollout guide', $item->title);
        self::assertSame($this->url('nowo_wiki_page_view', ['spaceSlug' => 'eng', 'pageSlug' => $guide->getSlug()]), $item->url);
        self::assertSame('Engineering · Ops handbook · How to deploy the service safely.', $item->subtitle);
        self::assertNotNull($item->score);
        self::assertNotNull($item->date);
        self::assertNull($item->icon);

        // A title match needs no excerpt; the label follows the viewer's locale.
        $title = $this->search('u-bob', 'rollout', locale: 'es');
        self::assertInstanceOf(SearchGroup::class, $title);
        self::assertSame('Engineering · Ops handbook', $title->items[0]->subtitle);
        self::assertSame('Wiki', $title->label);
    }

    public function testAPageBelowAnUnreadableAncestorIsNotFound(): void
    {
        $secret = $this->page($this->eng, 'Secret parent', 'nothing');
        $this->page($this->eng, 'Quasar child', 'quasar notes', $secret);
        $this->page($this->eng, 'Quasar grandchild', 'quasar more', $this->page($this->eng, 'Middle', 'm', $secret));
        $this->page($this->eng, 'Quasar visible', 'quasar');
        ApiTestHost::$hiddenPages = [$secret->getSlug()];

        $group = $this->search('u-bob', 'quasar');

        self::assertInstanceOf(SearchGroup::class, $group);
        self::assertSame(['Quasar visible'], $this->titles($group));
        self::assertSame(1, $group->total);
        self::assertTrue($group->exactTotal);
        self::assertNull($this->search('u-bob', 'secret'));
    }

    public function testAnotherTenantsOrSpacesPageIsNotFound(): void
    {
        $this->page($this->eng, 'Nebula eng', 'nebula');
        $this->page($this->ops, 'Nebula ops', 'nebula');

        self::assertSame(['Nebula eng', 'Nebula ops'], $this->titles($this->search('u-ana', 'nebula')));
        // Bob's tenants hold no `ops`.
        self::assertSame(['Nebula eng'], $this->titles($this->search('u-bob', 'nebula')));
        // A tenant of Bob's that holds the space, without the space checker's View on it: still not found.
        ApiTeamMembership::$teams['u-bob'][] = 'owner-2';
        self::assertSame(['Nebula eng'], $this->titles($this->search('u-bob', 'nebula')));

        // Scope (owner id): another tenant's scope answers exactly like an unknown one.
        self::assertSame(['Nebula eng'], $this->titles($this->search('u-ana', 'nebula', scope: 'owner-1')));
        self::assertNull($this->search('u-bob', 'nebula', scope: 'owner-2'));
        self::assertNull($this->search('u-bob', 'nebula', scope: 'no-such-owner'));

        // Typed space filter: a forbidden space and an unknown one give the same empty answer.
        self::assertSame(['Nebula ops'], $this->titles($this->search('u-ana', 'nebula', filters: [new SearchFilter('space', 'ops')])));
        self::assertSame(['Nebula eng'], $this->titles($this->search('u-ana', 'nebula', filters: [new SearchFilter('space', 'ops', true)])));
        self::assertNull($this->search('u-bob', 'nebula', filters: [new SearchFilter('space', 'ops')]));
        self::assertNull($this->search('u-bob', 'nebula', filters: [new SearchFilter('space', 'nope')]));
        // A filter the wiki does not apply would widen the rows: none.
        self::assertNull($this->search('u-ana', 'nebula', filters: [new SearchFilter('is', 'open')]));
    }

    public function testArchivedAndTrashedPagesAreNotFound(): void
    {
        $this->page($this->eng, 'Comet live', 'comet');
        $archived = $this->page($this->eng, 'Comet archived', 'comet');
        $trashed  = $this->page($this->eng, 'Comet trashed', 'comet');
        $this->pages()->archive($this->fresh($archived));
        $trash = self::getContainer()->get(WikiTrashService::class);
        self::assertInstanceOf(WikiTrashService::class, $trash);
        $trash->delete($this->fresh($trashed));

        $group = $this->search('u-bob', 'comet');

        self::assertSame(['Comet live'], $this->titles($group));
        self::assertInstanceOf(SearchGroup::class, $group);
        self::assertSame(1, $group->total);
        $this->signIn('u-bob');
        foreach ([$archived, $trashed] as $page) {
            self::assertNull($this->provider()->resolve($this->reference('eng', $page->getSlug()), 'u-bob', null));
        }
    }

    public function testHostileTitlesAndExcerptsStayPlainText(): void
    {
        $title = '<img src=x onerror=alert(1)> Pulsar';
        $this->page($this->eng, $title, "Pulsar body <script>alert('x')</script> and <b onclick=\"steal()\">bold</b> **strong** [link](javascript:alert(1))");

        $group = $this->search('u-bob', 'body');

        self::assertInstanceOf(SearchGroup::class, $group);
        $item = $group->items[0];
        // The title is the stored text, never markup the wiki rendered: SearchKit escapes it (DOM text nodes, Twig).
        self::assertSame($title, $item->title);
        // The excerpt is the wiki search's plain text — what the page shows as text: raw HTML of the source is text there
        // too, while Markdown punctuation and link targets are gone. Never markup to insert.
        self::assertSame("Engineering · Pulsar body <script>alert('x')</script> and <b onclick=\"steal()\">bold</b> strong link", $item->subtitle);
        foreach (['**', 'javascript:', '](', '[link'] as $needle) {
            self::assertStringNotContainsString($needle, $item->subtitle);
        }
        // Shown the way SearchKit's results page does (Twig, autoescape): inert text.
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $html = $twig->createTemplate('<a href="{{ item.url }}">{{ item.title }}</a><p>{{ item.subtitle }}</p>')->render(['item' => $item]);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('<b ', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt; Pulsar', $html);
        self::assertStringStartsWith('/', $item->url);
    }

    public function testTotalsAndPagesDoNotRevealHiddenPages(): void
    {
        foreach (['Orbit one', 'Orbit two', 'Orbit three'] as $visible) {
            $this->page($this->eng, $visible, 'orbit');
        }
        $hidden = [];
        for ($i = 0; $i < 4; ++$i) {
            $hidden[] = $this->page($this->eng, sprintf('Orbit hidden %d', $i), 'orbit')->getSlug();
            $this->page($this->ops, sprintf('Orbit ops %d', $i), 'orbit');
        }
        ApiTestHost::$hiddenPages = $hidden;

        $withHidden = $this->search('u-bob', 'orbit', limit: 2);
        self::assertInstanceOf(SearchGroup::class, $withHidden);
        self::assertSame(3, $withHidden->total);
        self::assertTrue($withHidden->exactTotal);
        self::assertCount(2, $withHidden->items);
        // 1.46: the search page across spaces (search.scope: all), labelled by SearchKit.
        self::assertSame($this->url('nowo_wiki_search', ['q' => 'orbit']), $withHidden->moreUrl);
        self::assertSame('See all', $withHidden->moreLabel);

        // The results page: no gap, no hidden row, an empty page past the end.
        $provider = $this->provider();
        $this->signIn('u-bob');
        $query  = new SearchQuery('orbit', null, 2, userIdentifier: 'u-bob');
        $first  = $provider->searchPage($query, 1, 2);
        $second = $provider->searchPage($query, 2, 2);
        self::assertInstanceOf(SearchGroup::class, $first);
        self::assertInstanceOf(SearchGroup::class, $second);
        self::assertCount(2, $first->items);
        self::assertCount(1, $second->items);
        self::assertNull($provider->searchPage($query, 3, 2));
        self::assertSame('wiki', $provider->groupKey());

        // Byte for byte what a viewer sees when the hidden pages never existed.
        $before = json_encode($withHidden->toArray(), JSON_THROW_ON_ERROR);
        $em     = $this->wikiEm();
        foreach ($hidden as $slug) {
            $page = $em->getRepository(WikiPage::class)->findOneBy(['slug' => $slug]);
            self::assertInstanceOf(WikiPage::class, $page);
            $this->pages()->archive($page);
        }
        ApiTestHost::$hiddenPages = [];
        self::assertSame($before, json_encode($this->search('u-bob', 'orbit', limit: 2)?->toArray(), JSON_THROW_ON_ERROR));

        // Across spaces with search.scope: all, the search page across spaces; one `space:` filter: that space's page.
        self::assertSame($this->url('nowo_wiki_search', ['q' => 'orbit']), $this->search('u-ana', 'orbit', limit: 2)?->moreUrl);
        self::assertSame($this->url('nowo_wiki_search', ['space' => 'eng', 'q' => 'orbit']), $this->search('u-ana', 'orbit', limit: 2, filters: [new SearchFilter('space', 'eng')])?->moreUrl);
    }

    public function testRecentsAreReCheckedForTheViewerNow(): void
    {
        $page      = $this->page($this->eng, 'Recent page', 'r');
        $reference = $this->reference('eng', $page->getSlug());
        $provider  = $this->provider();
        $this->signIn('u-bob');

        $item = $provider->resolve($reference, 'u-bob', 'en');
        self::assertInstanceOf(SearchItem::class, $item);
        self::assertSame('Recent page', $item->title);
        self::assertSame($reference->url, $item->url);
        self::assertSame('Engineering', $item->subtitle);

        // Forbidden now, unknown, another kind, another route with the same parameters, another space: the same null.
        ApiTestHost::$hiddenPages = [$page->getSlug()];
        self::assertNull($provider->resolve($reference, 'u-bob', null));
        ApiTestHost::$hiddenPages = [];
        self::assertNull($provider->resolve($reference, 'u-nobody', null));
        self::assertNull($provider->resolve($reference, '', null));
        self::assertNull($provider->resolve($this->reference('eng', 'no-such-page'), 'u-bob', null));
        $this->signIn('u-ana');
        self::assertNull($provider->resolve($this->reference('ops', $page->getSlug()), 'u-ana', null));
        $this->signIn('u-bob');
        self::assertNull($provider->resolve(new SearchReference($reference->url, 'tasks', 'nowo_wiki_page_view', $reference->routeParameters), 'u-bob', null));
        self::assertNull($provider->resolve(new SearchReference($reference->url, 'wiki', 'nowo_wiki_page_view', ['spaceSlug' => 'eng']), 'u-bob', null));
        $edit = $this->url('nowo_wiki_page_edit', $reference->routeParameters);
        self::assertNull($provider->resolve(new SearchReference($edit, 'wiki', 'nowo_wiki_page_edit', $reference->routeParameters), 'u-bob', null));
    }

    public function testDenyByDefault(): void
    {
        $this->page($this->eng, 'Vortex', 'vortex');

        // Unknown viewer, no text, no tenant, no View on any space: no group (not an empty one).
        self::assertNull($this->search('u-nobody', 'vortex'));
        // A look-alike identifier (a case- or pad-folding lookup could load u-bob): the identifier must match exactly.
        self::assertNull($this->search('U-BOB', 'vortex'));
        self::assertNull($this->search('u-bob ', 'vortex'));
        self::assertNull($this->search('u-bob', '   '));
        ApiTeamMembership::$teams['u-bob'] = [];
        self::assertNull($this->search('u-bob', 'vortex'));
        ApiTeamMembership::$teams['u-bob']   = ['owner-1'];
        ApiTestHost::$grants['u-bob']['eng'] = [];
        self::assertNull($this->search('u-bob', 'vortex'));
        self::assertSame(['space'], $this->provider()->supportedFilters());

        // No session at all (a CLI run of searchFor()): the default role gate reads the token, so nothing is found.
        ApiTestHost::$grants['u-bob']['eng'] = [WikiAction::View];
        self::assertInstanceOf(SearchGroup::class, $this->search('u-bob', 'vortex'));
        $tokens = self::getContainer()->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $tokens);
        $tokens->setToken(null);
        self::assertNull($this->provider()->search(new SearchQuery('vortex', null, 10, userIdentifier: 'u-bob')));
    }

    /** 1.44 (audit TI-M1): inside another person's session the role gate is the viewer's: a roleless session still finds Bob's pages. */
    public function testASearchForAnotherIdentifierUsesTheViewersRolesNotTheSessions(): void
    {
        $this->page($this->eng, 'Quark', 'quark');
        $tokens = self::getContainer()->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $tokens);
        $query = new SearchQuery('quark', null, 10, userIdentifier: 'u-bob');

        foreach ([[], ['ROLE_USER']] as $roles) {
            $session = new InMemoryUser('someone-else', null, $roles);
            $tokens->setToken(new UsernamePasswordToken($session, 'main', $roles));
            self::assertSame(['Quark'], $this->titles($this->provider()->search($query)), implode(',', $roles));
        }
        // Bob's own session without the role: the session's token decides, as on the wiki's pages.
        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('u-bob', null, []), 'main', []));
        self::assertNull($this->provider()->search($query));
    }

    public function testOneKeptAliveContainerCarriesNoViewerStateBetweenSearches(): void
    {
        $this->page($this->eng, 'Worker eng', 'worker');
        $ops      = $this->page($this->ops, 'Worker ops', 'worker');
        $provider = $this->provider();

        for ($round = 0; $round < 3; ++$round) {
            self::assertSame(['Worker eng', 'Worker ops'], $this->titles($this->searchWith($provider, 'u-ana', 'worker')), (string) $round);
            self::assertSame(['Worker eng'], $this->titles($this->searchWith($provider, 'u-bob', 'worker')), (string) $round);
            // Rights change between two searches of the same worker: the next search follows them at once.
            ApiTeamMembership::$teams['u-bob'][] = 'owner-2';
            ApiTestHost::$grants['u-bob']['ops'] = [WikiAction::View];
            self::assertSame(['Worker eng', 'Worker ops'], $this->titles($this->searchWith($provider, 'u-bob', 'worker')), (string) $round);
            ApiTeamMembership::$teams['u-bob'] = ['owner-1'];
            unset(ApiTestHost::$grants['u-bob']['ops']);
            self::assertSame(['Worker eng'], $this->titles($this->searchWith($provider, 'u-bob', 'worker')), (string) $round);
        }
        self::assertSame($provider, $this->provider());

        $this->pages()->archive($this->fresh($ops));
        self::assertSame(['Worker eng'], $this->titles($this->searchWith($provider, 'u-ana', 'worker')));
    }

    /**
     * The session of the person searching, as SearchKit's endpoint has it: the wiki's role gate (`list`, the default
     * ConfigurableWikiAccessChecker) reads the security token.
     */
    private function signIn(string $user): void
    {
        $entity = $this->em()->find(TestUserEntity::class, $user);
        $person = $entity ?? new InMemoryUser($user, null, ['ROLE_USER']);
        $tokens = self::getContainer()->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $tokens);
        $tokens->setToken(new UsernamePasswordToken($person, 'main', ['ROLE_USER']));
    }

    private function provider(): WikiPageSearchKitProvider
    {
        $provider = self::getContainer()->get('test.wiki_search_kit_provider');
        self::assertInstanceOf(WikiPageSearchKitProvider::class, $provider);

        return $provider;
    }

    /**
     * @param list<SearchFilter> $filters
     */
    private function search(string $user, string $text, int $limit = 10, ?string $scope = null, array $filters = [], ?string $locale = 'en'): ?SearchGroup
    {
        $this->signIn($user);

        return $this->provider()->search(new SearchQuery($text, null, $limit, $scope, false, $user, $filters, $locale));
    }

    /** One search of the kept-alive provider, in the person's session. */
    private function searchWith(WikiPageSearchKitProvider $provider, string $user, string $text): ?SearchGroup
    {
        $this->signIn($user);

        return $provider->search(new SearchQuery($text, null, 10, userIdentifier: $user));
    }

    /**
     * @return list<string>
     */
    private function titles(?SearchGroup $group): array
    {
        $titles = array_map(static fn (SearchItem $item): string => $item->title, $group->items ?? []);
        sort($titles);

        return $titles;
    }

    private function reference(string $space, string $page): SearchReference
    {
        $parameters = ['spaceSlug' => $space, 'pageSlug' => $page];

        return new SearchReference($this->url('nowo_wiki_page_view', $parameters), 'wiki', 'nowo_wiki_page_view', $parameters);
    }

    /**
     * @param array<string, scalar|null> $parameters
     */
    private function url(string $route, array $parameters): string
    {
        $router = self::getContainer()->get('router');
        self::assertInstanceOf(UrlGeneratorInterface::class, $router);

        return $router->generate($route, $parameters);
    }

    private function fresh(WikiPage $page): WikiPage
    {
        $found = $this->wikiEm()->find(WikiPage::class, $page->getId());
        self::assertInstanceOf(WikiPage::class, $found);

        return $found;
    }
}
