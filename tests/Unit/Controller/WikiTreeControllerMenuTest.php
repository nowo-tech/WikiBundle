<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Controller;

use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Nowo\IconSelectorBundle\Emoji\EmojiCatalog;
use Nowo\WikiBundle\Collaboration\WikiCollabPublisherInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Nowo\WikiBundle\Collaboration\WikiCollabSubscriberAuthorizerInterface;
use Nowo\WikiBundle\Controller\WikiTreeController;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Service\WikiPageIconService;
use Nowo\WikiBundle\Service\WikiPageMover;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiPageServiceInterface;
use Nowo\WikiBundle\Service\WikiRevisionCollisionException;
use Nowo\WikiBundle\Service\WikiRevisionConflictException;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\InMemoryCollabStateStore;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use Nowo\WikiBundle\Tests\Support\WikiControllerContainerBuilder;
use Nowo\WikiBundle\Tree\SessionWikiTreeStateStore;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function class_exists;
use function json_decode;
use function json_encode;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

/**
 * 1.39 (tree.context_menu): Rename and Change icon of the tree menu — rights, validation, revisions, conflicts.
 */
final class WikiTreeControllerMenuTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    private WikiPage $page;

    private WikiPage $locked;

    private WikiPageService $service;

    private Container $container;

    private TestUserEntity $user;

    protected function setUp(): void
    {
        if (!class_exists(EmojiCatalog::class)) {
            self::markTestSkipped('nowo-tech/icon-selector-bundle >= 1.6 (dev dependency) is not installed.');
        }
        $this->em    = SqliteWikiEntityManager::create();
        $this->space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $this->em->persist($this->space);
        $this->user = new TestUserEntity();
        $this->em->persist($this->user);
        $this->em->flush();
        $this->service = new WikiPageService(new DoctrineOrmWikiPageRepository($this->em), new DoctrineOrmWikiPageRevisionRepository($this->em), new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher());
        $this->page    = $this->service->create($this->space, 'Variables', '<p>Body</p>', $this->user);
        $this->locked  = $this->service->create($this->space, 'Locked', '<p>x</p>', $this->user);
    }

    public function testRenameAddsARevisionWithTheSameBody(): void
    {
        $before = $this->page->getCurrentRevision()?->getRevisionNumber();
        $answer = $this->controller()->tree($this->post(['page' => $this->page->getId(), 'title' => '  Variables por tipo  ']), 'eng', 'rename');

        self::assertSame(200, $answer->getStatusCode());
        self::assertSame(['title' => 'Variables por tipo', 'message' => 'wiki.tree_menu.renamed'], $this->json($answer->getContent()));
        self::assertSame('Variables por tipo', $this->page->getTitle());
        self::assertSame(($before ?? 0) + 1, $this->page->getCurrentRevision()?->getRevisionNumber());
        self::assertSame('<p>Body</p>', $this->page->getCurrentRevision()?->getContentHtml());

        // The same title writes nothing.
        $same = $this->controller()->tree($this->post(['page' => $this->page->getId(), 'title' => 'Variables por tipo']), 'eng', 'rename');
        self::assertSame(['title' => 'Variables por tipo', 'message' => ''], $this->json($same->getContent()));
        self::assertSame(($before ?? 0) + 1, $this->page->getCurrentRevision()?->getRevisionNumber());
    }

    public function testRenameRefusesBadTitles(): void
    {
        foreach (['', '   ', str_repeat('a', 256), "Bad\u{202E}title", "Line\nbreak", 12] as $title) {
            $answer = $this->controller()->tree($this->post(['page' => $this->page->getId(), 'title' => $title]), 'eng', 'rename');
            self::assertSame(422, $answer->getStatusCode());
            self::assertSame('title_invalid', $this->json($answer->getContent())['error']);
        }
        self::assertSame('Variables', $this->page->getTitle());
    }

    public function testWritesNeedTheEditRoleTheRightOnThePageAndTheMenu(): void
    {
        foreach ([
            fn (): WikiTreeController => $this->controller(roleEdit: false),
            fn (): WikiTreeController => $this->controller(deny: $this->locked),
            fn (): WikiTreeController => $this->controller(menu: false),
        ] as $make) {
            try {
                $make()->tree($this->post(['page' => $this->locked->getId(), 'title' => 'New']), 'eng', 'rename');
                self::fail('The rename was not refused.');
            } catch (AccessDeniedHttpException) {
                self::assertSame('Locked', $this->locked->getTitle());
            }
        }
    }

    public function testRenameWithoutPageServiceOrRevisionIs404(): void
    {
        try {
            $this->controller(service: false)->tree($this->post(['page' => $this->page->getId(), 'title' => 'X']), 'eng', 'rename');
            self::fail('404 expected');
        } catch (NotFoundHttpException) {
        }

        $bare = new WikiPage($this->space, 'bare', 'Bare');
        $this->em->persist($bare);
        $this->em->flush();
        $this->expectException(NotFoundHttpException::class);
        $this->controller()->tree($this->post(['page' => $bare->getId(), 'title' => 'X']), 'eng', 'rename');
    }

    public function testChangeIconStoresIconsAndEmojiWithoutARevision(): void
    {
        $before = $this->page->getCurrentRevision()?->getRevisionNumber();
        $answer = $this->controller()->tree($this->post(['page' => $this->page->getId(), 'icon' => 'emoji:🚀']), 'eng', 'icon');

        self::assertSame(200, $answer->getStatusCode());
        $data = $this->json($answer->getContent());
        self::assertSame('emoji:🚀', $data['icon']);
        self::assertSame('wiki.tree_menu.icon_saved', $data['message']);
        self::assertIsString($data['icon_html']);
        self::assertSame('emoji:🚀', $this->page->getIcon());
        self::assertSame($before, $this->page->getCurrentRevision()?->getRevisionNumber());

        $cleared = $this->controller()->tree($this->post(['page' => $this->page->getId(), 'icon' => null]), 'eng', 'icon');
        self::assertNull($this->json($cleared->getContent())['icon']);
        self::assertNull($this->page->getIcon());
    }

    public function testChangeIconRefusesInvalidValues(): void
    {
        foreach (['emoji:rocket', 'emoji:🚀🚀', 'mdi:home', "emoji:\u{202E}🚀"] as $icon) {
            $answer = $this->controller()->tree($this->post(['page' => $this->page->getId(), 'icon' => $icon]), 'eng', 'icon');
            self::assertSame(422, $answer->getStatusCode(), $icon);
            self::assertSame('icon_invalid', $this->json($answer->getContent())['error']);
        }
        self::assertNull($this->page->getIcon());

        $this->expectException(BadRequestHttpException::class);
        $this->controller()->tree($this->post(['page' => $this->page->getId(), 'icon' => ['x']]), 'eng', 'icon');
    }

    public function testRenameConflictsAnswer409AndInvalidTitlesFromTheService422(): void
    {
        foreach ([[new WikiRevisionConflictException(1, 2), 409, 'conflict'], [new WikiRevisionCollisionException('race'), 409, 'conflict'], [new InvalidArgumentException('slug'), 422, 'title_invalid']] as [$exception, $status, $error]) {
            $service = $this->createStub(WikiPageServiceInterface::class);
            $service->method('saveRevision')->willThrowException($exception);
            $answer = $this->controller(pageService: $service)->tree($this->post(['page' => $this->page->getId(), 'title' => 'Other']), 'eng', 'rename');
            self::assertSame($status, $answer->getStatusCode());
            self::assertSame($error, $this->json($answer->getContent())['error']);
        }
        self::assertSame('Variables', $this->page->getTitle());
    }

    public function testCoEditorsUnsavedTextRefusesTheRenameAndASavedLogLetsItThrough(): void
    {
        $store  = new InMemoryCollabStateStore();
        $collab = new WikiCollabService($store, $this->createStub(WikiCollabPublisherInterface::class), $this->createStub(WikiCollabSubscriberAuthorizerInterface::class), secret: 'test-secret');
        $store->append($this->page->getId(), 'unsaved update');

        $answer = $this->controller(collab: $collab)->tree($this->post(['page' => $this->page->getId(), 'title' => 'Other']), 'eng', 'rename');
        self::assertSame(409, $answer->getStatusCode());
        self::assertSame('being_edited', $this->json($answer->getContent())['error']);
        self::assertSame('Variables', $this->page->getTitle());

        $ok = $this->controller(collab: $collab)->tree($this->post(['page' => $this->locked->getId(), 'title' => 'Unlocked']), 'eng', 'rename');
        self::assertSame(200, $ok->getStatusCode());
        self::assertSame('Unlocked', $this->locked->getTitle());
    }

    public function testChangeIconNeedsTheIconField(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->controller(iconField: false)->tree($this->post(['page' => $this->page->getId(), 'icon' => 'tabler:home']), 'eng', 'icon');
    }

    private function controller(bool $roleEdit = true, ?WikiPage $deny = null, bool $menu = true, bool $service = true, bool $iconField = true, ?WikiPageServiceInterface $pageService = null, ?WikiCollabService $collab = null): WikiTreeController
    {
        $repository = new DoctrineOrmWikiPageRepository($this->em);
        $checker    = new CallbackSpaceAccessChecker(static fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => !($deny !== null && $page === $deny && $action === WikiAction::Edit));
        $roles      = $this->createStub(WikiAccessCheckerInterface::class);
        $roles->method('canEdit')->willReturn($roleEdit);
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $controller = new WikiTreeController(
            new WikiSpaceGuard(new FixedSpaceLocator($this->space), $checker),
            $repository,
            $roles,
            new WikiPageMover($repository, $repository),
            new SessionWikiTreeStateStore(new RequestStack([$request])),
            pageService: $service ? ($pageService ?? $this->service) : null,
            pageIcons: new WikiPageIconService($repository, ['tabler', 'emoji'], emojiCatalog: new EmojiCatalog()),
            pageIconField: $iconField,
            collab: $collab,
            contextMenu: $menu,
        );
        $this->container = WikiControllerContainerBuilder::bind($controller, $this->user);

        return $controller;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(array $body): Request
    {
        $body['_token'] = WikiControllerContainerBuilder::csrfToken($this->container ?? WikiControllerContainerBuilder::bind($this->controller(), $this->user), WikiTreeController::csrfTokenId($this->space));

        return Request::create('http://localhost/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost'], json_encode($body, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string|false $content): array
    {
        /* @var array<string, mixed> */
        return json_decode((string) $content, true, 512, JSON_THROW_ON_ERROR);
    }
}
