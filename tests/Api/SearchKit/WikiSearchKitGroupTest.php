<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\SearchKit;

use Nowo\SearchKitBundle\Event\SearchItemVisibilityEvent;
use Nowo\SearchKitBundle\Event\SearchKitEvents;
use Nowo\SearchKitBundle\Model\SearchFilter;
use Nowo\SearchKitBundle\Model\SearchGroup;
use Nowo\SearchKitBundle\Model\SearchItem;
use Nowo\SearchKitBundle\Model\SearchQuery;
use Nowo\SearchKitBundle\Model\SearchReference;
use Nowo\SearchKitBundle\Test\QueryCounter;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiSearchSpacesEvent;
use Nowo\WikiBundle\LinkPicker\WikiTenantPathsLinkTenantResolver;
use Nowo\WikiBundle\Search\SearchKit\WikiPageSearchDefinition;
use Nowo\WikiBundle\Search\SearchKit\WikiPageSearchKitProvider;
use Nowo\WikiBundle\Tests\Api\ApiTestCase;
use Nowo\WikiBundle\Tests\Api\App\ApiTeamMembership;
use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;
use Nowo\WikiBundle\Tests\Api\SearchKitTestRequirement;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

use function array_map;
use function count;
use function sort;
use function sprintf;
use function str_contains;

/**
 * 1.46: what the move onto SearchKit's provider base adds or must keep, through a host kernel (SearchKit's bundle, the
 * wiki's resolver and checker, the container's event dispatcher), on SQLite and the CI's MySQL, MariaDB and PostgreSQL:
 * the host-rules hooks (SearchKit's item visibility event per page, the wiki's spaces event per space; deny wins, before
 * the window and the total), the literal match, title matches first in a small window, the query budget with nested
 * pages, the recents through the wiki's provider, the "see all" link and the label / excerpt rules.
 */
final class WikiSearchKitGroupTest extends ApiTestCase
{
    use SearchKitTestRequirement;

    /** @var array<string, mixed> */
    protected static array $wikiConfig = [
        'search_kit_provider'      => ['enabled' => true, 'candidates' => 10],
        'team_membership_resolver' => 'app.teams',
        'trash'                    => ['enabled' => true],
    ];

    /** @var list<callable> listeners added by a test, removed after it (the container is kept alive) */
    private array $listeners = [];

    protected function setUp(): void
    {
        self::requireSearchKit();
        parent::setUp();
        ApiTeamMembership::$teams              = ['u-ana' => ['owner-1', 'owner-2'], 'u-bob' => ['owner-1']];
        ApiTestHost::$grants['u-ana']['ops'][] = WikiAction::View;
    }

    protected function tearDown(): void
    {
        foreach ($this->listeners as [$name, $listener]) {
            $this->dispatcher()->removeListener($name, $listener);
        }
        $this->listeners = [];
        parent::tearDown();
    }

    public function testAHostDenyOfAPageWinsAndIsNeitherShownNorCounted(): void
    {
        $denied = $this->page($this->eng, 'Helium denied', 'helium');
        $this->page($this->eng, 'Helium kept', 'helium');
        $seen = [];
        $this->listen(SearchKitEvents::ITEM_VISIBILITY, static function (SearchItemVisibilityEvent $event) use ($denied, &$seen): void {
            $seen[] = $event->group . '|' . $event->tenant . '|' . $event->userIdentifier . '|' . count($event->subjects());
            foreach ($event->subjects() as $subject) {
                if ($subject->id === $denied->getId()) {
                    $event->deny($subject);
                }
            }
        });
        // A later listener allowing everything never undoes the deny.
        $this->listen(SearchKitEvents::ITEM_VISIBILITY, static function (SearchItemVisibilityEvent $event): void {
            foreach ($event->subjects() as $subject) {
                $event->allow($subject);
            }
        }, -10);

        $group = $this->search('u-bob', 'helium');

        self::assertSame(['Helium kept'], $this->titles($group));
        self::assertSame(1, $group?->total);
        self::assertSame(['wiki|' . WikiPageSearchDefinition::TENANT . '|u-bob|2'], $seen);
        // The recent of the denied page is dropped too; the kept one still resolves.
        self::assertNull($this->provider()->resolve($this->reference('eng', $denied->getSlug()), 'u-bob', null));
        self::assertInstanceOf(SearchItem::class, $this->provider()->resolve($this->reference('eng', $this->slugOf('Helium kept')), 'u-bob', null));
    }

    public function testTheSpacesEventNarrowsBeforeTheSqlAndAnExclusionWins(): void
    {
        $this->page($this->eng, 'Argon eng', 'argon');
        $ops = $this->page($this->ops, 'Argon ops', 'argon');
        /** @var list<WikiSearchSpacesEvent> $events */
        $events = [];
        $this->listen(WikiEvents::SEARCH_KIT_SPACES, static function (WikiSearchSpacesEvent $event) use (&$events): void {
            $events[] = $event;
            foreach ($event->spaces() as $space) {
                if ($space->space->slug === 'ops') {
                    $event->exclude($space->space->id);
                }
            }
            // Not a space of this search: ignored.
            $event->exclude('00000000-0000-4000-8000-000000000000');
            // Stopping is ignored: every listener runs.
            $event->stopPropagation();
        });
        $later = 0;
        $this->listen(WikiEvents::SEARCH_KIT_SPACES, static function (WikiSearchSpacesEvent $event) use (&$later): void {
            ++$later;
        }, -10);

        $group = $this->search('u-ana', 'argon', scope: null);

        self::assertSame(['Argon eng'], $this->titles($group));
        self::assertSame(1, $group?->total);
        self::assertSame(1, $later);
        self::assertCount(1, $events);
        self::assertSame('u-ana', $events[0]->userIdentifier);
        self::assertNull($events[0]->scope);
        self::assertSame('en', $events[0]->locale);
        $slugs = array_map(static fn ($space): string => $space->space->slug, $events[0]->spaces());
        sort($slugs);
        self::assertSame(['eng', 'ops'], $slugs);
        self::assertSame(WikiTenantPathsLinkTenantResolver::NO_TENANT_ROW, $events[0]->spaces()[0]->tenant);
        self::assertTrue($events[0]->isExcluded($ops->getSpace()->getId()));
        self::assertFalse($events[0]->isPropagationStopped());

        // The recents follow the same rule.
        self::assertNull($this->provider()->resolve($this->reference('ops', $ops->getSlug()), 'u-ana', null));

        // Everything excluded: no group at all.
        $this->listen(WikiEvents::SEARCH_KIT_SPACES, static function (WikiSearchSpacesEvent $event): void {
            $event->excludeAll();
        });
        self::assertNull($this->search('u-ana', 'argon'));
    }

    public function testTheTextMatchesLiterallyAsOnePhrase(): void
    {
        foreach (['Rate 100% done', 'Rate 1000 done', 'snake_case name', 'snakeXcase name', 'Bang! here', 'Back\\slash path', 'Back slashes path'] as $title) {
            $this->page($this->eng, $title, 'plain body');
        }
        $this->page($this->eng, 'Words apart', 'the quick red fox');

        self::assertSame(['Rate 100% done'], $this->titles($this->search('u-bob', '100%')));
        self::assertSame(['snake_case name'], $this->titles($this->search('u-bob', 'e_c')));
        self::assertSame(['Bang! here'], $this->titles($this->search('u-bob', 'g!')));
        self::assertSame(['Back\\slash path'], $this->titles($this->search('u-bob', 'k\\s')));
        self::assertNull($this->search('u-bob', '%%'));
        // One phrase, as the wiki's own search: words apart are no match (1.44 behaviour kept).
        self::assertSame(['Words apart'], $this->titles($this->search('u-bob', 'quick red')));
        self::assertNull($this->search('u-bob', 'quick fox'));
        // Case-insensitive on every platform.
        self::assertSame(['Words apart'], $this->titles($this->search('u-bob', 'QUICK RED')));
    }

    public function testARevisionNotBackfilledNeverMatchesOnItsMarkupAlone(): void
    {
        $page = $this->page($this->eng, 'Legacy page', 'old text');
        $this->wikiEm()->createQueryBuilder()->update(WikiPageRevision::class, 'r')
            ->set('r.contentText', 'NULL')
            ->set('r.contentHtml', ':html')
            ->where('r.page = :page')
            ->setParameter('html', '<p class="zinc-callout">Visible words</p>')
            ->setParameter('page', $page->getId())
            ->getQuery()
            ->execute();

        // The SQL finds the class attribute in the HTML cache; the wiki's own rule drops it (its text has no "zinc").
        self::assertNull($this->search('u-bob', 'zinc'));
        self::assertSame(['Legacy page'], $this->titles($this->search('u-bob', 'visible words')));
    }

    public function testTitleMatchesComeFirstInTheCandidateWindow(): void
    {
        $this->page($this->eng, 'Xenon handbook', 'start');
        for ($i = 0; $i < 12; ++$i) {
            $this->page($this->eng, sprintf('Note %d', $i), 'mentions xenon in the body');
        }

        $group = $this->search('u-bob', 'xenon', limit: 3);

        self::assertInstanceOf(SearchGroup::class, $group);
        self::assertSame('Xenon handbook', $group->items[0]->title);
        // The window (candidates: 10) is full of readable rows: SearchKit shows a lower bound.
        self::assertSame(10, $group->total);
        self::assertFalse($group->exactTotal);
    }

    public function testNoLabelOrTextOfAPageTheViewerCannotReadIsShown(): void
    {
        $secret = $this->page($this->eng, 'Classified roadmap', 'krypton launch codes');
        $child  = $this->page($this->eng, 'Krypton child notes', 'krypton under the secret', $secret);
        $open   = $this->page($this->eng, 'Open parent', 'nothing');
        $this->page($this->eng, 'Krypton public', 'krypton for all', $open);
        ApiTestHost::$hiddenPages = [$secret->getSlug()];

        $group = $this->search('u-bob', 'krypton');

        self::assertSame(['Krypton public'], $this->titles($group));
        self::assertSame(1, $group?->total);
        foreach ($group->items ?? [] as $item) {
            foreach (['Classified', 'launch codes', 'Krypton child', 'under the secret'] as $hidden) {
                self::assertFalse(str_contains($item->title . ' ' . $item->subtitle, $hidden), $hidden);
            }
        }
        // The title matches: no excerpt; the readable ancestor only.
        self::assertSame('Engineering · Open parent', $group?->items[0]->subtitle);
        self::assertNull($this->provider()->resolve($this->reference('eng', $child->getSlug()), 'u-bob', null));
    }

    public function testTheQueryBudgetDoesNotGrowWithTheRows(): void
    {
        $count = function (string $text): int {
            $counter = self::getContainer()->get('app.search_kit_counter');
            self::assertInstanceOf(QueryCounter::class, $counter);
            $this->signIn('u-bob');
            $counter->reset();
            self::assertInstanceOf(SearchGroup::class, $this->provider()->search(new SearchQuery($text, null, 10, userIdentifier: 'u-bob')));

            return $counter->count();
        };
        $this->page($this->eng, 'Neon one', 'x');
        $few = $count('neon');
        for ($i = 0; $i < 8; ++$i) {
            $this->page($this->eng, sprintf('Neon %d', $i), 'x');
        }
        self::assertSame($few, $count('neon'), 'Flat pages: the same queries for 1 row as for 9.');
        // The viewer twice (user provider), their spaces, the candidates, one fresh read of them with spaces and parents.
        self::assertLessThanOrEqual(5, $few);

        // Nested pages: one more query per two tree levels, whatever the number of rows.
        $root   = $this->page($this->eng, 'Root', 'r');
        $middle = $this->page($this->eng, 'Middle', 'm', $root);
        $leaf   = $this->page($this->eng, 'Leaf', 'l', $middle);
        $this->page($this->eng, 'Argo one', 'a', $leaf);
        $this->wikiEm()->clear();
        $one = $count('argo');
        for ($i = 0; $i < 5; ++$i) {
            $this->page($this->eng, sprintf('Argo %d', $i), 'a', $this->fresh($leaf));
        }
        $this->wikiEm()->clear();
        self::assertSame($one, $count('argo'), 'Nested pages: the same queries for 1 row as for 6.');
        self::assertLessThanOrEqual($few + 2, $one);
        self::assertSame('Engineering · Root › Middle › Leaf', $this->search('u-bob', 'argo one')?->items[0]->subtitle);
    }

    public function testRecentsResolveThroughTheWholeFlowForTheViewer(): void
    {
        $page      = $this->page($this->eng, 'Recent eng', 'r');
        $reference = $this->reference('eng', $page->getSlug());
        $this->signIn('u-bob');

        $item = $this->provider()->resolve($reference, 'u-bob', 'en');
        self::assertInstanceOf(SearchItem::class, $item);
        self::assertSame($reference->url, $item->url);
        self::assertSame('Recent eng', $item->title);
        self::assertSame('Engineering', $item->subtitle);
        self::assertNull($item->score);

        // A forged id parameter never wins over the one found for the viewer, and alone it resolves nothing.
        $other  = $this->page($this->eng, 'Other eng', 'o');
        $forged = new SearchReference($reference->url, 'wiki', 'nowo_wiki_page_view', [WikiPageSearchDefinition::REFERENCE_ID => $other->getId()] + $reference->routeParameters);
        self::assertSame($reference->url, $this->provider()->resolve($forged, 'u-bob', null)?->url);
        $definition = self::getContainer()->get('test.wiki_search_definition');
        self::assertInstanceOf(WikiPageSearchDefinition::class, $definition);
        self::assertNull($definition->referenceId(new SearchReference('/x', 'wiki', 'nowo_wiki_page_edit', [WikiPageSearchDefinition::REFERENCE_ID => $other->getId()])));
        self::assertNull($definition->referenceId(new SearchReference('/x', 'wiki', 'nowo_wiki_page_view', [WikiPageSearchDefinition::REFERENCE_ID => 'not-an-id'])));
        self::assertSame($other->getId(), $definition->referenceId(new SearchReference('/x', 'wiki', 'nowo_wiki_page_view', [WikiPageSearchDefinition::REFERENCE_ID => $other->getId()])));

        // Odd slugs: nothing is looked up.
        foreach ([['spaceSlug' => 'eng'], ['spaceSlug' => 'eng', 'pageSlug' => ''], ['spaceSlug' => "en\ng", 'pageSlug' => 'x'], ['spaceSlug' => ['eng'], 'pageSlug' => 'x']] as $parameters) {
            self::assertNull($this->provider()->resolve(new SearchReference($reference->url, 'wiki', 'nowo_wiki_page_view', $parameters), 'u-bob', null));
        }
    }

    public function testTheSameSlugsInTwoReadableSpacesResolveToTheirOwnPage(): void
    {
        $twin = new WikiSpace('eng', 'Engineering twin', WikiSpaceOwnerScope::Team, 'owner-2');
        $em   = $this->em();
        $em->persist($twin);
        $em->flush();
        ApiTestHost::$grants['u-ana']['eng'] = ApiTestHost::all();
        $first                               = $this->page($this->eng, 'Shared title', 'first');
        $second                              = $this->page($twin, 'Shared title', 'second');
        self::assertSame($first->getSlug(), $second->getSlug());

        $this->signIn('u-ana');
        $item = $this->provider()->resolve($this->reference('eng', $first->getSlug()), 'u-ana', null);

        // Both pages share the URL: the first one the viewer may read answers, as the page view does.
        self::assertInstanceOf(SearchItem::class, $item);
        self::assertSame($this->reference('eng', $first->getSlug())->url, $item->url);
    }

    public function testSeeAllOnlyWhereTheSearchPageAnswersTheSameQuestion(): void
    {
        $this->page($this->eng, 'Radon eng', 'radon');
        $this->page($this->ops, 'Radon ops', 'radon');

        self::assertSame($this->url('nowo_wiki_search', ['q' => 'radon']), $this->search('u-ana', 'radon')?->moreUrl);
        self::assertSame($this->url('nowo_wiki_search', ['space' => 'ops', 'q' => 'radon']), $this->search('u-ana', 'radon', filters: [new SearchFilter('space', 'ops')])?->moreUrl);
        // An owner scope, a negated space, two spaces: the search page cannot express them, so no link.
        self::assertNull($this->search('u-ana', 'radon', scope: 'owner-1')?->moreUrl);
        self::assertNull($this->search('u-ana', 'radon', filters: [new SearchFilter('space', 'ops', true)])?->moreUrl);
        self::assertNull($this->search('u-ana', 'radon', filters: [new SearchFilter('space', 'ops'), new SearchFilter('space', 'eng')])?->moreUrl);
        self::assertInstanceOf(SearchGroup::class, $this->search('u-ana', 'radon', scope: 'owner-1'));
    }

    public function testTheProviderDeclaresTheWikisCapabilities(): void
    {
        $provider = $this->provider();

        self::assertSame('wiki', $provider->groupKey());
        self::assertSame(['space'], $provider->supportedFilters());
        self::assertSame(WikiPageSearchKitProvider::GROUP, WikiPageSearchDefinition::GROUP);
        $this->page($this->eng, 'Paged one', 'paged');
        $this->signIn('u-bob');
        self::assertCount(1, $provider->searchPage(new SearchQuery('paged', null, 10, userIdentifier: 'u-bob'), 1, 5)->items ?? []);
    }

    private function listen(string $name, callable $listener, int $priority = 0): void
    {
        $this->dispatcher()->addListener($name, $listener, $priority);
        $this->listeners[] = [$name, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        return $dispatcher;
    }

    private function signIn(string $user): void
    {
        $entity = $this->em()->find(TestUserEntity::class, $user);
        self::assertInstanceOf(TestUserEntity::class, $entity);
        $tokens = self::getContainer()->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $tokens);
        $tokens->setToken(new UsernamePasswordToken($entity, 'main', ['ROLE_USER']));
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
    private function search(string $user, string $text, int $limit = 10, ?string $scope = null, array $filters = []): ?SearchGroup
    {
        $this->signIn($user);

        return $this->provider()->search(new SearchQuery($text, null, $limit, $scope, false, $user, $filters, 'en'));
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

    private function slugOf(string $title): string
    {
        $page = $this->wikiEm()->getRepository(WikiPage::class)->findOneBy(['title' => $title]);
        self::assertInstanceOf(WikiPage::class, $page);

        return $page->getSlug();
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
