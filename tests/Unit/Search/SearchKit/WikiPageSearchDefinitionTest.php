<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Search\SearchKit;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Nowo\SearchKitBundle\Model\SearchFilter;
use Nowo\SearchKitBundle\Model\SearchQuery;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Search\SearchKit\WikiPageSearchDefinition;
use Nowo\WikiBundle\Search\SearchKit\WikiSearchKitAvailability;
use Nowo\WikiBundle\Search\WikiReadablePageFinder;
use Nowo\WikiBundle\Search\WikiSearchTenantResolverInterface;
use Nowo\WikiBundle\Search\WikiSearchTenantsResolverInterface;
use Nowo\WikiBundle\Search\WikiSearchViewerResolverInterface;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Translation\IdentityTranslator;

use function array_map;
use function hash;
use function range;

/**
 * 1.46: the hooks of {@see WikiPageSearchDefinition} that need no database — the gates, the "see all" rule, the shapes.
 */
final class WikiPageSearchDefinitionTest extends TestCase
{
    protected function setUp(): void
    {
        if (!WikiSearchKitAvailability::isAvailable()) {
            self::markTestSkipped('nowo-tech/search-kit-bundle >= 1.10.1 is not installed (PHP >= 8.3).');
        }
    }

    public function testTheGatesAndDeclarations(): void
    {
        $definition = $this->definition();

        self::assertSame('wiki', $definition->group());
        self::assertSame('wiki.search_kit.group', $definition->label(SearchQuery::forUserIdentifier('u-1', 'x', 5)));
        // The current tenant (no resolver: none, so no results), or every tenant with `tenant: all`.
        self::assertNull($definition->tenant(SearchQuery::forUserIdentifier('u-1', 'x', 5)));
        self::assertSame(WikiPageSearchDefinition::TENANT_ALL, $this->definition(tenantMode: WikiPageSearchDefinition::TENANT_ALL_MODE)->tenant(SearchQuery::forUserIdentifier('u-1', 'x', 5)));
        $current = $this->createStub(WikiSearchTenantResolverInterface::class);
        $current->method('currentTenant')->willReturnMap([['u-1', 'org-1'], ['u-2', ''], ['u-3', WikiPageSearchDefinition::TENANT_ALL]]);
        self::assertSame('org-1', $this->definition(currentTenants: $current)->tenant(SearchQuery::forUserIdentifier('u-1', 'x', 5)));
        self::assertNull($this->definition(currentTenants: $current)->tenant(SearchQuery::forUserIdentifier('u-2', 'x', 5)));
        // Audit VN-1: a resolver answering the all-tenants marker in `current` mode searches nothing.
        self::assertNull($this->definition(currentTenants: $current)->tenant(SearchQuery::forUserIdentifier('u-3', 'x', 5)));
    }

    public function testAListOfCurrentTenants(): void
    {
        $lists = $this->createStub(WikiSearchTenantsResolverInterface::class);
        $lists->method('currentTenants')->willReturnMap([
            ['u-1', ['org-1', 'project-7', 'user-u-1', 'general']],
            ['u-2', ['general', 'user-u-1', 'project-7', 'org-1', 'org-1']],
            ['u-3', ['*', '', 'org-1']],
            ['u-4', ['*']],
            ['u-5', []],
            ['u-6', array_map(static fn (int $i): string => 'org-' . $i, range(0, WikiPageSearchDefinition::MAX_TENANTS))],
        ]);
        $definition = $this->definition(currentTenants: $lists);
        $tenant     = static fn (string $user): ?string => $definition->tenant(SearchQuery::forUserIdentifier($user, 'x', 5));

        // Several keys: one stable value whatever the order and duplicates; documented as the prefix + SHA-256.
        self::assertSame(WikiPageSearchDefinition::TENANTS_PREFIX . hash('sha256', '["general","org-1","project-7","user-u-1"]'), $tenant('u-1'));
        self::assertSame($tenant('u-1'), $tenant('u-2'));
        self::assertSame(['general', 'user-u-1', 'project-7', 'org-1'], $definition->currentKeys('u-2'));
        // `*` and empty keys are dropped: one key left, the key itself (as in 1.46).
        self::assertSame('org-1', $tenant('u-3'));
        self::assertNull($tenant('u-4'));
        self::assertNull($tenant('u-5'));
        // More than MAX_TENANTS keys: none (fail closed).
        self::assertSame([], $definition->currentKeys('u-6'));
        self::assertNull($tenant('u-6'));
        self::assertNull(WikiPageSearchDefinition::tenantId([]));
        self::assertTrue($definition->featureEnabled('*', SearchQuery::forUserIdentifier('u-1', 'x', 5)));
        self::assertFalse($this->definition(viewers: false)->featureEnabled('*', SearchQuery::forUserIdentifier('u-1', 'x', 5)));
        self::assertSame(['p.title', 'r.contentText'], $definition->searchableFields());
        self::assertSame('p.id', $definition->idField());
        self::assertSame(200, $definition->maxCandidates());
        self::assertFalse($definition->sensitive());
        self::assertNull($definition->icon());
        self::assertTrue($this->definition(sensitive: true)->sensitive());
    }

    public function testNoViewerSeesNoRow(): void
    {
        self::assertSame([], $this->definition(viewers: false)->filterVisible([['x']], '*', SearchQuery::forUserIdentifier('u-1', 'x', 5)));
        // A row that is not a page row of the wiki's query is never shown.
        self::assertSame([], $this->definition()->filterVisible([['x'], (object) []], '*', SearchQuery::forUserIdentifier('u-1', 'x', 5)));
    }

    public function testRowsThatAreNotPageRowsAreRefused(): void
    {
        $definition = $this->definition();

        $this->expectException(LogicException::class);
        $definition->subjectId(['id' => 'x']);
    }

    public function testItemsOfRowsThatAreNotPageRowsAreRefused(): void
    {
        $definition = $this->definition();

        $this->expectException(LogicException::class);
        $definition->toItem(['id' => 'x'], SearchQuery::forUserIdentifier('u-1', 'x', 5));
    }

    public function testSeeAllOnlyWhereTheSearchPageAnswersTheSameQuestion(): void
    {
        $query = static fn (?string $scope = null, array $filters = []): SearchQuery => new SearchQuery('ion', null, 5, $scope, false, 'u-1', $filters);

        self::assertSame('/wiki/search?q=ion', $this->definition()->seeAllUrl($query()));
        self::assertNull($this->definition(searchRoute: false)->seeAllUrl($query()));
        // search.scope: space — only with one named space, never one without a search page.
        self::assertNull($this->definition(global: false)->seeAllUrl($query()));
        self::assertSame('/wiki/search?space=eng&q=ion', $this->definition(global: false)->seeAllUrl($query(null, [new SearchFilter('space', 'eng')])));
        self::assertNull($this->definition(global: false, excluded: ['eng'])->seeAllUrl($query(null, [new SearchFilter('space', 'eng')])));
        // A slug scope names the space; with the same space filter still one; another space: none.
        $slug = WikiReadablePageFinder::SCOPE_SLUG;
        self::assertSame('/wiki/search?space=eng&q=ion', $this->definition(scopeMode: $slug)->seeAllUrl($query('eng')));
        self::assertSame('/wiki/search?space=eng&q=ion', $this->definition(scopeMode: $slug)->seeAllUrl($query('eng', [new SearchFilter('space', 'eng')])));
        self::assertNull($this->definition(scopeMode: $slug)->seeAllUrl($query('eng', [new SearchFilter('space', 'ops')])));
        // An owner scope, a negated space, a filter the wiki does not know: none.
        self::assertNull($this->definition()->seeAllUrl($query('owner-1')));
        self::assertNull($this->definition()->seeAllUrl($query(null, [new SearchFilter('space', 'eng', true)])));
        self::assertNull($this->definition()->seeAllUrl($query(null, [new SearchFilter('is', 'open')])));
    }

    public function testTenantIdsNeverCollide(): void
    {
        // Audit VN2-1: separators inside keys cannot shift between keys.
        self::assertNotSame(WikiPageSearchDefinition::tenantId(["a\nb", 'c']), WikiPageSearchDefinition::tenantId(['a', "b\nc"]));
        self::assertSame(WikiPageSearchDefinition::tenantId(['a', 'b']), WikiPageSearchDefinition::tenantId(['b', 'a']));
        self::assertSame('org-1', WikiPageSearchDefinition::tenantId(['org-1']));

        $lists = $this->createStub(WikiSearchTenantsResolverInterface::class);
        $lists->method('currentTenants')->willReturnMap([
            ['u-1', ['org-1', 'org-2']],
            ['u-2', ['tenants:' . hash('sha256', 'x'), 'tenants:']],
            ['u-3', ["bad\nkey", "tab\tkey", "nul\0key", "bell\x07", "c1\u{85}", "\xff\xfe", 'ok', 12]],
            ['u-4', ['10', '2', '10']],
        ]);
        $definition = $this->definition(currentTenants: $lists);
        $forged     = (string) WikiPageSearchDefinition::tenantId(['org-1', 'org-2']);

        // A single key shaped like a list id is dropped: it can never stand for that list.
        self::assertSame([], $definition->currentKeys('u-2'));
        self::assertNull($definition->tenant(SearchQuery::forUserIdentifier('u-2', 'x', 5)));
        self::assertSame($forged, $definition->tenant(SearchQuery::forUserIdentifier('u-1', 'x', 5)));
        // Control characters and invalid UTF-8: dropped.
        self::assertSame(['ok'], $definition->currentKeys('u-3'));
        self::assertSame('ok', $definition->tenant(SearchQuery::forUserIdentifier('u-3', 'x', 5)));
        // Numeric-looking keys stay strings, first occurrence order.
        self::assertSame(['10', '2'], $definition->currentKeys('u-4'));
        self::assertSame(WikiPageSearchDefinition::tenantId(['2', '10']), $definition->tenant(SearchQuery::forUserIdentifier('u-4', 'x', 5)));
    }

    /**
     * @param list<string> $excluded
     */
    private function definition(bool $viewers = true, bool $searchRoute = true, bool $global = true, array $excluded = [], string $scopeMode = WikiReadablePageFinder::SCOPE_OWNER, bool $sensitive = false, string $tenantMode = WikiPageSearchDefinition::TENANT_CURRENT, WikiSearchTenantResolverInterface|WikiSearchTenantsResolverInterface|null $currentTenants = null): WikiPageSearchDefinition
    {
        $routes = new RouteCollection();
        $routes->add('nowo_wiki_page_view', new Route('/wiki/{spaceSlug}/{pageSlug}'));
        $routes->add('nowo_wiki_search', new Route('/wiki/search'));
        $resolver = $this->createStub(WikiSearchViewerResolverInterface::class);
        $resolver->method('viewer')->willReturn(new InMemoryUser('u-1', null));
        $finder = new WikiReadablePageFinder(
            $this->createStub(WikiAccessCheckerInterface::class),
            $this->createStub(WikiSpaceAccessResolverInterface::class),
            $this->createStub(WikiSpaceAccessCheckerInterface::class),
            new WikiSearchService($this->createStub(EntityManagerInterface::class)),
            $this->createStub(WikiPageRepositoryInterface::class),
        );

        return new WikiPageSearchDefinition(
            $finder,
            $viewers ? $resolver : null,
            $this->createStub(WikiSpaceAccessCheckerInterface::class),
            new WikiSearchService($this->createStub(EntityManagerInterface::class)),
            $this->createStub(EntityManagerInterface::class),
            new UrlGenerator($routes, new RequestContext()),
            new IdentityTranslator(),
            'nowo_wiki_page_view',
            [],
            $searchRoute ? 'nowo_wiki_search' : null,
            $global,
            $excluded,
            $scopeMode,
            200,
            null,
            $sensitive,
            null,
            null,
            null,
            $tenantMode,
            $currentTenants,
        );
    }
}
