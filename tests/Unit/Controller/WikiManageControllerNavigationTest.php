<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Controller;

use Closure;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\BreadcrumbKitBundle\Service\BreadcrumbCurrentLabelInterface;
use Nowo\WikiBundle\Ai\NullWikiAiAssistant;
use Nowo\WikiBundle\Controller\WikiManageController;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Interchange\WikiArchiveHelper;
use Nowo\WikiBundle\Interchange\WikiDocumentExporter;
use Nowo\WikiBundle\Interchange\WikiDocumentImporter;
use Nowo\WikiBundle\Interchange\WikiDocumentTreeReader;
use Nowo\WikiBundle\Interchange\WikiFormatDetector;
use Nowo\WikiBundle\Interchange\WikiFrontMatterParser;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiPageTreeBuilder;
use Nowo\WikiBundle\Service\WikiRevisionDiffService;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use Nowo\WikiBundle\Tests\Support\WikiControllerContainerBuilder;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function array_column;
use function array_filter;
use function count;
use function json_encode;
use function str_starts_with;

require_once __DIR__ . '/../../Stub/BreadcrumbKit/BreadcrumbCurrentLabelInterface.php';

/**
 * 1.30: the in-wiki path (web_ui.page_header.path: card), the direct sub-pages (web_ui.children) and the optional
 * BreadcrumbKit current label of the page view — permission filtering (hidden ancestors and children), archived
 * pages, tree order, and the query budget (the sub-pages cost one query whatever their number; the path none).
 */
final class WikiManageControllerNavigationTest extends TestCase
{
    private EntityManager $em;

    private RecordingLogger $sql;

    private WikiSpace $space;

    private WikiPage $ops;

    private WikiPage $run;

    private WikiPage $secret;

    private WikiPage $deep;

    private WikiPage $leaf;

    private object $twig;

    protected function setUp(): void
    {
        $this->sql   = new RecordingLogger();
        $this->em    = SqliteWikiEntityManager::create($this->sql);
        $this->space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 't');
        $this->em->persist($this->space);
        // Engineering › Operations › Runbooks › Secret runbooks › Deep › Leaf
        $this->ops    = $this->page('ops', 'Operations');
        $this->run    = $this->page('run', 'Runbooks', $this->ops);
        $this->secret = $this->page('secret', 'Secret runbooks', $this->run);
        $this->deep   = $this->page('deep', 'Deep', $this->secret);
        $this->leaf   = $this->page('leaf', 'Leaf', $this->deep);
        $this->twig   = new class {
            /** @var array<string, mixed> */
            public array $context = [];

            /** @param array<string, mixed> $context */
            public function render(string $name, array $context = []): string
            {
                $this->context = $context;

                return $name;
            }
        };
    }

    public function testOffByDefault(): void
    {
        $label = $this->createMock(BreadcrumbCurrentLabelInterface::class);
        $label->expects(self::never())->method('setCurrentLabel');
        $this->controller(layout: [], breadcrumb: $label)->viewPage('eng', 'deep');

        self::assertArrayNotHasKey('page_path', $this->twig->context);
        self::assertArrayNotHasKey('page_children', $this->twig->context);
    }

    public function testThePathListsTheSpaceAndTheAncestorsWithLinks(): void
    {
        $this->controller(['path' => 'card'])->viewPage('eng', 'leaf');

        self::assertSame([
            ['label' => 'Engineering', 'href' => '/generated/nowo_wiki_space'],
            ['label' => 'Operations', 'href' => '/generated/nowo_wiki_page_view'],
            ['label' => 'Runbooks', 'href' => '/generated/nowo_wiki_page_view'],
            ['label' => 'Secret runbooks', 'href' => '/generated/nowo_wiki_page_view'],
            ['label' => 'Deep', 'href' => '/generated/nowo_wiki_page_view'],
        ], $this->twig->context['page_path']);

        // A top-level page: the space alone; without the space route, no link.
        $this->controller(['path' => 'card'], routes: ['page_view'])->viewPage('eng', 'ops');
        self::assertSame([['label' => 'Engineering', 'href' => null]], $this->twig->context['page_path']);
    }

    /**
     * Defence in depth: the read rule already hides a page below a hidden one (404), but a host checker may answer an
     * ancestor differently once the page is shown. Such an ancestor has neither label nor link: neither its title nor
     * its slug reaches the template.
     */
    public function testAHiddenAncestorLeaksNothing(): void
    {
        $asked  = [];
        $decide = function (WikiAction $action, WikiSpace $space, ?WikiPage $page) use (&$asked): bool {
            if ($page?->getId() !== $this->secret->getId()) {
                return true;
            }
            $asked[] = $action;

            // Readable for the page's own read check, hidden afterwards.
            return count($asked) === 1;
        };
        $this->controller(['path' => 'card'], $decide)->viewPage('eng', 'leaf');

        $path = $this->twig->context['page_path'];
        self::assertSame(['label' => null, 'href' => null], $path[3]);
        self::assertSame('Deep', $path[4]['label']);
        $encoded = (string) json_encode($path);
        self::assertStringNotContainsString('Secret', $encoded);
        self::assertStringNotContainsString('secret', $encoded);
    }

    public function testTheSubPagesAreReadableActiveAndInTreeOrder(): void
    {
        $b      = $this->page('b', 'Bravo', $this->run);
        $a      = $this->page('a', 'Alpha', $this->run);
        $hidden = $this->page('hidden', 'Hidden child', $this->run);
        $gone   = $this->page('gone', 'Archived child', $this->run);
        $gone->archive();
        $b->setPosition(2);
        $a->setPosition(1);
        $this->secret->setPosition(3);
        $this->em->flush();
        $decide = static fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => $page?->getId() !== $hidden->getId();

        foreach (['always', 'auto'] as $mode) {
            $this->controller(['children' => $mode], $decide)->viewPage('eng', 'run');
            $children = $this->twig->context['page_children'];
            self::assertSame($mode, $children['mode']);
            // Hidden and archived children are not listed, nor counted; grandchildren are not direct sub-pages.
            self::assertSame(['Alpha', 'Bravo', 'Secret runbooks'], array_column($children['items'], 'label'));
            self::assertSame('/generated/nowo_wiki_page_view', $children['items'][0]['href']);
        }

        // A page without (readable) sub-pages: an empty list.
        $this->controller(['children' => 'always'], $decide)->viewPage('eng', 'leaf');
        self::assertSame([], $this->twig->context['page_children']['items']);

        // A host repository without the children finder: the space's active pages, filtered the same way.
        $this->controller(['children' => 'always'], $decide, pages: $this->stubRepository())->viewPage('eng', 'run');
        self::assertSame(['Alpha', 'Bravo', 'Secret runbooks'], array_column($this->twig->context['page_children']['items'], 'label'));
    }

    public function testTheSubPagesCostOneQueryWhateverTheirNumberAndThePathNone(): void
    {
        $budget = function (array $layout): int {
            $controller = $this->controller($layout);
            $this->em->clear();
            $before = $this->queries();
            $controller->viewPage('eng', 'run');

            return $this->queries() - $before;
        };

        $base = $budget([]);
        self::assertSame($base, $budget(['path' => 'card']), 'The path reads the ancestors already loaded.');
        self::assertSame($base + 1, $budget(['children' => 'always']));

        // Attached again after the clear() of the budget runs.
        $space = $this->em->find(WikiSpace::class, $this->space->getId());
        $run   = $this->em->find(WikiPage::class, $this->run->getId());
        self::assertInstanceOf(WikiSpace::class, $space);
        self::assertInstanceOf(WikiPage::class, $run);
        $this->space = $space;
        $this->run   = $run;
        for ($i = 0; $i < 30; ++$i) {
            $this->page('child-' . $i, 'Child ' . $i, $this->run);
        }
        $budget([]); // warm-up: the space was loaded again above
        $base = $budget([]);
        self::assertSame($base + 1, $budget(['children' => 'always', 'path' => 'card']));
        self::assertCount(31, $this->twig->context['page_children']['items']);
    }

    public function testTheBreadcrumbKitLabelIsOptional(): void
    {
        $label = $this->createMock(BreadcrumbCurrentLabelInterface::class);
        $label->expects(self::once())->method('setCurrentLabel')->with('Deep');
        $this->controller(['breadcrumb_current_label' => true], breadcrumb: $label)->viewPage('eng', 'deep');

        // Without BreadcrumbKit (null) or with an older one (another object): nothing to call, the page renders.
        $this->controller(['breadcrumb_current_label' => true])->viewPage('eng', 'deep');
        $this->controller(['breadcrumb_current_label' => true], breadcrumb: new stdClass())->viewPage('eng', 'deep');
        self::assertArrayHasKey('page', $this->twig->context);
    }

    private function queries(): int
    {
        return count(array_filter($this->sql->records, static fn (array $record): bool => str_starts_with($record['message'], 'Executing')));
    }

    private function page(string $slug, string $title, ?WikiPage $parent = null): WikiPage
    {
        $page = new WikiPage($this->space, $slug, $title, $parent);
        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }

    /**
     * A host repository without WikiPageChildrenFinderInterface (in memory).
     */
    private function stubRepository(): WikiPageRepositoryInterface
    {
        $doctrine = new DoctrineOrmWikiPageRepository($this->em);
        $stub     = $this->createStub(WikiPageRepositoryInterface::class);
        $stub->method('findBySlug')->willReturnCallback(static fn (WikiSpace $space, string $slug): ?WikiPage => $doctrine->findBySlug($space, $slug));
        $stub->method('findActiveBySpace')->willReturnCallback(static fn (WikiSpace $space, ?int $limit = null): array => $doctrine->findActiveBySpace($space, $limit));

        return $stub;
    }

    /**
     * @param array<string, mixed> $layout
     * @param (Closure(WikiAction, WikiSpace, ?WikiPage): bool)|null $decide
     * @param list<string> $routes
     */
    private function controller(array $layout, ?Closure $decide = null, ?object $breadcrumb = null, array $routes = ['space', 'page_view', 'page_edit', 'page_history'], ?WikiPageRepositoryInterface $pages = null): WikiManageController
    {
        $repository = $pages ?? new DoctrineOrmWikiPageRepository($this->em);
        $doctrine   = new DoctrineOrmWikiPageRepository($this->em);
        $revisions  = $this->createStub(WikiPageRevisionRepositoryInterface::class);
        $roles      = $this->createStub(WikiAccessCheckerInterface::class);
        foreach (['canAccess', 'canList', 'canCreate', 'canEdit', 'canViewHistory', 'canArchive', 'canAskAi', 'canImport', 'canExport'] as $method) {
            $roles->method($method)->willReturn(true);
        }
        $resolver = $this->createStub(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$this->space]);
        $service = new WikiPageService($doctrine, $revisions, new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher());
        $named   = [];
        foreach ($routes as $key) {
            $named[$key] = ['path' => '/' . $key, 'name' => 'nowo_wiki_' . $key];
        }

        $controller = new WikiManageController(
            $roles,
            $resolver,
            $repository,
            $revisions,
            $service,
            new WikiPageTreeBuilder(),
            new WikiRevisionDiffService($revisions),
            new WikiSearchService($this->createStub(EntityManagerInterface::class)),
            new NullWikiAiAssistant(),
            new WikiDocumentImporter(new WikiArchiveHelper(), new WikiFormatDetector(new WikiFrontMatterParser()), new WikiDocumentTreeReader(new WikiFrontMatterParser()), new WikiMarkdownConverter(), $doctrine, $service, new WikiSlugger()),
            new WikiDocumentExporter($doctrine, new WikiPageTreeBuilder(), new WikiMarkdownConverter(), new WikiFrontMatterParser()),
            new WikiArchiveHelper(),
            $named,
            [],
            null,
            ['tiptap_config' => 'notion'],
            ['enabled' => false, 'max_upload_bytes' => 1],
            spaceGuard: new WikiSpaceGuard(new FixedSpaceLocator($this->space), new CallbackSpaceAccessChecker($decide ?? static fn (): bool => true)),
            layout: $layout,
            breadcrumbCurrentLabel: $breadcrumb,
        );
        WikiControllerContainerBuilder::bind($controller, new TestUser(), ['twig' => $this->twig]);

        return $controller;
    }
}
