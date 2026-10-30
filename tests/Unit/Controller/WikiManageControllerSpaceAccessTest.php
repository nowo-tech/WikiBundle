<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
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
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * 1.9: the space- and page-aware checks, restore, unarchive, "Used in" and the editor options event.
 */
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
        self::assertSame([$this->archived], $this->twig->context['archived_pages']);
        self::assertArrayHasKey($this->archived->getId(), $this->twig->context['unarchive_forms']);
        self::assertFalse($this->twig->context['can_create']);
        self::assertTrue($this->twig->context['permissions']['archive']);

        [$reader] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View));
        $reader->space('eng');
        self::assertSame([], $this->twig->context['archived_pages']);
    }

    public function testPageViewCarriesPermissionsAndUsedIn(): void
    {
        [$controller] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::History));
        $controller->viewPage('eng', 'runbook');

        self::assertFalse($this->twig->context['can_edit']);
        self::assertTrue($this->twig->context['can_history']);
        self::assertSame('BEACON-1', $this->twig->context['used_in']['issue'][0]->label);
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

    public function testHistoryOffersRestoreForOlderRevisionsOnly(): void
    {
        [$controller] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::History, WikiAction::Restore));
        $controller->pageHistory('eng', 'runbook');
        self::assertSame([2, 1], array_keys($this->twig->context['restore_forms']));

        [$historian] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::History));
        $historian->pageHistory('eng', 'runbook');
        self::assertSame([], $this->twig->context['restore_forms']);
    }

    public function testRestoreAppendsARevision(): void
    {
        [$controller, $container] = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::Restore));
        $token                    = WikiControllerContainerBuilder::csrfToken($container, 'wiki_restore_' . $this->page->getId());

        $response = $controller->restoreRevision(Request::create('/r', 'POST', ['_token' => $token]), 'eng', 'runbook', 1);

        self::assertSame('/generated/nowo_wiki_page_view', $response->getTargetUrl());
        self::assertSame('<p>v1</p>', $this->page->getCurrentRevision()?->getContentHtml());

        $this->expectException(NotFoundHttpException::class);
        $controller->restoreRevision(Request::create('/r', 'POST', ['_token' => $token]), 'eng', 'runbook', 99);
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
     * @param (callable(WikiEditorOptionsEvent): void)|null $onEditorOptions
     *
     * @return array{0: WikiManageController, 1: Container}
     */
    private function controller(CallbackSpaceAccessChecker $checker, ?callable $onEditorOptions = null, bool $dispatcher = true): array
    {
        $pages = $this->createStub(WikiPageRepositoryInterface::class);
        $pages->method('findBySlug')->willReturnCallback(fn (WikiSpace $s, string $slug): ?WikiPage => ['runbook' => $this->page, 'old' => $this->archived][$slug] ?? null);
        $pages->method('findActiveBySpace')->willReturn([$this->page]);
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
            spaceAccessResolver: $this->createStub(WikiSpaceAccessResolverInterface::class),
            pageRepository: $pages,
            revisionRepository: $revisions,
            pageService: new WikiPageService($pages, $revisions, new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher()),
            pageTreeBuilder: new WikiPageTreeBuilder(),
            revisionDiffService: new WikiRevisionDiffService($revisions),
            searchService: new WikiSearchService($this->createStub(EntityManagerInterface::class)),
            aiAssistant: new NullWikiAiAssistant(),
            documentImporter: $noConstructor(WikiDocumentImporter::class),
            documentExporter: $noConstructor(WikiDocumentExporter::class),
            archiveHelper: new WikiArchiveHelper(),
            routes: [
                'space'             => ['path' => '/s', 'name' => 'nowo_wiki_space'],
                'page_view'         => ['path' => '/v', 'name' => 'nowo_wiki_page_view'],
                'page_unarchive'    => ['path' => '/u', 'name' => 'nowo_wiki_page_unarchive'],
                'page_restore'      => ['path' => '/r', 'name' => 'nowo_wiki_page_restore'],
                'attachment_upload' => ['path' => '/a', 'name' => 'nowo_wiki_attachment_upload'],
                'search'            => ['path' => '/q', 'name' => 'nowo_wiki_search'],
                'space_import'      => ['path' => '/i', 'name' => 'nowo_wiki_space_import'],
                'ai_ask'            => ['path' => '/ask', 'name' => 'nowo_wiki_ai_ask'],
            ],
            templates: ['layout' => 'l', 'space' => 's', 'page_view' => 'v', 'page_edit' => 'e', 'page_history' => 'h', 'page_diff' => 'd', 'ai_ask' => 'a'],
            dashboardRoute: null,
            editor: ['tiptap_config' => 'notion'],
            importExport: ['enabled' => true, 'max_upload_bytes' => 1048576],
            spaceGuard: new WikiSpaceGuard(new FixedSpaceLocator($this->space), $checker),
            linkService: new WikiPageLinkService($links, [$resolver], new EventDispatcher()),
            eventDispatcher: $dispatcher ? $events : null,
            attachments: ['enabled' => true],
        );
        $container = WikiControllerContainerBuilder::bind($controller, new TestUser(), ['twig' => $this->twig]);

        return [$controller, $container];
    }
}
