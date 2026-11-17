<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\LinkPicker;

use Doctrine\ORM\EntityManager;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\AllowlistIconResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\NullPageResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\NullUserResolver;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\LinkPicker\ConfiguredWikiLinkPickerSettings;
use Nowo\WikiBundle\LinkPicker\WikiLinkPickerService;
use Nowo\WikiBundle\LinkPicker\WikiTenantPathsLinkTenantResolver;
use Nowo\WikiBundle\Markdown\Milkdown\MilkdownWikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\WikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface;
use Nowo\WikiBundle\Markdown\WikiRenderContext;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Routing\WikiTenantPaths;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\LinkPickerTestAccess;
use Nowo\WikiBundle\Tests\Support\LinkPickerTestSpaces;
use Nowo\WikiBundle\Tests\Support\LinkPickerTestUrls;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_column;
use function array_map;
use function array_slice;
use function count;
use function memory_get_usage;
use function str_repeat;

/**
 * 1.40 (contract §3.1, §5; W2): the link picker's candidates and sections — title-only matches, the current wiki first,
 * other wikis of the same tenant only, hidden ancestors, unreadable and excluded spaces, `search.scope: space` and
 * `cross_wiki`, and the same empty answer for "no match" and "not allowed".
 */
final class WikiLinkPickerServiceTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $eng;

    private WikiSpace $ops;

    private WikiSpace $hr;

    private WikiSpace $foreign;

    private TestUser $user;

    /** @var array<string, WikiPage> */
    private array $pages = [];

    private bool $canList = true;

    protected function setUp(): void
    {
        $this->em      = SqliteWikiEntityManager::create();
        $this->eng     = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 't');
        $this->ops     = new WikiSpace('ops', 'Operations', WikiSpaceOwnerScope::Team, 't');
        $this->hr      = new WikiSpace('hr', 'People', WikiSpaceOwnerScope::Team, 't');
        $this->foreign = new WikiSpace('x-acme', 'Acme', WikiSpaceOwnerScope::Team, 'other');
        $this->user    = new TestUser();
        foreach ([$this->eng, $this->ops, $this->hr, $this->foreign] as $space) {
            $this->em->persist($space);
        }
        $this->page($this->eng, 'install', 'Install guide', "## Set up {#setup}\n\n:::details{summary=\"More\"}\n### Inside\n:::\n\n## Done");
        $this->page($this->eng, 'notes', 'Installer notes');
        $this->page($this->eng, 'percent', '100% install_ notes');
        $private = $this->page($this->eng, 'private', 'Private install');
        $child   = $this->page($this->eng, 'child', 'Install child');
        $child->moveTo($private, 0);
        $this->page($this->eng, 'old', 'Install archive')->archive();
        $this->page($this->ops, 'ops-install', 'Install ops');
        $this->page($this->ops, 'ops-other', 'Runbook');
        $this->page($this->hr, 'hr-install', 'Install people');
        $this->page($this->foreign, 'acme-install', 'Install acme');
        $this->em->flush();
    }

    public function testTheCurrentWikiFirstThenOtherWikisOfTheSameTenant(): void
    {
        $answer = $this->service()->candidates($this->user, $this->eng, 'install');

        self::assertSame(['Install guide', 'Installer notes', '100% install_ notes'], array_column($answer['here'], 'label'));
        self::assertSame('/wiki/eng/install', $answer['here'][0]['url']);
        self::assertSame($this->pages['install']->getId(), $answer['here'][0]['id']);
        // hr is not readable, Acme is another tenant: neither is named.
        self::assertSame([['wiki' => 'Operations', 'pages' => [['id' => $this->pages['ops-install']->getId(), 'label' => 'Install ops', 'url' => '/wiki/ops/ops-install']]]], $answer['others']);
    }

    public function testMatchesAreLiteralAndTitleOnly(): void
    {
        $service = $this->service();

        self::assertSame(['100% install_ notes'], array_column($service->candidates($this->user, $this->eng, '0% install_')['here'], 'label'));
        self::assertSame([], $service->candidates($this->user, $this->eng, 'Set up')['here']);
        self::assertSame([], $service->candidates($this->user, $this->eng, 'Inside')['here']);
    }

    public function testAnEmptyQueryGivesRecentPagesOfTheCurrentWikiOnly(): void
    {
        $answer = $this->service()->candidates($this->user, $this->eng, "  \u{202E}\x07 ");

        self::assertCount(3, $answer['here']);
        self::assertSame([], $answer['others']);
        self::assertSame([], $this->service()->candidates($this->user, $this->eng, 'i')['others']);
    }

    public function testNoMatchAndNotAllowedGiveTheSameAnswer(): void
    {
        $service = $this->service();

        self::assertSame(WikiLinkPickerService::EMPTY, $service->candidates($this->user, $this->eng, 'nothing like it'));
        self::assertSame(WikiLinkPickerService::EMPTY, $service->candidates($this->user, null, 'install'));
        self::assertSame(WikiLinkPickerService::EMPTY, $service->candidates($this->user, $this->hr, 'install'));
        $this->canList = false;
        self::assertSame(WikiLinkPickerService::EMPTY, $service->candidates($this->user, $this->eng, 'install'));
        self::assertNull($service->sections($this->user, $this->eng, $this->pages['install']->getId()));
    }

    public function testScopeCrossWikiTenantOnlyAndExcludedSpaces(): void
    {
        self::assertSame([], $this->service(scope: 'space')->candidates($this->user, $this->eng, 'install')['others']);
        self::assertSame([], $this->service(crossWiki: false)->candidates($this->user, $this->eng, 'install')['others']);
        self::assertSame(WikiLinkPickerService::EMPTY, $this->service(scope: 'space', excluded: ['eng'])->candidates($this->user, $this->eng, 'install'));
        self::assertNull($this->service(scope: 'space', excluded: ['eng'])->sections($this->user, $this->eng, $this->pages['install']->getId()));
        // tenant_only: a space that matches no tenant row has no tenant, so no other wiki.
        $tenantOnly = $this->service(tenantPaths: new WikiTenantPaths([['path' => '/x/{spaceSlug}', 'name_prefix' => 'x_', 'slug_requirement' => 'x-[a-z]+']], [], true));
        self::assertSame([], $tenantOnly->candidates($this->user, $this->eng, 'install')['others']);
        // Without tenant paths every space is one tenant.
        self::assertSame(['Acme', 'Operations'], array_column($this->service(tenantPaths: null)->candidates($this->user, $this->eng, 'install')['others'], 'wiki'));
    }

    public function testOtherWikisAreCapped(): void
    {
        $spaces = [];
        for ($i = 1; $i <= 7; ++$i) {
            $spaces[] = $space = new WikiSpace('team-' . $i, 'Team ' . $i, WikiSpaceOwnerScope::Team, 't');
            $this->em->persist($space);
            for ($j = 1; $j <= 4; ++$j) {
                $this->page($space, 'p' . $i . '-' . $j, 'Zeta ' . $i . '-' . $j);
            }
            $this->page($space, 'o' . $i, 'Omega ' . $i);
        }
        for ($k = 1; $k <= 12; ++$k) {
            $this->page($this->eng, 'alpha-' . $k, 'Alpha ' . $k);
        }
        $this->em->flush();

        $others = $this->service(tenantPaths: null, spaces: $spaces)->candidates($this->user, $this->eng, 'zeta')['others'];

        self::assertLessThanOrEqual(WikiLinkPickerService::MAX_WIKIS, count($others));
        self::assertSame([3, 3, 3, 1], array_map(static fn (array $group): int => count($group['pages']), $others));
        // One hit in each of seven wikis: five are named.
        self::assertSame(['Team 1', 'Team 2', 'Team 3', 'Team 4', 'Team 5'], array_column($this->service(tenantPaths: null, spaces: $spaces)->candidates($this->user, $this->eng, 'omega')['others'], 'wiki'));
        self::assertCount(WikiLinkPickerService::MAX_HERE, $this->service()->candidates($this->user, $this->eng, 'alpha')['here']);
    }

    public function testSectionsComeFromTheViewersRender(): void
    {
        $service = $this->service();

        self::assertSame([
            ['id' => 'setup', 'level' => 2, 'text' => 'Set up'],
            ['id' => 'inside', 'level' => 3, 'text' => 'Inside'],
            ['id' => 'done', 'level' => 2, 'text' => 'Done'],
        ], $service->sections($this->user, $this->eng, $this->pages['install']->getId()));
        // Another wiki of the same tenant; a page without a revision.
        self::assertSame([], $service->sections($this->user, $this->eng, $this->pages['ops-install']->getId()));
    }

    public function testSectionsOfPagesOutOfReachAreNotFound(): void
    {
        $service = $this->service();

        foreach (['child', 'old', 'hr-install', 'acme-install'] as $slug) {
            self::assertNull($service->sections($this->user, $this->eng, $this->pages[$slug]->getId()), $slug);
        }
        self::assertNull($service->sections($this->user, $this->eng, '00000000-0000-4000-8000-000000000000'));
        self::assertNull($service->sections($this->user, null, $this->pages['install']->getId()));
        self::assertNull($this->service(crossWiki: false)->sections($this->user, $this->eng, $this->pages['ops-install']->getId()));
    }

    public function testAnHtmlRevisionOrANeutralRendererUsesTheStoredHtml(): void
    {
        $revision = new WikiPageRevision($this->pages['notes'], 1, '<h2 id="nowo-h-a">A</h2>', null);
        $this->pages['notes']->setCurrentRevision($revision);
        $this->em->persist($revision);
        $this->em->flush();

        self::assertSame([['id' => 'a', 'level' => 2, 'text' => 'A']], $this->service()->sections($this->user, $this->eng, $this->pages['notes']->getId()));
        // Audit RY-I2: the stored (viewer-neutral) HTML gives top-level headings only.
        $nested = new WikiPageRevision($this->pages['notes'], 2, '<h2 id="nowo-h-a">A</h2><details><h3 id="nowo-h-b">B</h3></details>', null);
        $this->pages['notes']->setCurrentRevision($nested);
        $this->em->persist($nested);
        $this->em->flush();
        self::assertSame(['a'], array_column($this->service()->sections($this->user, $this->eng, $this->pages['notes']->getId()) ?? [], 'id'));
        self::assertSame([], $this->service(renderer: new WikiMarkdownRenderer())->sections($this->user, $this->eng, $this->pages['install']->getId()));
    }

    public function testAHostRepositoryWithoutTheFinder(): void
    {
        $pages = [];
        foreach ($this->pages as $page) {
            $pages[$page->getSpace()->getId()][] = $page;
        }
        $repository = $this->createStub(WikiPageRepositoryInterface::class);
        $repository->method('findActiveBySpace')->willReturnCallback(static fn (WikiSpace $space): array => array_values(array_filter($pages[$space->getId()] ?? [], static fn (WikiPage $page): bool => !$page->isArchived())));

        $service = $this->service(repository: $repository);

        self::assertSame(['Install guide', 'Installer notes', '100% install_ notes'], array_column($service->candidates($this->user, $this->eng, 'install')['here'], 'label'));
        self::assertCount(3, $service->candidates($this->user, $this->eng, '')['here']);

        // Audit RY-L5: more than the 50 rows of a page of answers are read before the title filter.
        $many = [];
        for ($i = 1; $i <= 120; ++$i) {
            $many[] = new WikiPage($this->eng, 'p' . $i, $i === 120 ? 'Needle' : 'Other ' . $i);
        }
        $big = $this->createStub(WikiPageRepositoryInterface::class);
        $big->method('findActiveBySpace')->willReturnCallback(static fn (WikiSpace $space, ?int $limit = null): array => array_slice($many, 0, $limit));
        self::assertSame(['Needle'], array_column($this->service(repository: $big)->candidates($this->user, $this->eng, 'needle')['here'], 'label'));
    }

    /** Audit RY-I1: labels and wiki names carry no format (bidi, zero-width) characters. */
    public function testLabelsWithoutFormatCharacters(): void
    {
        $this->page($this->eng, 'spoof', "Install \u{202E}gnp.exe");
        $this->em->flush();

        self::assertContains('Install gnp.exe', array_column($this->service()->candidates($this->user, $this->eng, 'install')['here'], 'label'));
    }

    public function testUrlsWithoutARouteOrWithARoutingError(): void
    {
        self::assertSame('', $this->service(routes: [])->candidates($this->user, $this->eng, 'guide')['here'][0]['url']);
        $broken = $this->createStub(UrlGeneratorInterface::class);
        $broken->method('generate')->willThrowException(new RouteNotFoundException());
        self::assertSame('', $this->service(urls: $broken)->candidates($this->user, $this->eng, 'guide')['here'][0]['url']);
    }

    /**
     * FrankenPHP worker mode without kernel reset: one service answers many requests of two users of two tenants; no
     * answer leaks into another and memory stays flat.
     */
    public function testManyRequestsOfTwoUsersKeepNothingBetweenThem(): void
    {
        $ops     = $this->ops;
        $checker = new class($ops) implements WikiSpaceAccessCheckerInterface {
            public function __construct(private readonly WikiSpace $ops)
            {
            }

            public function isGranted(UserInterface $user, WikiAction $action, WikiSpace $space, ?WikiPage $page = null): bool
            {
                return $space !== $this->ops || $user->getUserIdentifier() === 'ana';
            }
        };
        $service = $this->service(checker: $checker);
        $ana     = new TestUserEntity('ana');
        $bob     = new TestUserEntity('bob');
        $before  = 0;
        for ($i = 0; $i < 1000; ++$i) {
            [$user, $space] = $i % 2 === 0 ? [$ana, $this->eng] : [$bob, $this->foreign];
            $answer         = $service->candidates($user, $space, 'install');
            if ($user === $ana) {
                self::assertSame(['Operations', 'People'], array_column($answer['others'], 'wiki'));
                self::assertNotNull($service->sections($user, $space, $this->pages['ops-install']->getId()));
            } else {
                self::assertSame(['Install acme'], array_column($answer['here'], 'label'));
                self::assertSame([], $answer['others']);
                self::assertNull($service->sections($user, $space, $this->pages['ops-install']->getId()));
            }
            if ($i === 100) {
                $before = memory_get_usage();
            }
        }

        self::assertLessThan(1024 * 1024, memory_get_usage() - $before);
    }

    public function testTheQueryIsCleanedAndCut(): void
    {
        self::assertSame('a b', WikiLinkPickerService::normaliseQuery("  a\u{200B}b\u{202E} "));
        self::assertSame(str_repeat('x', WikiLinkPickerService::MAX_QUERY), WikiLinkPickerService::normaliseQuery(str_repeat('x', 300)));
        self::assertSame('', WikiLinkPickerService::normaliseQuery("\xC3"));
    }

    /**
     * @param list<string> $excluded
     * @param list<WikiSpace> $spaces extra spaces of the user
     * @param array<string, array{path: string, name: string}>|null $routes
     */
    private function service(
        string $scope = 'all',
        bool $crossWiki = true,
        array $excluded = [],
        ?WikiTenantPaths $tenantPaths = new WikiTenantPaths([['path' => '/t/{spaceSlug}/wiki', 'name_prefix' => 't_', 'slug_requirement' => 'eng|ops|hr|team-[0-9]+'], ['path' => '/x/{spaceSlug}', 'name_prefix' => 'x_', 'slug_requirement' => 'x-[a-z]+']], []),
        array $spaces = [],
        ?WikiPageRepositoryInterface $repository = null,
        ?array $routes = null,
        ?UrlGeneratorInterface $urls = null,
        ?WikiMarkdownRendererInterface $renderer = null,
        ?WikiSpaceAccessCheckerInterface $checker = null,
    ): WikiLinkPickerService {
        $privateId = $this->pages['private']->getId();
        $hr        = $this->hr;
        $guard     = new WikiSpaceGuard(
            new FixedSpaceLocator($this->eng, $this->ops, $this->hr, $this->foreign, ...$spaces),
            $checker ?? new CallbackSpaceAccessChecker(static fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => $space !== $hr && $page?->getId() !== $privateId),
        );
        // Plain doubles, not PHPUnit stubs (a stub records every call, which the worker test would count as memory growth).
        $access   = new LinkPickerTestAccess(fn (): bool => $this->canList);
        $resolver = new LinkPickerTestSpaces([$this->eng, $this->ops, $this->hr, $this->foreign, ...$spaces]);
        $urls ??= new LinkPickerTestUrls();

        return new WikiLinkPickerService(
            $repository ?? new DoctrineOrmWikiPageRepository($this->em),
            $guard,
            $access,
            $resolver,
            $urls,
            $routes ?? ['page_view' => ['path' => '/p', 'name' => 'nowo_wiki_page_view']],
            new ConfiguredWikiLinkPickerSettings($crossWiki),
            new WikiTenantPathsLinkTenantResolver($tenantPaths),
            $renderer ?? new MilkdownWikiMarkdownRenderer([], new NullPageResolver(), new NullUserResolver(), new AllowlistIconResolver('/i.svg', [])),
            new WikiHtmlSanitizer(WikiHtmlSanitizer::DEFAULT_EMBED_HOSTS, ['details' => ['open'], 'summary' => []]),
            new WikiRenderContext(),
            $scope,
            $excluded,
        );
    }

    private function page(WikiSpace $space, string $slug, string $title, ?string $markdown = null): WikiPage
    {
        $page = new WikiPage($space, $slug, $title);
        $this->em->persist($page);
        if ($markdown !== null) {
            $revision = new WikiPageRevision($page, 1, '', null, $markdown);
            $page->setCurrentRevision($revision);
            $this->em->persist($revision);
        }
        $this->pages[$slug] = $page;

        return $page;
    }
}
