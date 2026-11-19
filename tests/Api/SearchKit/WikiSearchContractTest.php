<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\SearchKit;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\SearchKitBundle\Model\SearchReference;
use Nowo\SearchKitBundle\Provider\Entity\EntitySearchDefinition;
use Nowo\SearchKitBundle\Test\EntitySearchProviderTestCase;
use Nowo\SearchKitBundle\Test\QueryCounter;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Search\SearchKit\WikiPageSearchDefinition;
use Nowo\WikiBundle\Search\SearchKit\WikiSearchKitAvailability;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Tests\Api\App\ApiKernel;
use Nowo\WikiBundle\Tests\Api\App\ApiSearchTenants;
use Nowo\WikiBundle\Tests\Api\App\ApiTeamMembership;
use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

use function class_alias;
use function class_exists;

if (!class_exists(EntitySearchProviderTestCase::class)) {
    class_alias(SearchKitAbsentTestCase::class, EntitySearchProviderTestCase::class);
}

/**
 * 1.46: SearchKit's provider contract (EntitySearchProviderTestCase) against {@see WikiPageSearchDefinition},
 * on the API host kernel's database (SQLite, or WIKI_TEST_DATABASE_URL: MySQL, MariaDB, PostgreSQL in CI), outside any
 * request, on one kept-alive container (no kernel reset).
 *
 * A contract tenant is a host tenant (`search_kit_provider.tenant_resolver`): each tenant owns one space, and the viewer is
 * a member of both teams, so the current-tenant rule (default `tenant: current`) is what separates the tenants. Hidden rows are
 * pages the space checker refuses to the viewer (per page); archived rows are archived pages. SearchKit's generic
 * provider alone does not revalidate wiki recents (a recent names a page by its slugs, looked up for the viewer by
 * WikiPageSearchKitProvider first): {@see supportsRecents()} is false here, and the recents are covered through the
 * wiki's provider in WikiSearchKitGroupTest.
 */
final class WikiSearchContractTest extends EntitySearchProviderTestCase
{
    private const TENANT_A = 'kit-owner-a';

    private const TENANT_B = 'kit-owner-b';

    private ApiKernel $kernel;

    /** @var array<string, WikiSpace> tenant => its space */
    private array $spaces = [];

    /** @var array<string, TestUserEntity> */
    private array $users = [];

    /** Rows seeded so far (each one a second later than the previous one, so the newest-first order is total). */
    private int $seeded = 0;

    protected function setUp(): void
    {
        if (!class_exists(QueryCounter::class) || !WikiSearchKitAvailability::isAvailable()) {
            self::markTestSkipped('nowo-tech/search-kit-bundle >= 1.10.1 is not installed (PHP >= 8.3).');
        }
        ApiTestHost::reset();
        ApiSearchTenants::reset();
        ApiTeamMembership::$teams = [];
        $this->kernel             = new ApiKernel([], ['search_kit_provider' => ['enabled' => true, 'candidates' => 50, 'tenant_resolver' => 'app.search_tenants'], 'team_membership_resolver' => 'app.teams']);
        $this->kernel->boot();
        $em   = $this->em();
        $tool = new SchemaTool($em);
        $tool->dropDatabase();
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
        foreach ([self::VIEWER_A, self::VIEWER_B] as $identifier) {
            $this->users[$identifier] = new TestUserEntity($identifier);
            $em->persist($this->users[$identifier]);
        }
        foreach ([self::TENANT_A => 'kit-a', self::TENANT_B => 'kit-b'] as $tenant => $slug) {
            $this->spaces[$tenant] = new WikiSpace($slug, 'Kit ' . $slug, WikiSpaceOwnerScope::Team, $tenant);
            $em->persist($this->spaces[$tenant]);
            ApiSearchTenants::$spaces[$slug] = $tenant;
            foreach ([self::VIEWER_A, self::VIEWER_B] as $identifier) {
                ApiTestHost::$grants[$identifier][$slug] = [WikiAction::View];
            }
        }
        $em->flush();
    }

    protected function tearDown(): void
    {
        ApiTestHost::reset();
        ApiSearchTenants::reset();
        ApiTeamMembership::$teams = [];
        if (isset($this->kernel)) {
            $this->kernel->shutdown();
        }
        parent::tearDown();
    }

    protected function definition(): EntitySearchDefinition
    {
        $definition = $this->container()->get('test.wiki_search_definition');
        self::assertInstanceOf(EntitySearchDefinition::class, $definition);

        return $definition;
    }

    protected function queryCounter(): QueryCounter
    {
        $counter = $this->container()->get('app.search_kit_counter');
        self::assertInstanceOf(QueryCounter::class, $counter);

        return $counter;
    }

    /**
     * The viewer's user (user provider), their spaces (tenant / team resolver), the candidates, the viewer again for the
     * page checks, and one fresh read of the candidates with their spaces and parents.
     */
    protected function queryBudget(): int
    {
        return 5;
    }

    /**
     * SearchKit 1.10.1: the wiki filters in SQL everything it can decide itself — the tenant / team resolver, `View` on
     * the space, the host's spaces event, archived and trashed pages — but the page rule (`View` on the page and every
     * ancestor) is the host's PHP checker ({@see WikiSpaceAccessCheckerInterface}), so a page
     * it refuses still takes a place in the candidate window: documented residual (SECURITY.md, audit TI-L2).
     */
    protected function hiddenRowsFilteredInSql(): bool
    {
        return false;
    }

    protected function supportsRecents(): bool
    {
        return false;
    }

    protected function tenants(): array
    {
        return [self::TENANT_A, self::TENANT_B];
    }

    protected function seed(int|string $tenant, string $text): int|string
    {
        return $this->page($tenant, $text)->getId();
    }

    protected function seedHidden(int|string $tenant, string $text): int|string
    {
        $page                       = $this->page($tenant, $text);
        ApiTestHost::$hiddenPages[] = $page->getSlug();

        return $page->getId();
    }

    protected function seedArchived(int|string $tenant, string $text): int|string
    {
        $page = $this->page($tenant, $text);
        $this->pages()->archive($page);

        return $page->getId();
    }

    protected function reference(int|string $id): SearchReference
    {
        $page = $this->wikiEm()->find(WikiPage::class, $id);
        self::assertInstanceOf(WikiPage::class, $page);
        $parameters = ['spaceSlug' => $page->getSpace()->getSlug(), 'pageSlug' => $page->getSlug()];
        $router     = $this->container()->get('router');
        self::assertInstanceOf(UrlGeneratorInterface::class, $router);

        return new SearchReference($router->generate('nowo_wiki_page_view', $parameters), 'wiki', 'nowo_wiki_page_view', $parameters);
    }

    protected function actAs(int|string|null $tenant, string $userIdentifier): void
    {
        // The viewer is a member of both teams: only the current tenant (the request's) separates the tenants' rows.
        ApiTeamMembership::$teams[$userIdentifier]  = [self::TENANT_A, self::TENANT_B];
        ApiSearchTenants::$current[$userIdentifier] = $tenant === null ? null : (string) $tenant;
        $this->signIn($userIdentifier, ['ROLE_USER']);
    }

    protected function disableFeature(): void
    {
        $user = $this->tokens()->getToken()?->getUser();
        self::assertInstanceOf(TestUserEntity::class, $user);
        // The wiki's role gate (`list`) for the viewer: without the role, nothing.
        $this->signIn($user->getUserIdentifier(), []);
    }

    /**
     * @param list<string> $roles
     */
    private function signIn(string $userIdentifier, array $roles): void
    {
        $this->tokens()->setToken(new UsernamePasswordToken($this->users[$userIdentifier], 'main', $roles));
    }

    private function page(int|string $tenant, string $text): WikiPage
    {
        $em   = $this->wikiEm();
        $page = $this->pages()->create(
            $em->find(WikiSpace::class, $this->spaces[(string) $tenant]->getId()) ?? $this->spaces[(string) $tenant],
            $text,
            'Body of the page.',
            $em->find(TestUserEntity::class, self::VIEWER_A) ?? $this->users[self::VIEWER_A],
            null,
            // Titles repeat across the contract's rows (and slug alike, e.g. "x%a" / "x_a"): a slug of their own.
            'kit-row-' . ($this->seeded + 1),
            WikiPageRevision::FORMAT_MARKDOWN,
        );
        // One second after the previous row: the latest change comes first, whatever the clock's precision.
        $em->createQueryBuilder()->update(WikiPage::class, 'p')
            ->set('p.updatedAt', ':at')
            ->where('p.id = :id')
            ->setParameter('at', new DateTimeImmutable('2026-01-01 00:00:00 +' . ++$this->seeded . ' seconds'))
            ->setParameter('id', $page->getId())
            ->getQuery()
            ->execute();

        return $page;
    }

    private function pages(): WikiPageService
    {
        $service = $this->container()->get(WikiPageService::class);
        self::assertInstanceOf(WikiPageService::class, $service);

        return $service;
    }

    private function em(): EntityManagerInterface
    {
        $em = $this->container()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }

    private function wikiEm(): EntityManagerInterface
    {
        $provider = $this->container()->get(WikiEntityManagerProvider::class);
        self::assertInstanceOf(WikiEntityManagerProvider::class, $provider);

        return $provider->get();
    }

    private function tokens(): TokenStorageInterface
    {
        $tokens = $this->container()->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $tokens);

        return $tokens;
    }

    private function container(): ContainerInterface
    {
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }
}
