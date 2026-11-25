<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Controller;

use Closure;
use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Nowo\WikiBundle\Controller\WikiTreeController;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Entity\WikiTrashEntry;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Port\Internal\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiTrashRepository;
use Nowo\WikiBundle\Repository\WikiPageTreeWriterInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Service\WikiPageDuplicator;
use Nowo\WikiBundle\Service\WikiPageMoveException;
use Nowo\WikiBundle\Service\WikiPageMover;
use Nowo\WikiBundle\Service\WikiPageNotFoundException;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiPageServiceInterface;
use Nowo\WikiBundle\Service\WikiQuotaGate;
use Nowo\WikiBundle\Service\WikiSlugTakenException;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\RecordingQuotaListener;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use Nowo\WikiBundle\Tests\Support\WikiControllerContainerBuilder;
use Nowo\WikiBundle\Trash\WikiTrashService;
use Nowo\WikiBundle\Tree\SessionWikiTreeStateStore;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

use function array_column;
use function array_map;
use function json_decode;
use function json_encode;
use function mb_strlen;
use function str_repeat;
use function usort;

use const JSON_THROW_ON_ERROR;

/**
 * 1.41 (tree.context_menu): Archive / Unarchive, Delete (trash), Duplicate and the parents of "Move to…" — the rights
 * of each entry (the manage pages' role gates and space checks), the CSRF token, the trash with sub-pages, the copy's
 * place and slug, and the refusals that answer a clear status instead of a 500.
 */
final class WikiTreeControllerMenuActionsTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    private WikiPageService $service;

    private TestUserEntity $user;

    private WikiPage $ops;

    private WikiPage $run;

    private WikiPage $secret;

    private WikiPage $guide;

    private WikiPage $hidden;

    private WikiPage $plan;

    private ?Container $container = null;

    protected function setUp(): void
    {
        RecordingQuotaListener::$seen     = [];
        RecordingQuotaListener::$denyWith = null;
        $this->em                         = SqliteWikiEntityManager::create();
        $this->space                      = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $this->em->persist($this->space);
        $this->user = new TestUserEntity();
        $this->em->persist($this->user);
        $this->em->flush();
        $this->service = new WikiPageService(new DoctrineOrmWikiPageRepository($this->em), new DoctrineOrmWikiPageRevisionRepository($this->em), new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher());
        $this->ops     = $this->page('Operations');
        $this->run     = $this->page('Runbooks', $this->ops);
        // A sibling of Runbooks the user may not read, and a hidden root page with a readable sub-page (DI-L1).
        $this->secret = $this->page('Secret', $this->ops);
        $this->guide  = $this->page('Guide');
        $this->hidden = $this->page('Hidden');
        $this->plan   = $this->page('Plan', $this->hidden);
    }

    public function testEveryActionAsksTheTokenTheOriginAndTheMenu(): void
    {
        foreach (['archive', 'delete', 'duplicate', 'parents', 'unarchive'] as $action) {
            foreach ([
                'token'  => fn (): Request => $this->post(['page' => $this->guide->getId(), '_token' => 'forged']),
                'origin' => fn (): Request => $this->post(['page' => $this->guide->getId()], 'https://evil.example'),
            ] as $case => $request) {
                try {
                    $this->controller()->tree($request(), 'eng', $action);
                    self::fail($action . ': ' . $case . ' accepted');
                } catch (AccessDeniedHttpException) {
                }
            }
            try {
                $this->controller(menu: false)->tree($this->post(['page' => $this->guide->getId()]), 'eng', $action);
                self::fail($action . ': accepted without the menu');
            } catch (AccessDeniedHttpException) {
            }
        }
        self::assertFalse($this->guide->isArchived());
    }

    /**
     * @return iterable<string, array{0: string, 1: array{edit?: bool, create?: bool, archive?: bool}, 2: WikiAction|null}>
     */
    public static function refusals(): iterable
    {
        yield 'archive without the archive role' => ['archive', ['archive' => false], null];
        yield 'archive without Archive on the page' => ['archive', [], WikiAction::Archive];
        yield 'delete without the archive role' => ['delete', ['archive' => false], null];
        yield 'delete without Delete on the page' => ['delete', [], WikiAction::Delete];
        yield 'duplicate without the create role' => ['duplicate', ['create' => false], null];
        yield 'duplicate without the edit role' => ['duplicate', ['edit' => false], null];
        yield 'duplicate without Create on the space' => ['duplicate', [], WikiAction::Create];
        yield 'duplicate without Edit on the parent' => ['duplicate', [], WikiAction::Edit];
        yield 'parents without the edit role' => ['parents', ['edit' => false], null];
        yield 'parents without Edit on the page' => ['parents', [], WikiAction::Edit];
    }

    /**
     * @param array{edit?: bool, create?: bool, archive?: bool} $roles
     */
    #[DataProvider('refusals')]
    public function testThePermissionMatrix(string $action, array $roles, ?WikiAction $denied): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        try {
            $this->controller(roles: $roles, deny: $denied)->tree($this->post(['page' => $this->run->getId()]), 'eng', $action);
        } finally {
            self::assertFalse($this->run->isArchived());
            self::assertCount(6, $this->active());
        }
    }

    public function testAHiddenPageOrOneUnderAHiddenPageIsNotFound(): void
    {
        foreach (['archive', 'delete', 'duplicate', 'parents'] as $action) {
            foreach ([$this->secret, $this->plan] as $page) {
                try {
                    $this->controller()->tree($this->post(['page' => $page->getId()]), 'eng', $action);
                    self::fail($action . ' reached a hidden page');
                } catch (NotFoundHttpException) {
                }
            }
        }
        self::assertCount(6, $this->active());
    }

    public function testArchiveThenUnarchive(): void
    {
        $answer = $this->controller()->tree($this->post(['page' => $this->run->getId()]), 'eng', 'archive');
        self::assertSame(200, $answer->getStatusCode());
        self::assertSame('no-store, private', $answer->headers->get('Cache-Control'));
        self::assertSame(['page' => $this->run->getId(), 'message' => 'wiki.tree_menu.archived'], $this->json($answer));
        self::assertTrue($this->run->isArchived());

        // An archived page is not a tree row any more: the other entries answer 404 for it.
        try {
            $this->controller()->tree($this->post(['page' => $this->run->getId()]), 'eng', 'duplicate');
            self::fail('an archived page was duplicated');
        } catch (NotFoundHttpException) {
        }

        $back = $this->controller()->tree($this->post(['page' => $this->run->getId()]), 'eng', 'unarchive');
        self::assertSame(['page' => $this->run->getId(), 'message' => 'wiki.tree_menu.unarchived'], $this->json($back));
        self::assertFalse($this->run->isArchived());
    }

    public function testUnarchiveRevealsNothing(): void
    {
        $this->service->archive($this->secret);
        $this->service->archive($this->guide);
        foreach ([
            'an active page'              => [$this->run->getId(), []],
            'a hidden archived page'      => [$this->secret->getId(), []],
            'an unknown id'               => ['nope', []],
            'an invalid id'               => ['../x', []],
            'not a string'                => [12, []],
            'without the unarchive route' => [$this->guide->getId(), ['routes' => ['page_view' => ['path' => '/', 'name' => 'nowo_wiki_page_view']]]],
            'a page of another space'     => [$this->foreign()->getId(), []],
        ] as $case => [$id, $options]) {
            try {
                $this->controller(...$options)->tree($this->post(['page' => $id]), 'eng', 'unarchive');
                self::fail($case);
            } catch (NotFoundHttpException) {
            }
        }
        foreach ([['roles' => ['archive' => false]], ['deny' => WikiAction::Archive]] as $options) {
            try {
                $this->controller(...$options)->tree($this->post(['page' => $this->guide->getId()]), 'eng', 'unarchive');
                self::fail('unarchived without the rights');
            } catch (AccessDeniedHttpException) {
            }
        }
        self::assertTrue($this->guide->isArchived());

        // A trashed page comes back with its deletion only (the trash's Restore).
        $this->trash()->delete($this->ops);
        $this->expectException(NotFoundHttpException::class);
        $this->controller()->tree($this->post(['page' => $this->ops->getId()]), 'eng', 'unarchive');
    }

    public function testUnarchiveAsksTheHostsQuota(): void
    {
        $this->service->archive($this->guide);
        RecordingQuotaListener::$denyWith = 'Your plan is full.';
        $answer                           = $this->controller()->tree($this->post(['page' => $this->guide->getId()]), 'eng', 'unarchive');
        self::assertSame(422, $answer->getStatusCode());
        self::assertSame(['error' => 'quota', 'message' => 'Your plan is full.'], $this->json($answer));
        self::assertTrue($this->guide->isArchived());
    }

    public function testArchiveNeedsTheArchiveRoute(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->controller(routes: [])->tree($this->post(['page' => $this->guide->getId()]), 'eng', 'archive');
    }

    public function testDeleteSendsThePageAndItsSubPagesToTheTrash(): void
    {
        $preview = $this->controller()->tree($this->post(['page' => $this->ops->getId(), 'preview' => true]), 'eng', 'delete');
        // Operations and Runbooks: the hidden Secret goes too, uncounted (audit DO-I2).
        self::assertSame(['count' => 2, 'message' => 'wiki.tree_menu.delete_confirm'], $this->json($preview));
        self::assertFalse($this->ops->isArchived());

        $answer = $this->controller()->tree($this->post(['page' => $this->ops->getId()]), 'eng', 'delete');
        self::assertSame(['page' => $this->ops->getId(), 'count' => 2, 'message' => 'wiki.tree_menu.deleted'], $this->json($answer));
        $entries = $this->em->getRepository(WikiTrashEntry::class)->findAll();
        self::assertCount(3, $entries);
        $titles = array_map(static fn (WikiTrashEntry $entry): string => $entry->getPage()->getTitle(), $entries);
        usort($titles, static fn (string $a, string $b): int => $a <=> $b);
        self::assertSame(['Operations', 'Runbooks', 'Secret'], $titles);
        self::assertTrue($this->run->isArchived());

        // Deleted again by a request that read it before the first one locked: the page left (410), no second deletion.
        $connection = $this->em->getConnection();
        $connection->executeStatement('UPDATE wiki_pages SET archived_at = NULL WHERE id = ?', [$this->ops->getId()]);
        $again = $this->controller()->tree($this->post(['page' => $this->ops->getId()]), 'eng', 'delete');
        self::assertSame(410, $again->getStatusCode());
        self::assertSame('page_gone', $this->json($again)['error']);
        self::assertCount(3, $this->em->getRepository(WikiTrashEntry::class)->findAll());
    }

    public function testDeleteWithoutTheTrashIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->controller(withTrash: false)->tree($this->post(['page' => $this->guide->getId()]), 'eng', 'delete');
    }

    public function testDuplicateCopiesThePageRightBelowIt(): void
    {
        $this->service->saveRevision($this->run, 'Runbooks', '<p>Second</p>', $this->user);
        $answer = $this->controller()->tree($this->post(['page' => $this->run->getId()]), 'eng', 'duplicate');
        self::assertSame(200, $answer->getStatusCode());
        $data = $this->json($answer);
        self::assertSame('/generated/nowo_wiki_page_view', $data['url']);
        self::assertSame('wiki.tree_menu.duplicated', $data['message']);
        $copy = $this->em->find(WikiPage::class, $data['page']);
        self::assertInstanceOf(WikiPage::class, $copy);
        self::assertSame('Runbooks wiki.tree_menu.copy_suffix', $copy->getTitle());
        self::assertSame('runbooks-copy', $copy->getSlug());
        self::assertSame($this->ops, $copy->getParent());
        self::assertSame('<p>Second</p>', $copy->getCurrentRevision()?->getContentHtml());
        self::assertSame(1, $copy->getCurrentRevision()?->getRevisionNumber());
        self::assertSame(['Runbooks', 'Runbooks wiki.tree_menu.copy_suffix', 'Secret'], $this->childTitles($this->ops));
        // The page only (owner default 1.41): no sub-page is copied.
        self::assertSame([], $this->childTitles($copy));
        self::assertSame(['wiki.tree_menu.duplicated'], $this->flashes());

        // Again: the next free slug.
        $second = $this->controller()->tree($this->post(['page' => $this->run->getId()]), 'eng', 'duplicate');
        self::assertSame('runbooks-copy-2', $this->em->find(WikiPage::class, $this->json($second)['page'])?->getSlug());
    }

    public function testDuplicateKeepsTheTitleWithinTheColumn(): void
    {
        $long   = $this->page(str_repeat('a', 255));
        $answer = $this->controller()->tree($this->post(['page' => $long->getId()]), 'eng', 'duplicate');
        $copy   = $this->em->find(WikiPage::class, $this->json($answer)['page']);
        self::assertSame(255, mb_strlen((string) $copy?->getTitle()));
    }

    public function testDuplicateRefusals(): void
    {
        RecordingQuotaListener::$denyWith = 'No more pages.';
        $quota                            = $this->controller()->tree($this->post(['page' => $this->run->getId()]), 'eng', 'duplicate');
        self::assertSame(['error' => 'quota', 'message' => 'No more pages.'], $this->json($quota));
        RecordingQuotaListener::$denyWith = null;

        foreach ([
            [new WikiPageMoveException('stale'), 409, 'stale'],
            [new WikiPageMoveException('too_deep'), 422, 'too_deep'],
            [new WikiSlugTakenException('Page slug already exists in this space.'), 409, 'conflict'],
            [new WikiPageNotFoundException('gone'), 410, 'page_gone'],
        ] as [$exception, $status, $error]) {
            $answer = $this->controller(duplicateFails: $exception)->tree($this->post(['page' => $this->run->getId()]), 'eng', 'duplicate');
            self::assertSame($status, $answer->getStatusCode());
            self::assertSame($error, $this->json($answer)['error']);
        }

        // An ORM error is not a refusal (audit SH-L1).
        $this->expectException(InvalidArgumentException::class);
        $this->controller(duplicateFails: new InvalidArgumentException('ORM'))->tree($this->post(['page' => $this->run->getId()]), 'eng', 'duplicate');
    }

    public function testDuplicateNeedsTheService(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->controller(withDuplicator: false)->tree($this->post(['page' => $this->run->getId()]), 'eng', 'duplicate');
    }

    /** Audit SM-I3: a host without the new-page route gets no copies either. */
    public function testDuplicateNeedsTheNewPageRoute(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->controller(routes: ['page_view' => ['path' => '/', 'name' => 'nowo_wiki_page_view']])->tree($this->post(['page' => $this->run->getId()]), 'eng', 'duplicate');
    }

    public function testParentsAreTheMoveFormsChoices(): void
    {
        $deep   = $this->page('Deep', $this->run);
        $locked = $this->page('Locked');
        $answer = $this->controller(denyEditOn: $locked)->tree($this->post(['page' => $this->run->getId()]), 'eng', 'parents');
        $data   = $this->json($answer);
        // Not Runbooks nor its sub-tree (a cycle), not the hidden pages, not the page without Edit.
        self::assertSame(['Guide', 'Operations'], array_column($data['parents'], 'label'));
        self::assertSame([0, 0], array_column($data['parents'], 'depth'));
        self::assertTrue($data['root']);
        self::assertSame($this->ops->getId(), $data['parent']);
        self::assertNotContains($deep->getId(), array_column($data['parents'], 'id'));

        $nested = $this->json($this->controller()->tree($this->post(['page' => $this->guide->getId()]), 'eng', 'parents'));
        self::assertSame(['Locked', 'Operations', '— Runbooks', '— — Deep'], array_column($nested['parents'], 'label'));
        self::assertNull($nested['parent']);

        // A capped tree offers its first pages only.
        $capped = $this->json($this->controller(pagination: ['max_tree_pages' => 2])->tree($this->post(['page' => $this->guide->getId()]), 'eng', 'parents'));
        self::assertCount(2, $capped['parents']);
    }

    public function testAMoveThatLostARaceIsAConflictAndACycleIsRefused(): void
    {
        $cycle = $this->controller()->tree($this->post(['page' => $this->ops->getId(), 'parent' => $this->run->getId(), 'index' => 0]), 'eng', 'move');
        self::assertSame(422, $cycle->getStatusCode());
        self::assertSame('cycle', $this->json($cycle)['error']);

        // Another request moved the page between this one's read and the space lock: 409, not a 500.
        $connection = $this->em->getConnection();
        $id         = $this->run->getId();
        $stale      = $this->controller(onLock: static fn (): int|string => $connection->executeStatement('UPDATE wiki_pages SET parent_id = NULL WHERE id = ?', [$id]))->tree($this->post(['page' => $this->run->getId(), 'parent' => null, 'index' => 0]), 'eng', 'move');
        self::assertSame(409, $stale->getStatusCode());
        self::assertSame('stale', $this->json($stale)['error']);
    }

    // --- helpers ----------------------------------------------------------------------------------------------------

    private function page(string $title, ?WikiPage $parent = null): WikiPage
    {
        return $this->service->create($this->space, $title, '<p>' . $title . '</p>', $this->user, $parent);
    }

    private function foreign(): WikiPage
    {
        $other = new WikiSpace('ops', 'Ops', WikiSpaceOwnerScope::Team, 't');
        $this->em->persist($other);
        $this->em->flush();
        $page = $this->service->create($other, 'Foreign', '<p>x</p>', $this->user);
        $this->service->archive($page);

        return $page;
    }

    /** @return list<WikiPage> */
    private function active(): array
    {
        return (new DoctrineOrmWikiPageRepository($this->em))->findActiveBySpace($this->space);
    }

    /** @return list<string> */
    private function childTitles(WikiPage $parent): array
    {
        $children = $this->em->getRepository(WikiPage::class)->findBy(['parent' => $parent, 'archivedAt' => null], ['position' => 'ASC']);

        return array_map(static fn (WikiPage $p): string => $p->getTitle(), $children);
    }

    private function trash(): WikiTrashService
    {
        $repository = new DoctrineOrmWikiPageRepository($this->em);

        return new WikiTrashService($repository, new DoctrineOrmWikiTrashRepository($this->em), $repository, new EventDispatcher());
    }

    /**
     * @param array{edit?: bool, create?: bool, archive?: bool} $roles
     * @param array<string, array{path: string, name: string}>|null $routes
     * @param array<string, int> $pagination
     */
    private function controller(
        array $roles = [],
        ?WikiAction $deny = null,
        ?WikiPage $denyEditOn = null,
        bool $menu = true,
        bool $withTrash = true,
        bool $withDuplicator = true,
        ?Throwable $duplicateFails = null,
        ?array $routes = null,
        array $pagination = [],
        ?Closure $onLock = null,
    ): WikiTreeController {
        $repository = new DoctrineOrmWikiPageRepository($this->em);
        $hidden     = [$this->secret->getId() => true, $this->hidden->getId() => true];
        $checker    = new CallbackSpaceAccessChecker(static fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => !($action === WikiAction::View && $page instanceof WikiPage && isset($hidden[$page->getId()]))
            && $action !== $deny && !($action === WikiAction::Edit && $denyEditOn instanceof WikiPage && $page === $denyEditOn));
        $access = $this->createStub(WikiAccessCheckerInterface::class);
        $access->method('canEdit')->willReturn($roles['edit'] ?? true);
        $access->method('canCreate')->willReturn($roles['create'] ?? true);
        $access->method('canArchive')->willReturn($roles['archive'] ?? true);
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $writer = $onLock instanceof Closure ? new class($repository, $onLock) implements WikiPageTreeWriterInterface {
            public function __construct(private readonly WikiPageTreeWriterInterface $inner, private readonly Closure $onLock)
            {
            }

            public function beginSpaceTransaction(WikiSpace $space): void
            {
                $this->inner->beginSpaceTransaction($space);
                ($this->onLock)();
            }

            public function commitSpaceTransaction(): void
            {
                $this->inner->commitSpaceTransaction();
            }

            public function rollbackSpaceTransaction(): void
            {
                $this->inner->rollbackSpaceTransaction();
            }
        } : $repository;
        $mover      = new WikiPageMover($repository, $writer);
        $duplicator = new WikiPageDuplicator($this->service, $mover, $repository, new WikiSlugger());
        if ($duplicateFails instanceof Throwable) {
            $pages = $this->createStub(WikiPageServiceInterface::class);
            $pages->method('create')->willThrowException($duplicateFails);
            $duplicator = new WikiPageDuplicator($pages, $mover, $repository, new WikiSlugger());
        }
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WikiEvents::QUOTA_CHECK, (new RecordingQuotaListener())->onCheck(...));

        $controller = new WikiTreeController(
            new WikiSpaceGuard(new FixedSpaceLocator($this->space), $checker),
            $repository,
            $access,
            $mover,
            new SessionWikiTreeStateStore(new RequestStack([$request])),
            pageService: $this->service,
            contextMenu: $menu,
            trash: $withTrash ? $this->trash() : null,
            duplicator: $withDuplicator ? $duplicator : null,
            quota: new WikiQuotaGate($dispatcher),
            routes: $routes ?? [
                'page_view'      => ['path' => '/', 'name' => 'nowo_wiki_page_view'],
                'page_new'       => ['path' => '/', 'name' => 'nowo_wiki_page_new'],
                'page_archive'   => ['path' => '/', 'name' => 'nowo_wiki_page_archive'],
                'page_unarchive' => ['path' => '/', 'name' => 'nowo_wiki_page_unarchive'],
            ],
            pagination: $pagination,
        );
        $this->container = WikiControllerContainerBuilder::bind($controller, $this->user);

        return $controller;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(array $body, string $origin = 'http://localhost'): Request
    {
        $body['_token'] ??= WikiControllerContainerBuilder::csrfToken($this->container ?? WikiControllerContainerBuilder::bind($this->controller(), $this->user), WikiTreeController::csrfTokenId($this->space));

        return Request::create('http://localhost/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => $origin], json_encode($body, JSON_THROW_ON_ERROR));
    }

    /** @return list<mixed> */
    private function flashes(): array
    {
        $stack   = $this->container?->get('request_stack');
        $session = $stack instanceof RequestStack ? $stack->getSession() : null;

        return $session instanceof FlashBagAwareSessionInterface ? $session->getFlashBag()->get('success') : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Response $response): array
    {
        /* @var array<string, mixed> */
        return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
