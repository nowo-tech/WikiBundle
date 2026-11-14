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
use Nowo\WikiBundle\Entity\WikiPageRevision;
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
use Nowo\WikiBundle\Repository\WikiPageChildrenFinderInterface;
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
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use Nowo\WikiBundle\Tests\Support\WikiControllerContainerBuilder;
use Nowo\WikiBundle\Ui\WikiBreadcrumbParentLabel;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

use function array_column;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function in_array;
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

    /**
     * Audit MG-2-L1: the cap applies to readable sub-pages; hidden ones never eat it, and "more than the cap" is a flag.
     */
    public function testTheCapCountsReadableSubPagesAndFlagsTheRest(): void
    {
        $hidden = [];
        for ($i = 1; $i <= 7; ++$i) {
            $child = $this->page('c' . $i, 'Child ' . $i, $this->run);
            $child->setPosition($i);
            if ($i <= 4) {
                $hidden[] = $child->getId();
            }
        }
        $this->em->flush();
        $decide = static fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => !in_array($page?->getId(), $hidden, true);

        // Cap 2: four hidden children (positions 1-4) sit between the readable ones; they do not use up the cap.
        $this->controller(['children' => 'always'], $decide, maxTreePages: 2)->viewPage('eng', 'run');
        $children = $this->twig->context['page_children'];
        self::assertSame(['Secret runbooks', 'Child 5'], array_column($children['items'], 'label'));
        self::assertTrue($children['truncated']);

        // Cap 4: four readable (Child 5-7 and Secret runbooks), not more: no flag.
        $this->controller(['children' => 'always'], $decide, maxTreePages: 4)->viewPage('eng', 'run');
        self::assertSame(['Secret runbooks', 'Child 5', 'Child 6', 'Child 7'], array_column($this->twig->context['page_children']['items'], 'label'));
        self::assertFalse($this->twig->context['page_children']['truncated']);

        // The fallback repository is bounded like the tree; a capped space list is flagged.
        $this->controller(['children' => 'always'], $decide, pages: $this->stubRepository(), maxTreePages: 3)->viewPage('eng', 'run');
        self::assertTrue($this->twig->context['page_children']['truncated']);
    }

    /**
     * Audit MG-2-L5: at most CHILDREN_MAX_BATCHES finder calls, then truncated; a host finder that ignores the offset
     * (the same batch again) stops at the second call instead of looping forever.
     */
    public function testTheBatchesAreCappedAndAFinderIgnoringTheOffsetStops(): void
    {
        $hidden = [];
        for ($i = 1; $i <= 9; ++$i) {
            $child = $this->page('h' . $i, 'Hidden ' . $i, $this->run);
            $child->setPosition($i);
            $hidden[] = $child->getId();
        }
        $this->em->flush();
        $decide = static fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => !in_array($page?->getId(), $hidden, true);

        // Cap 1: batches of 2, all hidden but Secret runbooks (position 0) — three calls, then truncated.
        $finder = $this->countingFinder(false);
        $this->controller(['children' => 'always'], $decide, pages: $finder, maxTreePages: 1)->viewPage('eng', 'run');
        self::assertSame(WikiManageController::CHILDREN_MAX_BATCHES, $finder->calls);
        self::assertSame(['Secret runbooks'], array_column($this->twig->context['page_children']['items'], 'label'));
        self::assertTrue($this->twig->context['page_children']['truncated']);

        // A finder that ignores the offset: the second batch brings nothing new.
        $finder = $this->countingFinder(true);
        $this->controller(['children' => 'always'], $decide, pages: $finder, maxTreePages: 1)->viewPage('eng', 'run');
        self::assertSame(2, $finder->calls);
        self::assertTrue($this->twig->context['page_children']['truncated']);
    }

    /**
     * Audit MG-2-L6: equal positions and titles still page in one total order (id last): no page is skipped or repeated.
     */
    public function testOffsetPagingOverEqualSortKeysIsTotal(): void
    {
        for ($i = 0; $i < 6; ++$i) {
            $this->page('same-' . $i, 'Same', $this->leaf);
        }
        $repository = new DoctrineOrmWikiPageRepository($this->em);
        $ids        = [];
        for ($offset = 0; $offset < 6; $offset += 2) {
            foreach ($repository->findActiveChildren($this->leaf, 2, $offset) as $page) {
                $ids[] = $page->getId();
            }
        }
        self::assertCount(6, array_unique($ids));
        self::assertSame($ids, array_values(array_map(static fn (WikiPage $page): string => $page->getId(), $repository->findActiveChildren($this->leaf, 10))));
    }

    /**
     * Audit MG-2-L2: findActiveChildren() reads the rows again over a page the EntityManager kept (worker mode).
     */
    public function testTheChildrenFinderRefreshesKeptPages(): void
    {
        $child = $this->page('kid', 'Old title', $this->leaf);
        $this->em->getConnection()->executeStatement('UPDATE wiki_pages SET title = ? WHERE id = ?', ['New title', $child->getId()]);

        $found = (new DoctrineOrmWikiPageRepository($this->em))->findActiveChildren($this->leaf, 10);
        self::assertSame(['New title'], array_map(static fn (WikiPage $page): string => $page->getTitle(), $found));
        self::assertSame([], (new DoctrineOrmWikiPageRepository($this->em))->findActiveChildren($this->leaf, 10, 1));
    }

    /**
     * Audit MG-2-L4: an archived ancestor (its page view is a 404) is plain text, not a link.
     */
    public function testAnArchivedAncestorHasNoLink(): void
    {
        $this->run->archive();
        $this->em->flush();
        $this->controller(['path' => 'card'])->viewPage('eng', 'leaf');

        self::assertSame(['label' => 'Runbooks', 'href' => null], $this->twig->context['page_path'][2]);
        self::assertSame('/generated/nowo_wiki_page_view', $this->twig->context['page_path'][1]['href']);
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

    /** 1.31: title card (the card prints the title only without a leading title heading) and page_meta card. */
    public function testTheTitleInTheCardAndTheRevisionLine(): void
    {
        $this->controller(['title' => 'card', 'page_meta' => 'card'])->viewPage('eng', 'deep');
        self::assertTrue($this->twig->context['title_in_body']);
        self::assertTrue($this->twig->context['title_in_card']);
        self::assertArrayNotHasKey('page_meta', $this->twig->context);

        $author = new TestUserEntity('id-author');
        $this->em->persist($author);
        $revision = new WikiPageRevision($this->deep, 4, '<h1>Deep</h1><p>Body</p>', $author);
        $this->em->persist($revision);
        $this->deep->setCurrentRevision($revision);
        $this->em->flush();
        $this->controller(['title' => 'card', 'page_meta' => 'card'])->viewPage('eng', 'deep');
        self::assertTrue($this->twig->context['title_in_body']);
        self::assertArrayNotHasKey('title_in_card', $this->twig->context);
        self::assertSame(['revision' => 4, 'updated_at' => $revision->getCreatedAt()], $this->twig->context['page_meta']);

        // title: body without the heading, and the defaults: nothing.
        $this->controller(['title' => 'body'])->viewPage('eng', 'leaf');
        self::assertArrayNotHasKey('title_in_body', $this->twig->context);
        self::assertArrayNotHasKey('title_in_card', $this->twig->context);
        $this->controller([])->viewPage('eng', 'deep');
        self::assertArrayNotHasKey('page_meta', $this->twig->context);
    }

    /** 1.31: the child pages put the page title on the request for the BreadcrumbKit trail listener. */
    public function testTheChildPagesNameTheirPageCrumb(): void
    {
        $label   = $this->createStub(BreadcrumbCurrentLabelInterface::class);
        $request = Request::create('/h');
        $this->controller(['breadcrumb_current_label' => true], breadcrumb: $label)->pageHistory('eng', 'deep', $request);
        self::assertSame('Deep', $request->attributes->get(WikiBreadcrumbParentLabel::ATTRIBUTE));
        // 1.33: with the action's route key, so that a tenant copy of the route is recognised.
        self::assertSame('page_history', $request->attributes->get(WikiBreadcrumbParentLabel::ROUTE_KEY_ATTRIBUTE));
        $request = Request::create('/e');
        $this->controller(['breadcrumb_current_label' => true], breadcrumb: $label)->editPage($request, 'eng', 'deep');
        self::assertSame('page_edit', $request->attributes->get(WikiBreadcrumbParentLabel::ROUTE_KEY_ATTRIBUTE));

        // Off, or without BreadcrumbKit: nothing on the request.
        foreach ([$this->controller([], breadcrumb: $label), $this->controller(['breadcrumb_current_label' => true])] as $controller) {
            $request = Request::create('/h');
            $controller->pageHistory('eng', 'deep', $request);
            self::assertFalse($request->attributes->has(WikiBreadcrumbParentLabel::ATTRIBUTE));
        }
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
     * A host repository with the children finder that counts its calls and may ignore the offset.
     */
    private function countingFinder(bool $ignoreOffset): WikiPageRepositoryInterface&WikiPageChildrenFinderInterface
    {
        return new class(new DoctrineOrmWikiPageRepository($this->em), $ignoreOffset) implements WikiPageRepositoryInterface, WikiPageChildrenFinderInterface {
            public int $calls = 0;

            public function __construct(private readonly DoctrineOrmWikiPageRepository $inner, private readonly bool $ignoreOffset)
            {
            }

            public function findActiveChildren(WikiPage $parent, int $limit, int $offset = 0): array
            {
                ++$this->calls;

                return $this->inner->findActiveChildren($parent, $limit, $this->ignoreOffset ? 0 : $offset);
            }

            public function save(WikiPage $page): void
            {
                $this->inner->save($page);
            }

            public function findById(string $id): ?WikiPage
            {
                return $this->inner->findById($id);
            }

            public function findBySlug(WikiSpace $space, string $slug): ?WikiPage
            {
                return $this->inner->findBySlug($space, $slug);
            }

            public function findActiveBySpace(WikiSpace $space, ?int $limit = null): array
            {
                return $this->inner->findActiveBySpace($space, $limit);
            }

            public function findArchivedBySpace(WikiSpace $space, int $limit = 100): array
            {
                return $this->inner->findArchivedBySpace($space, $limit);
            }

            public function countBySpaceAndSlug(WikiSpace $space, string $slug, ?string $excludePageId = null): int
            {
                return $this->inner->countBySpaceAndSlug($space, $slug, $excludePageId);
            }
        };
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
    private function controller(array $layout, ?Closure $decide = null, ?object $breadcrumb = null, array $routes = ['space', 'page_view', 'page_edit', 'page_history'], ?WikiPageRepositoryInterface $pages = null, int $maxTreePages = 500): WikiManageController
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
            pagination: ['max_tree_pages' => $maxTreePages] + WikiManageController::DEFAULT_PAGINATION,
            spaceGuard: new WikiSpaceGuard(new FixedSpaceLocator($this->space), new CallbackSpaceAccessChecker($decide ?? static fn (): bool => true)),
            layout: $layout,
            breadcrumbCurrentLabel: $breadcrumb,
        );
        WikiControllerContainerBuilder::bind($controller, new TestUser(), ['twig' => $this->twig]);

        return $controller;
    }
}
