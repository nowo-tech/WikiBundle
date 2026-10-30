<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\WikiBundle\Ai\NullWikiAiAssistant;
use Nowo\WikiBundle\Controller\WikiManageController;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageLink;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEditorOptionsEvent;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Interchange\WikiArchiveHelper;
use Nowo\WikiBundle\Interchange\WikiDocumentExporter;
use Nowo\WikiBundle\Interchange\WikiDocumentImporter;
use Nowo\WikiBundle\Interchange\WikiFrontMatterParser;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Link\WikiLinkedObject;
use Nowo\WikiBundle\Link\WikiObjectLinkResolverInterface;
use Nowo\WikiBundle\Link\WikiPageLinkService;
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
use Nowo\WikiBundle\Tests\Support\InMemoryLinkRepository;
use Nowo\WikiBundle\Tests\Support\WikiControllerContainerBuilder;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use ZipArchive;

/*
 * 1.9: the space- and page-aware checks, restore, unarchive, "Used in" and the editor options event.
 */
use function array_column;

final class WikiManageControllerSpaceAccessTest extends TestCase
{
    private WikiSpace $space;

    private WikiPage $page;

    private WikiPage $archived;

    /** @var array<int, WikiPageRevision> */
    private array $revisions = [];

    /** @var object{context: array<string, mixed>} */
    private object $twig;

    protected function setUp(): void
    {
        $this->space    = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $this->page     = new WikiPage($this->space, 'runbook', 'Runbook');
        $this->archived = new WikiPage($this->space, 'old', 'Old');
        $this->archived->archive();
        foreach ([1, 2, 3] as $number) {
            $this->revisions[$number] = new WikiPageRevision($this->page, $number, '<p>v' . $number . '</p>', new TestUser());
        }
        $this->page->setCurrentRevision($this->revisions[3]);
        $this->twig = new class {
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

    public function testASpaceTheCheckerHidesIsNotFound(): void
    {
        [$controller] = $this->controller(CallbackSpaceAccessChecker::allowing());

        $this->expectException(NotFoundHttpException::class);
        $controller->space('eng');
    }

    public function testSpaceListsArchivedPagesOnlyToArchivers(): void
    {
        [$archiver] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::Archive));
        $archiver->space('eng');
        self::assertSame('Old', $this->twig->context['archived'][0]['title']);
        self::assertSame('restore', $this->twig->context['archived'][0]['action']?->kind);
        self::assertSame('POST', $this->twig->context['archived'][0]['action']->method);
        self::assertFalse($this->twig->context['can_create']);
        self::assertTrue($this->twig->context['permissions']['archive']);

        [$reader] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View));
        $reader->space('eng');
        self::assertSame([], $this->twig->context['archived']);
    }

    public function testPageViewCarriesPermissionsAndUsedIn(): void
    {
        [$controller] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::History));
        $controller->viewPage('eng', 'runbook');

        self::assertFalse($this->twig->context['can_edit']);
        self::assertTrue($this->twig->context['can_history']);
        self::assertSame('BEACON-1', $this->twig->context['used_in'][0]['label']);
        self::assertSame('issue', $this->twig->context['used_in'][0]['type']);
    }

    public function testWriteRoutesAnswer403ToReaders(): void
    {
        [$controller] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View));

        foreach ([
            static fn () => $controller->editPage(Request::create('/e'), 'eng', 'runbook'),
            static fn () => $controller->newPage(Request::create('/n'), 'eng'),
            static fn () => $controller->pageHistory('eng', 'runbook'),
            static fn () => $controller->pageDiff(Request::create('/d'), 'eng', 'runbook'),
            static fn () => $controller->archivePage(Request::create('/a', 'POST'), 'eng', 'runbook'),
            static fn () => $controller->unarchivePage(Request::create('/u', 'POST'), 'eng', 'old'),
            static fn () => $controller->restoreRevision(Request::create('/r', 'POST'), 'eng', 'runbook', 1),
            static fn () => $controller->exportSpace(Request::create('/x'), 'eng'),
            static fn () => $controller->importSpace(Request::create('/i', 'POST'), 'eng'),
            static fn () => $controller->askAi(Request::create('/ask', 'GET', ['space' => 'eng'])),
        ] as $call) {
            try {
                $call();
                self::fail('A reader must get a 403.');
            } catch (AccessDeniedHttpException) {
            }
        }

        self::assertTrue(true);
    }

    /**
     * Every manage route with the action it asks, and whether it works on a page.
     *
     * @return iterable<string, array{0: string, 1: WikiAction, 2: bool}>
     */
    public static function routes(): iterable
    {
        yield 'space' => ['space', WikiAction::View, false];
        yield 'page view' => ['viewPage', WikiAction::View, true];
        yield 'page edit' => ['editPage', WikiAction::Edit, true];
        yield 'page new' => ['newPage', WikiAction::Create, false];
        yield 'page history' => ['pageHistory', WikiAction::History, true];
        yield 'page diff' => ['pageDiff', WikiAction::History, true];
        yield 'page archive' => ['archivePage', WikiAction::Archive, true];
        yield 'page unarchive' => ['unarchivePage', WikiAction::Archive, true];
        yield 'page restore' => ['restoreRevision', WikiAction::Restore, true];
        yield 'space export' => ['exportSpace', WikiAction::Export, false];
        yield 'space import' => ['importSpace', WikiAction::Import, false];
        yield 'ask ai' => ['askAi', WikiAction::AskAi, false];
    }

    #[DataProvider('routes')]
    public function testAHiddenSpaceIsNotFoundOnEveryRoute(string $route, WikiAction $action, bool $onPage): void
    {
        $asked        = [];
        [$controller] = $this->controller(new CallbackSpaceAccessChecker(static function (WikiAction $a, WikiSpace $s, ?WikiPage $p) use (&$asked): bool {
            $asked[] = $a;

            return false;
        }));

        $this->assertThrows(NotFoundHttpException::class, $this->call($controller, $route));
        self::assertSame([WikiAction::View], $asked, 'Nothing else is asked (or done) before the space is known to be readable.');
    }

    #[DataProvider('routes')]
    public function testADeniedActionOnAReadableSpaceIsForbiddenOrNotFoundForView(string $route, WikiAction $action, bool $onPage): void
    {
        $context      = [];
        [$controller] = $this->controller(new CallbackSpaceAccessChecker(static function (WikiAction $a, WikiSpace $s, ?WikiPage $p) use ($action, $onPage, &$context): bool {
            if ($a === $action) {
                $context[] = $p?->getSlug();
            }

            // The space stays readable; only the route's own action is denied (on the page for a page view).
            return $a !== $action || ($onPage && !$p instanceof WikiPage);
        }));

        $expected = $action === WikiAction::View ? NotFoundHttpException::class : AccessDeniedHttpException::class;
        $this->assertThrows($expected, $this->call($controller, $route));
        self::assertNotSame([], $context);
        self::assertSame($onPage ? ($route === 'unarchivePage' ? 'old' : 'runbook') : null, end($context), 'The action is asked with the page of the route.');
    }

    /**
     * @return iterable<string, array{0: string, 1: WikiAction, 2: bool}>
     */
    public static function pageRoutes(): iterable
    {
        foreach (self::routes() as $name => $route) {
            if ($route[2]) {
                yield $name => $route;
            }
        }
    }

    #[DataProvider('pageRoutes')]
    public function testAHiddenPageIsNotFoundOnEveryPageRoute(string $route): void
    {
        [$controller] = $this->controller(new CallbackSpaceAccessChecker(static fn (WikiAction $a, WikiSpace $s, ?WikiPage $p): bool => $a !== WikiAction::View || !$p instanceof WikiPage));

        $this->assertThrows(NotFoundHttpException::class, $this->call($controller, $route));
    }

    public function testHistoryOffersRestoreForOlderRevisionsOnly(): void
    {
        [$controller] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::History, WikiAction::Restore));
        $controller->pageHistory('eng', 'runbook');
        self::assertSame([2, 1], $this->restorable($this->twig->context['page']));

        [$historian] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::History));
        $historian->pageHistory('eng', 'runbook');
        self::assertSame([], $this->restorable($this->twig->context['page']));
    }

    /**
     * Search service whose query returns the given rows.
     *
     * @param list<array<int|string, mixed>> $rows
     */
    private function searchService(array $rows): WikiSearchService
    {
        $query = $this->createStub(Query::class);
        $query->method('setHint')->willReturnSelf();
        $query->method('getResult')->willReturn($rows);

        $qb = $this->createStub(QueryBuilder::class);
        foreach (['select', 'from', 'innerJoin', 'leftJoin', 'where', 'andWhere', 'setParameter', 'setMaxResults'] as $method) {
            $qb->method($method)->willReturnSelf();
        }
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        return new WikiSearchService($em);
    }

    /**
     * Revision numbers of the history rows that offer Restore.
     *
     * @return list<int>
     */
    private function restorable(ListPage $history): array
    {
        $numbers = [];
        foreach ($history->rows as $row) {
            foreach ($history->rowActions($row) as $action) {
                if ($action->kind === 'restore') {
                    $numbers[] = (int) $row['id'];
                }
            }
        }

        return $numbers;
    }

    public function testRestoreAppendsARevision(): void
    {
        [$controller, $container] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::Restore));
        $token                    = WikiControllerContainerBuilder::csrfToken($container, 'wiki_restore_' . $this->page->getId());

        $response = $controller->restoreRevision(Request::create('/r', 'POST', ['_token' => $token]), 'eng', 'runbook', 1);

        self::assertSame('/generated/nowo_wiki_page_view', $response->getTargetUrl());
        self::assertSame('<p>v1</p>', $this->page->getCurrentRevision()?->getContentHtml());
        /** @var RequestStack $stack */
        $stack = $container->get('request_stack');
        /** @var FlashBagAwareSessionInterface $session */
        $session = $stack->getSession();
        self::assertSame(['[NowoWikiBundle] wiki.history.restored'], $session->getFlashBag()->get('success'));

        $this->expectException(NotFoundHttpException::class);
        $controller->restoreRevision(Request::create('/r', 'POST', ['_token' => $token]), 'eng', 'runbook', 99);
    }

    public function testHiddenPagesStayOutOfTheTreeTheArchivedListAndSearch(): void
    {
        $hidden  = static fn (WikiAction $a, WikiSpace $s, ?WikiPage $p): bool => !$p instanceof WikiPage || ($p->getSlug() !== 'runbook' && $p->getSlug() !== 'old');
        $checker = new CallbackSpaceAccessChecker($hidden);

        [$controller] = $this->controller($checker);
        $controller->space('eng');
        self::assertSame([], $this->twig->context['tree']);
        self::assertSame([], $this->twig->context['archived']);

        $controller->search(Request::create('/q', 'GET', ['q' => 'run', 'space' => 'eng']));
        self::assertSame([], $this->twig->context['page']->rows);

        [$reader] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View));
        $reader->search(Request::create('/q', 'GET', ['q' => 'run', 'space' => 'eng']));
        self::assertSame(['Runbook'], array_column($this->twig->context['page']->rows, 'title'));
    }

    public function testASubPageOfAHiddenPageIsLeftOutOfTheTree(): void
    {
        $child   = new WikiPage($this->space, 'child', 'Child', $this->page);
        $checker = new CallbackSpaceAccessChecker(static fn (WikiAction $a, WikiSpace $s, ?WikiPage $p): bool => !$p instanceof WikiPage || $p->getSlug() !== 'runbook');

        [$controller] = $this->controller($checker, tree: [$this->page, $child]);
        $controller->space('eng');

        self::assertSame([], $this->twig->context['tree']);
    }

    public function testSpaceExportLeavesHiddenPagesAndTheirSubPagesOut(): void
    {
        $secret = new WikiPage($this->space, 'secret', 'Secret plan');
        $child  = new WikiPage($this->space, 'secret-child', 'Secret child', $secret);
        $pages  = $this->createStub(WikiPageRepositoryInterface::class);
        $pages->method('findActiveBySpace')->willReturn([$this->page, $secret, $child]);
        $exporter = new WikiDocumentExporter($pages, new WikiPageTreeBuilder(), new WikiMarkdownConverter(), new WikiFrontMatterParser());
        $checker  = new CallbackSpaceAccessChecker(static fn (WikiAction $a, WikiSpace $s, ?WikiPage $p): bool => $p?->getSlug() !== 'secret');

        [$controller] = $this->controller($checker, exporter: $exporter);
        $zip          = new ZipArchive();
        $path         = $controller->exportSpace(Request::create('/x?format=outline'), 'eng')->getFile()->getPathname();
        self::assertTrue($zip->open($path));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $names[] = (string) $zip->getNameIndex($i);
        }
        $zip->close();
        @unlink($path);

        self::assertSame([], array_values(array_filter($names, static fn (string $name): bool => str_contains(strtolower($name), 'secret'))));
        self::assertNotSame([], array_values(array_filter($names, static fn (string $name): bool => str_contains(strtolower($name), 'runbook'))));
    }

    public function testSpaceListsAndTheGlobalAiQuestionFollowThePerSpaceChecker(): void
    {
        $hiddenSpace = new WikiSpace('hr', 'HR', WikiSpaceOwnerScope::Team, 't');
        $checker     = new CallbackSpaceAccessChecker(static fn (WikiAction $a, WikiSpace $s): bool => $s->getSlug() === 'eng' && $a !== WikiAction::AskAi);

        [$controller] = $this->controller($checker, spaces: [$this->space, $hiddenSpace]);
        $controller->search(Request::create('/q'));
        self::assertSame(['eng'], array_map(static fn (ChoiceView $choice): string => $choice->value, $this->twig->context['page']->filters?->children['space']->vars['choices'] ?? []));

        $controller->askAi(Request::create('/ask'));
        self::assertSame([], $this->twig->context['page']->form?->children['space']->vars['choices']);
    }

    public function testATreeOfHiddenPagesIsNotFlaggedAsTruncated(): void
    {
        $hidden = [];
        for ($i = 0; $i < 3; ++$i) {
            $hidden[] = new WikiPage($this->space, 'secret-' . $i, 'Secret ' . $i);
        }
        $checker = new CallbackSpaceAccessChecker(static fn (WikiAction $a, WikiSpace $s, ?WikiPage $p): bool => !str_starts_with((string) $p?->getSlug(), 'secret'));

        [$controller] = $this->controller($checker, tree: [$this->page, ...$hidden], pagination: 1);
        $controller->space('eng');

        self::assertFalse($this->twig->context['tree_truncated']);
        self::assertCount(1, $this->twig->context['tree']);
    }

    public function testRestoreWithAForgedTokenIsForbidden(): void
    {
        [$controller] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::Restore));

        $this->expectException(AccessDeniedHttpException::class);
        $controller->restoreRevision(Request::create('/r', 'POST', ['_token' => 'forged']), 'eng', 'runbook', 1);
    }

    public function testUnarchive(): void
    {
        [$controller, $container] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::Archive));
        $token                    = WikiControllerContainerBuilder::csrfToken($container, 'wiki_unarchive_' . $this->archived->getId());

        try {
            $controller->unarchivePage(Request::create('/u', 'POST', ['_token' => 'forged']), 'eng', 'old');
            self::fail('A forged token must be refused.');
        } catch (AccessDeniedHttpException) {
        }

        $controller->unarchivePage(Request::create('/u', 'POST', ['_token' => $token]), 'eng', 'old');
        self::assertFalse($this->archived->isArchived());

        $this->expectException(NotFoundHttpException::class);
        $controller->unarchivePage(Request::create('/u', 'POST', ['_token' => $token]), 'eng', 'runbook');
    }

    public function testTheEditorOptionsEventSeesTheUploadHook(): void
    {
        $seen         = [];
        [$controller] = $this->controller(
            CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::Edit, WikiAction::Create),
            static function (WikiEditorOptionsEvent $event) use (&$seen): void {
                $seen[]                        = [$event->page?->getSlug(), array_keys($event->options), $event->space->getSlug(), $event->user->getUserIdentifier()];
                $event->options['suggestions'] = [['trigger' => '@', 'url' => '/people']];
            },
        );

        $controller->editPage(Request::create('/e'), 'eng', 'runbook');
        $controller->newPage(Request::create('/n'), 'eng');

        self::assertSame([
            ['runbook', ['upload_url', 'upload_csrf_token'], 'eng', 'demo@wiki.local'],
            [null, [], 'eng', 'demo@wiki.local'],
        ], $seen);
    }

    public function testWithoutADispatcherTheEditorGetsNoExtraOption(): void
    {
        [$controller] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::Create), dispatcher: false);

        self::assertSame(200, $controller->newPage(Request::create('/n'), 'eng')->getStatusCode());
    }

    /**
     * @return callable(): mixed
     */
    private function call(WikiManageController $controller, string $route): callable
    {
        return match ($route) {
            'space'           => static fn (): Response => $controller->space('eng'),
            'viewPage'        => static fn (): Response => $controller->viewPage('eng', 'runbook'),
            'editPage'        => static fn (): Response => $controller->editPage(Request::create('/e'), 'eng', 'runbook'),
            'newPage'         => static fn (): Response => $controller->newPage(Request::create('/n'), 'eng'),
            'pageHistory'     => static fn (): Response => $controller->pageHistory('eng', 'runbook'),
            'pageDiff'        => static fn (): Response => $controller->pageDiff(Request::create('/d'), 'eng', 'runbook'),
            'archivePage'     => static fn (): RedirectResponse => $controller->archivePage(Request::create('/a', 'POST'), 'eng', 'runbook'),
            'unarchivePage'   => static fn (): RedirectResponse => $controller->unarchivePage(Request::create('/u', 'POST'), 'eng', 'old'),
            'restoreRevision' => static fn (): RedirectResponse => $controller->restoreRevision(Request::create('/r', 'POST'), 'eng', 'runbook', 1),
            'exportSpace'     => static fn (): BinaryFileResponse => $controller->exportSpace(Request::create('/x'), 'eng'),
            'importSpace'     => static fn (): RedirectResponse => $controller->importSpace(Request::create('/i', 'POST'), 'eng'),
            default           => static fn (): Response => $controller->askAi(Request::create('/ask', 'GET', ['space' => 'eng'])),
        };
    }

    /**
     * @param class-string<Throwable> $expected
     * @param callable(): mixed $call
     */
    private function assertThrows(string $expected, callable $call): void
    {
        try {
            $call();
        } catch (Throwable $e) {
            self::assertInstanceOf($expected, $e);

            return;
        }

        self::fail('Expected ' . $expected . '.');
    }

    /**
     * @param (callable(WikiEditorOptionsEvent): void)|null $onEditorOptions
     * @param list<WikiPage>|null $tree active pages of the space (default: the runbook)
     * @param list<WikiSpace>|null $spaces spaces of the user's resolver (default: none)
     *
     * @return array{0: WikiManageController, 1: Container}
     */
    private function controller(CallbackSpaceAccessChecker $checker, ?callable $onEditorOptions = null, bool $dispatcher = true, ?array $tree = null, ?WikiDocumentExporter $exporter = null, ?array $spaces = null, int $pagination = 500): array
    {
        $spaceResolver = $this->createStub(WikiSpaceAccessResolverInterface::class);
        $spaceResolver->method('listSpacesForUser')->willReturn($spaces ?? []);

        $pages = $this->createStub(WikiPageRepositoryInterface::class);
        $pages->method('findBySlug')->willReturnCallback(fn (WikiSpace $s, string $slug): ?WikiPage => ['runbook' => $this->page, 'old' => $this->archived][$slug] ?? null);
        $pages->method('findActiveBySpace')->willReturn($tree ?? [$this->page]);
        $pages->method('findArchivedBySpace')->willReturn([$this->archived]);

        $revisions = $this->createStub(WikiPageRevisionRepositoryInterface::class);
        $revisions->method('findByPage')->willReturn([$this->revisions[3], $this->revisions[2], $this->revisions[1]]);
        $revisions->method('findByPageAndNumber')->willReturnCallback(fn (WikiPage $p, int $n): ?WikiPageRevision => $this->revisions[$n] ?? null);
        $revisions->method('getNextRevisionNumber')->willReturn(4);

        $links = new InMemoryLinkRepository();
        $links->save(new WikiPageLink($this->page, 'issue', '1'));
        $resolver = new class implements WikiObjectLinkResolverInterface {
            public function supports(string $objectType): bool
            {
                return true;
            }

            public function resolveVisible(UserInterface $user, string $objectType, array $objectIds): array
            {
                return ['1' => new WikiLinkedObject('issue', '1', 'BEACON-1', '/issues/1')];
            }
        };

        $events = new EventDispatcher();
        if ($onEditorOptions !== null) {
            $events->addListener(WikiEvents::EDITOR_OPTIONS, $onEditorOptions);
        }

        $legacy = $this->createStub(WikiAccessCheckerInterface::class);
        foreach (['canAccess', 'canList', 'canCreate', 'canEdit', 'canViewHistory', 'canArchive', 'canAskAi', 'canImport', 'canExport'] as $method) {
            $legacy->method($method)->willReturn(true);
        }

        $noConstructor = static fn (string $class): object => (new ReflectionClass($class))->newInstanceWithoutConstructor();

        $controller = new WikiManageController(
            accessChecker: $legacy,
            spaceAccessResolver: $spaceResolver,
            pageRepository: $pages,
            revisionRepository: $revisions,
            pageService: new WikiPageService($pages, $revisions, new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher()),
            pageTreeBuilder: new WikiPageTreeBuilder(),
            revisionDiffService: new WikiRevisionDiffService($revisions),
            searchService: $this->searchService([[$this->page, 'contentHtml' => '<p>run it</p>']]),
            aiAssistant: new NullWikiAiAssistant(),
            documentImporter: $noConstructor(WikiDocumentImporter::class),
            documentExporter: $exporter ?? $noConstructor(WikiDocumentExporter::class),
            archiveHelper: new WikiArchiveHelper(),
            routes: [
                'space'             => ['path' => '/s', 'name' => 'nowo_wiki_space'],
                'page_view'         => ['path' => '/v', 'name' => 'nowo_wiki_page_view'],
                'page_unarchive'    => ['path' => '/u', 'name' => 'nowo_wiki_page_unarchive'],
                'page_restore'      => ['path' => '/r', 'name' => 'nowo_wiki_page_restore'],
                'page_history'      => ['path' => '/h', 'name' => 'nowo_wiki_page_history'],
                'attachment_upload' => ['path' => '/a', 'name' => 'nowo_wiki_attachment_upload'],
                'search'            => ['path' => '/q', 'name' => 'nowo_wiki_search'],
                'space_import'      => ['path' => '/i', 'name' => 'nowo_wiki_space_import'],
                'ai_ask'            => ['path' => '/ask', 'name' => 'nowo_wiki_ai_ask'],
            ],
            templates: ['layout' => 'l', 'space' => 's', 'page_view' => 'v', 'page_edit' => 'e', 'page_history' => 'h', 'page_diff' => 'd', 'ai_ask' => 'a'],
            dashboardRoute: null,
            editor: ['tiptap_config' => 'notion'],
            importExport: ['enabled' => true, 'max_upload_bytes' => 1048576],
            pagination: ['spaces_per_page' => 24, 'revisions_per_page' => 25, 'max_tree_pages' => $pagination],
            spaceGuard: new WikiSpaceGuard(new FixedSpaceLocator($this->space), $checker),
            linkService: new WikiPageLinkService($links, [$resolver], new EventDispatcher()),
            eventDispatcher: $dispatcher ? $events : null,
            attachments: ['enabled' => true],
            translator: new class implements TranslatorInterface {
                public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
                {
                    return '[' . $domain . '] ' . $id;
                }

                public function getLocale(): string
                {
                    return 'en';
                }
            },
        );
        $container = WikiControllerContainerBuilder::bind($controller, new TestUser(), ['twig' => $this->twig]);

        return [$controller, $container];
    }
}
