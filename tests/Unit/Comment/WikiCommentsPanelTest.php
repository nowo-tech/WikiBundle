<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Comment;

use Nowo\MilkdownEditorBundle\ProfileRegistry;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Comment\WikiCommentsPanel;
use Nowo\WikiBundle\Controller\WikiCommentController;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\History\WikiAuthorLabel;
use Nowo\WikiBundle\Markdown\WikiMarkdownRenderer;
use Nowo\WikiBundle\Panel\WikiPanelStateStoreInterface;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiCommentRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use Nowo\WikiBundle\Tests\Support\WikiControllerContainerBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function in_array;

/**
 * 1.16: the panel of the read / edit views — only with ViewComments, the open state (query, else remembered), the
 * badge count, the no-JavaScript list and form, and the Milkdown options that mount the thread component.
 */
final class WikiCommentsPanelTest extends TestCase
{
    /** @var list<WikiAction> */
    private array $granted;

    private bool $remembered = false;

    public function testNoPanelWithoutViewComments(): void
    {
        [$panel, $user, $space, $page] = $this->panel([WikiAction::Comment]);

        self::assertNull($panel->panel(Request::create('/x'), $user, $space, $page));
        self::assertSame(['features' => ['a']], $panel->editorOptions(['features' => ['a']], null, $page));
    }

    public function testThePanelOfAViewer(): void
    {
        [$panel, $user, $space, $page, $service] = $this->panel([WikiAction::ViewComments, WikiAction::Comment]);
        $service->create($user, $space, $page, null, 'Page note');

        $closed = $panel->panel(Request::create('/wiki/run?x=1'), $user, $space, $page);
        self::assertNotNull($closed);
        self::assertSame(WikiCommentsPanel::ID, $closed['id']);
        self::assertFalse($closed['open']);
        self::assertSame(1, $closed['count']);
        self::assertSame('Alt+Shift+C', $closed['shortcut']);
        self::assertSame('/wiki/run?comments=1', $closed['toggle_href']);
        self::assertSame('/wiki/run?comments=0', $closed['close_href']);
        self::assertSame('/r/nowo_wiki_page_comments', $closed['comments_url']);
        self::assertSame('csrf:' . WikiCommentService::csrfTokenId($page), $closed['csrf_token']);
        self::assertSame('/r/nowo_wiki_space_panel_state', $closed['state_url']);
        self::assertSame('csrf:' . WikiCommentController::panelTokenId($space), $closed['state_token']);
        self::assertSame([], $closed['page_threads']);
        self::assertNull($closed['form']);

        $open = $panel->panel(Request::create('/wiki/run?comments=1'), $user, $space, $page);
        self::assertNotNull($open);
        self::assertTrue($open['open']);
        self::assertSame('/wiki/run?comments=0', $open['toggle_href']);
        self::assertCount(1, $open['page_threads']);
        self::assertInstanceOf(FormView::class, $open['form']);
        self::assertSame('/r/nowo_wiki_page_comments/new', $open['form']->vars['action']);

        // The remembered state applies unless the query overrides it.
        $this->remembered = true;
        self::assertTrue($panel->panel(null, $user, $space, $page)['open'] ?? null);
        self::assertSame('?comments=0', $panel->panel(null, $user, $space, $page)['toggle_href'] ?? null);
        self::assertFalse($panel->panel(Request::create('/x?comments=0'), $user, $space, $page)['open'] ?? null);
    }

    public function testWithoutTheComponentTheServerRendersEveryOpenThread(): void
    {
        // 1.16 default for commenters who may not edit: no page source, the panel is the server list.
        [$panel, $user, $space, $page, $service] = $this->panel([WikiAction::ViewComments, WikiAction::Comment]);
        $service->create($user, $space, $page, ['v' => 1, 'quote' => ['exact' => 'deploy', 'prefix' => '', 'suffix' => ''], 'position' => ['start' => 0, 'end' => 6], 'block' => ['index' => 0, 'heading' => null]], 'Inline note');
        $service->create($user, $space, $page, null, 'Page note');

        // Closed: nothing is read (audit EI-L4); opening it in place reloads the page with ?comments=1.
        $closed = $panel->panel(Request::create('/wiki/run'), $user, $space, $page, false);
        self::assertNotNull($closed);
        self::assertFalse($closed['open']);
        self::assertFalse($closed['mounted']);
        self::assertSame([], $closed['page_threads']);
        self::assertNull($closed['form']);
        self::assertSame('/wiki/run?comments=1', $closed['reload_href']);

        $server = $panel->panel(Request::create('/wiki/run?comments=1'), $user, $space, $page, false);
        self::assertNotNull($server);
        self::assertCount(2, $server['page_threads']);
        self::assertFalse($server['truncated']);
        self::assertNull($server['reload_href']);
        self::assertInstanceOf(FormView::class, $server['form']);
        self::assertFalse($panel->readViewSource());
        self::assertNull($server['edit_href']);
        self::assertSame('/wiki/run/edit?comments=1', $panel->panel(null, $user, $space, $page, false, '/wiki/run/edit?comments=1')['edit_href'] ?? null);

        // The list is capped.
        for ($i = 0; $i < WikiCommentService::LIST_CAP; ++$i) {
            $service->create($user, $space, $page, null, 'Note ' . $i);
        }
        $capped = $panel->panel(Request::create('/wiki/run?comments=1'), $user, $space, $page, false);
        self::assertCount(WikiCommentService::LIST_CAP, $capped['page_threads'] ?? []);
        self::assertTrue($capped['truncated'] ?? null);

        $mounted = $panel->panel(Request::create('/wiki/run'), $user, $space, $page);
        self::assertTrue($mounted['mounted'] ?? null);
        self::assertSame([], $mounted['page_threads'] ?? null);
    }

    public function testMilkdownOptionsMountTheThreadComponent(): void
    {
        [$panel, $user, $space, $page] = $this->panel([WikiAction::ViewComments]);
        $data                          = $panel->panel(null, $user, $space, $page);
        self::assertNotNull($data);

        $fromProfile = $panel->editorOptions(['profile' => 'wiki', 'upload_url' => '/u'], $data, $page);
        self::assertSame(['toolbar', 'table', 'comments'], $fromProfile['features']);
        self::assertSame('/u', $fromProfile['upload_url']);
        self::assertSame([
            'url'        => '/r/nowo_wiki_page_comments',
            'csrf_token' => 'csrf:' . WikiCommentService::csrfTokenId($page),
            'can_create' => false,
            'panel'      => 'container',
            'container'  => WikiCommentsPanel::MOUNT,
            'topic'      => null,
        ], $fromProfile['comments']);

        self::assertSame(['a', 'comments'], $panel->editorOptions(['features' => ['a', 'comments']], $data, $page, true)['features']);
        self::assertSame(['toolbar', 'table', 'comments'], $panel->editorOptions([], $data, $page)['features']);
        $bare = $this->panel([WikiAction::ViewComments], profiles: false, readViewSource: true)[0];
        self::assertTrue($bare->readViewSource());
        self::assertSame(['comments'], $bare->editorOptions([], $data, $page)['features']);
    }

    /**
     * @param list<WikiAction> $granted
     *
     * @return array{0: WikiCommentsPanel, 1: UserInterface, 2: WikiSpace, 3: WikiPage, 4: WikiCommentService}
     */
    private function panel(array $granted, bool $profiles = true, bool $readViewSource = false): array
    {
        $this->granted = $granted;
        $em            = SqliteWikiEntityManager::create();
        $space         = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $page          = new WikiPage($space, 'run', 'Runbooks');
        $user          = (new TestUserEntity('id-ana'))->withDisplayName('Ana Garcia');
        foreach ([$space, $page, $user] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $guard   = new WikiSpaceGuard(new FixedSpaceLocator($space), new CallbackSpaceAccessChecker(fn (WikiAction $action): bool => in_array($action, $this->granted, true)));
        $service = new WikiCommentService(new DoctrineOrmWikiCommentRepository($em), $guard, new WikiAuthorLabel('secret'), new WikiMarkdownRenderer(), new WikiHtmlSanitizer(), limits: ['user_threads_per_page' => 1000] + WikiCommentService::DEFAULT_LIMITS);
        $state   = new class($this->remembered) implements WikiPanelStateStoreInterface {
            public function __construct(private bool &$open)
            {
            }

            public function isOpen(UserInterface $user, string $panel): bool
            {
                return $this->open;
            }

            public function setOpen(UserInterface $user, string $panel, bool $open): void
            {
                $this->open = $open;
            }
        };
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $name): string => '/r/' . $name);
        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('getToken')->willReturnCallback(static fn (string $id): CsrfToken => new CsrfToken($id, 'csrf:' . $id));
        $container = WikiControllerContainerBuilder::bind(new WikiCommentController($guard, new DoctrineOrmWikiPageRepository($em)));
        /** @var FormFactoryInterface $forms */
        $forms = $container->get('form.factory');

        $panel = new WikiCommentsPanel(
            $service,
            $state,
            $router,
            $csrf,
            ['page_comments' => ['path' => '/c/{path}', 'name' => 'nowo_wiki_page_comments'], 'space_panel_state' => ['path' => '/s', 'name' => 'nowo_wiki_space_panel_state']],
            'Alt+Shift+C',
            $profiles ? new ProfileRegistry(['wiki' => ['features' => ['toolbar', 'table'], 'theme' => 'auto', 'min_height' => '1rem', 'icon_sprite_url' => null, 'icons' => [], 'upload_max_size' => 1, 'upload_accept' => []]], 'wiki') : null,
            $forms,
            $readViewSource,
        );

        return [$panel, $user, $space, $page, $service];
    }
}
