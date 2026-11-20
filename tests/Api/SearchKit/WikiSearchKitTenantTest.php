<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\SearchKit;

use Nowo\SearchKitBundle\Event\SearchItemVisibilityEvent;
use Nowo\SearchKitBundle\Event\SearchKitEvents;
use Nowo\SearchKitBundle\Model\SearchGroup;
use Nowo\SearchKitBundle\Model\SearchItem;
use Nowo\SearchKitBundle\Model\SearchQuery;
use Nowo\SearchKitBundle\Model\SearchReference;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiSearchSpacesEvent;
use Nowo\WikiBundle\Search\SearchKit\WikiPageSearchKitProvider;
use Nowo\WikiBundle\Tests\Api\ApiTestCase;
use Nowo\WikiBundle\Tests\Api\App\ApiSearchTenants;
use Nowo\WikiBundle\Tests\Api\App\ApiTeamMembership;
use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;
use Nowo\WikiBundle\Tests\Api\SearchKitTestRequirement;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

use function array_map;
use function sort;

/**
 * 1.46 (owner decision of 2026-11-19: the palette, like every quick-access list, shows the current tenant only): with
 * `search_kit_provider.tenant: current` (the default) the group searches only the spaces of the request's tenant, even
 * when the viewer may read spaces of other tenants. Ana reads `eng` (tenant org-1), `ops` (org-2) and `lab` (no tenant).
 */
class WikiSearchKitTenantTest extends ApiTestCase
{
    use SearchKitTestRequirement;

    /** @var array<string, mixed> */
    protected static array $wikiConfig = [
        'search_kit_provider'      => ['enabled' => true, 'tenant_resolver' => 'app.search_tenants'],
        'team_membership_resolver' => 'app.teams',
    ];

    protected WikiSpace $lab;

    /** @var list<array{string, callable}> */
    private array $listeners = [];

    protected function setUp(): void
    {
        self::requireSearchKit();
        parent::setUp();
        ApiSearchTenants::reset();
        $this->lab = new WikiSpace('lab', 'Laboratory', WikiSpaceOwnerScope::Team, 'owner-1');
        $this->em()->persist($this->lab);
        $this->em()->flush();
        ApiTeamMembership::$teams              = ['u-ana' => ['owner-1', 'owner-2']];
        ApiTestHost::$grants['u-ana']['ops'][] = WikiAction::View;
        ApiTestHost::$grants['u-ana']['lab']   = [WikiAction::View];
        ApiSearchTenants::$spaces              = ['eng' => 'org-1', 'ops' => 'org-2'];
        foreach (['eng' => $this->eng, 'ops' => $this->ops, 'lab' => $this->lab] as $slug => $space) {
            $this->page($space, 'Tenant page ' . $slug, 'tenant body');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->listeners as [$name, $listener]) {
            $this->dispatcher()->removeListener($name, $listener);
        }
        $this->listeners = [];
        ApiSearchTenants::reset();
        parent::tearDown();
    }

    public function testOnlyTheCurrentTenantsSpacesAreSearched(): void
    {
        ApiSearchTenants::$current['u-ana'] = 'org-1';
        self::assertSame(['Tenant page eng'], $this->titles($this->search()));
        self::assertSame(1, $this->search()?->total);

        ApiSearchTenants::$current['u-ana'] = 'org-2';
        self::assertSame(['Tenant page ops'], $this->titles($this->search()));

        // No tenant context, or a tenant with no readable space: nothing (a space without a tenant key never shows).
        ApiSearchTenants::$current['u-ana'] = null;
        self::assertNull($this->search());
        ApiSearchTenants::$current['u-ana'] = 'org-9';
        self::assertNull($this->search());
        // A `space:` filter or a scope never reaches outside the current tenant.
        ApiSearchTenants::$current['u-ana'] = 'org-1';
        self::assertNull($this->provider()->search(new SearchQuery('tenant', null, 10, 'owner-2', false, 'u-ana', [], 'en')));
    }

    public function testTheHostHooksSeeTheCurrentTenant(): void
    {
        ApiSearchTenants::$current['u-ana'] = 'org-1';
        $tenants                            = [];
        $spaces                             = [];
        $this->listen(SearchKitEvents::ITEM_VISIBILITY, static function (SearchItemVisibilityEvent $event) use (&$tenants): void {
            $tenants[] = $event->tenant;
        });
        $this->listen(WikiEvents::SEARCH_KIT_SPACES, static function (WikiSearchSpacesEvent $event) use (&$spaces): void {
            foreach ($event->spaces() as $space) {
                $spaces[] = $space->space->slug . '=' . $space->tenant;
            }
        });

        self::assertInstanceOf(SearchGroup::class, $this->search());
        self::assertSame(['org-1'], $tenants);
        self::assertSame(['eng=org-1'], $spaces);
    }

    public function testRecentsOfAnotherTenantAreDropped(): void
    {
        $ops = $this->reference('ops', $this->slug('Tenant page ops'));
        $this->signIn();

        ApiSearchTenants::$current['u-ana'] = 'org-1';
        self::assertNull($this->provider()->resolve($ops, 'u-ana', null));
        ApiSearchTenants::$current['u-ana'] = 'org-2';
        self::assertInstanceOf(SearchItem::class, $this->provider()->resolve($ops, 'u-ana', null));
    }

    protected function search(): ?SearchGroup
    {
        $this->signIn();

        return $this->provider()->search(new SearchQuery('tenant', null, 10, null, false, 'u-ana', [], 'en'));
    }

    /**
     * @return list<string>
     */
    protected function titles(?SearchGroup $group): array
    {
        $titles = array_map(static fn (SearchItem $item): string => $item->title, $group->items ?? []);
        sort($titles);

        return $titles;
    }

    protected function provider(): WikiPageSearchKitProvider
    {
        $provider = self::getContainer()->get('test.wiki_search_kit_provider');
        self::assertInstanceOf(WikiPageSearchKitProvider::class, $provider);

        return $provider;
    }

    protected function signIn(): void
    {
        $user = $this->em()->find(TestUserEntity::class, 'u-ana');
        self::assertInstanceOf(TestUserEntity::class, $user);
        $tokens = self::getContainer()->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $tokens);
        $tokens->setToken(new UsernamePasswordToken($user, 'main', ['ROLE_USER']));
    }

    protected function slug(string $title): string
    {
        $page = $this->wikiEm()->getRepository(WikiPage::class)->findOneBy(['title' => $title]);
        self::assertNotNull($page);

        return $page->getSlug();
    }

    protected function reference(string $space, string $page): SearchReference
    {
        $parameters = ['spaceSlug' => $space, 'pageSlug' => $page];
        $router     = self::getContainer()->get('router');
        self::assertInstanceOf(UrlGeneratorInterface::class, $router);

        return new SearchReference($router->generate('nowo_wiki_page_view', $parameters), 'wiki', 'nowo_wiki_page_view', $parameters);
    }

    protected function listen(string $name, callable $listener): void
    {
        $this->dispatcher()->addListener($name, $listener);
        $this->listeners[] = [$name, $listener];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        return $dispatcher;
    }
}
