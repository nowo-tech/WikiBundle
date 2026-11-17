<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\Routing;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\WikiBundle\DependencyInjection\WikiExtension;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiQuotaCheckEvent;
use Nowo\WikiBundle\Link\WikiPageLinkService;
use Nowo\WikiBundle\Link\WikiPageLinkServiceInterface;
use Nowo\WikiBundle\Service\WikiPageIconService;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiPageServiceInterface;
use Nowo\WikiBundle\Service\WikiSpaceService;
use Nowo\WikiBundle\Service\WikiSpaceServiceInterface;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Stub\WikiSecurityUserDto;
use Nowo\WikiBundle\Tests\Support\RecordingQuotaListener;
use Nowo\WikiBundle\User\DoctrineWikiUserReferenceResolver;
use Nowo\WikiBundle\User\WikiUserReferenceResolverInterface;
use Nowo\WikiBundle\WikiBundle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use ZipArchive;

use function array_keys;
use function array_search;
use function count;
use function dirname;
use function str_contains;
use function strpos;
use function substr_count;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * 1.21 (audit GV-L1, GV-M1, GV-L2): the hooks in a real kernel with TwigBundle registered BEFORE WikiBundle, the order of
 * a normal host, over HTTP. The contract tests give the Twig global as render context, so they cannot see the order.
 */
final class WebUiHooksKernelTest extends TestCase
{
    private const HOOKS = [
        'import_export' => ['enabled' => true],
        'web_ui'        => [
            'assets'        => false,
            'import_export' => 'dialog',
            'slots'         => ['sidebar' => 'slots/sidebar.html.twig', 'space_intro' => 'slots/space_intro.html.twig', 'page_header_extra' => 'slots/page_header_extra.html.twig'],
        ],
    ];

    private ?KernelInterface $kernel = null;

    private WikiSpace $space;

    private TestUserEntity $user;

    public static function setUpBeforeClass(): void
    {
        (new Filesystem())->remove(dirname((new RoutingTestKernel(true))->getCacheDir()));
        foreach (['-defaults', '-hooks', '-scoped', '-newpage', '-defaults-tree', '-treemenu', '-treemenu-dialog', '-treemenu-trash', '-nav-none', '-nav-top', '-nav-bottom', '-nav-both', '-icon-emoji'] as $variant) {
            (new Filesystem())->remove(dirname((new RoutingTestKernel(true, [], $variant))->getCacheDir()));
        }
    }

    protected function setUp(): void
    {
        RecordingQuotaListener::$seen     = [];
        RecordingQuotaListener::$denyWith = null;
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testTheDddPortsAreAliasedAndTakeANonEntityUser(): void
    {
        $this->boot(['accepted_user_classes' => [WikiSecurityUserDto::class]], '-ddd');
        $container = $this->services();
        self::assertSame($container->get(WikiPageService::class), $container->get(WikiPageServiceInterface::class));
        self::assertSame($container->get(WikiSpaceService::class), $container->get(WikiSpaceServiceInterface::class));
        self::assertSame($container->get(WikiPageLinkService::class), $container->get(WikiPageLinkServiceInterface::class));
        self::assertInstanceOf(DoctrineWikiUserReferenceResolver::class, $container->get(WikiUserReferenceResolverInterface::class));

        $pages = $container->get(WikiPageServiceInterface::class);
        self::assertInstanceOf(WikiPageServiceInterface::class, $pages);
        $page   = $pages->create($this->space, 'Ports', '<p>x</p>', new WikiSecurityUserDto($this->user->getId()));
        $author = $page->getCurrentRevision()?->getAuthor();
        self::assertInstanceOf(TestUserEntity::class, $author);
        self::assertSame($this->user->getId(), $author->getId());
    }

    public function testAnUnlistedSecurityUserClassIsRefusedByDefault(): void
    {
        $this->boot([], '-defaults');
        $pages = $this->services()->get(WikiPageServiceInterface::class);
        self::assertInstanceOf(WikiPageServiceInterface::class, $pages);

        $this->expectException(AccessDeniedException::class);
        $pages->create($this->space, 'Ports', '<p>x</p>', new WikiSecurityUserDto($this->user->getId()));
    }

    public function testTwigIsRegisteredBeforeTheWikiInThisKernel(): void
    {
        $this->boot([], '-defaults');
        self::assertNotNull($this->kernel);
        $names = array_keys($this->kernel->getBundles());
        self::assertLessThan(array_search((new WikiBundle())->getName(), $names, true), array_search((new TwigBundle())->getName(), $names, true));
    }

    public function testSlotsAssetsAndDialogReachTheSpaceHome(): void
    {
        $this->boot(self::HOOKS, '-hooks');
        $html = $this->html('/tools/wiki/spaces/eng');

        // Slots: the host templates instead of the tree and search cards.
        self::assertStringContainsString('<aside data-testid="host-sidebar">NowoWikiBundle</aside>', $html);
        self::assertStringContainsString('<p data-testid="host-intro">NowoWikiBundle</p>', $html);
        self::assertStringNotContainsString('data-wiki-sidebar', $html);
        self::assertStringNotContainsString('data-testid="wiki-space-search"', $html);
        // assets: false: no stylesheet or script of the bundle or the scaffold.
        self::assertStringNotContainsString('wiki.css', $html);
        self::assertStringNotContainsString('wiki.js', $html);
        self::assertStringNotContainsString('nowo-ui.css', $html);
        // Dialog: the opener with its dialog, no import card.
        self::assertStringContainsString('data-nowo-ui-dialog-open="nowo-wiki-import-export-dialog"', $html);
        self::assertStringContainsString('<dialog id="nowo-wiki-import-export-dialog"', $html);
        self::assertStringContainsString('data-testid="wiki-export-outline"', $html);
        self::assertStringContainsString('id="nowo-wiki-import-export-form"', $html);
        self::assertStringNotContainsString('data-testid="wiki-import"', $html);
    }

    public function testTheHeaderSlotReachesThePageView(): void
    {
        $this->boot(self::HOOKS, '-hooks');
        $html = $this->html('/tools/wiki/spaces/eng/pages/home');

        self::assertStringContainsString('<p data-testid="host-header-extra">NowoWikiBundle</p>', $html);
        self::assertLessThan((int) strpos($html, 'data-testid="wiki-tabs"'), (int) strpos($html, 'data-testid="host-header-extra"'));
        self::assertStringNotContainsString('wiki.js', $html);
    }

    /** Without the options the same kernel prints the 1.20 page. */
    public function testTheDefaultsStayInTheSameBundleOrder(): void
    {
        $this->boot(['import_export' => ['enabled' => true]], '-defaults');
        $html = $this->html('/tools/wiki/spaces/eng');

        self::assertStringContainsString('wiki.css', $html);
        self::assertStringContainsString('wiki.js', $html);
        self::assertStringContainsString('data-wiki-sidebar', $html);
        self::assertStringContainsString('data-testid="wiki-space-search"', $html);
        self::assertStringContainsString('data-testid="wiki-import"', $html);
        self::assertStringNotContainsString('nowo-wiki-import-export-dialog', $html);
        self::assertStringNotContainsString('data-testid="host-', $html);
    }

    /** GV-L2: the reason is on the page the user sees, nothing is written. */
    public function testARefusedNewPageShowsTheReason(): void
    {
        $this->boot(['import_export' => ['enabled' => true]], '-defaults');
        RecordingQuotaListener::$denyWith = 'Your plan allows <b>10</b> pages.';
        $before                           = $this->pageCount();

        $response = $this->newPage('Over the limit');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame(1, substr_count((string) $response->getContent(), 'Your plan allows &lt;b&gt;10&lt;/b&gt; pages.'));
        self::assertSame($before, $this->pageCount());
        self::assertSame(WikiQuotaCheckEvent::PAGE_CREATE, RecordingQuotaListener::$seen[0]->action);
    }

    public function testAnAllowedNewPageIsWritten(): void
    {
        $this->boot(['import_export' => ['enabled' => true]], '-defaults');
        $before = $this->pageCount();

        self::assertSame(Response::HTTP_FOUND, $this->newPage('Within the limit')->getStatusCode());
        self::assertSame($before + 1, $this->pageCount());
    }

    /** GV-I7: an empty title is a message on the form, not a 500. */
    public function testAnEmptyTitleIsNotAServerError(): void
    {
        $this->boot(['import_export' => ['enabled' => true]], '-defaults');
        $before = $this->pageCount();

        $response = $this->newPage(null);

        self::assertLessThan(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertSame($before, $this->pageCount());
        self::assertStringContainsString('name="wiki_page_form[title]"', (string) $response->getContent());
    }

    /** GV-I7: on the edit form too, and the page keeps its title. */
    public function testAnEmptyTitleIsNeverSavedOnEdit(): void
    {
        $this->boot(['import_export' => ['enabled' => true]], '-defaults');
        $entityManager = $this->services()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $page = $entityManager->getRepository(WikiPage::class)->findOneBy(['slug' => 'home']);
        self::assertInstanceOf(WikiPage::class, $page);

        $response = $this->request(Request::create('/tools/wiki/spaces/eng/pages/home/edit', 'POST', ['wiki_page_form' => ['_token' => 'ok-wiki_edit_' . $page->getId(), 'title' => '   ', 'content' => 'Body']]));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $entityManager->refresh($page);
        self::assertSame('Home', $page->getTitle());
    }

    /** GV-M1: `count` is every Markdown document of the archive; a refused import writes nothing. */
    public function testAnImportReportsItsDocumentsAndARefusalWritesNothing(): void
    {
        $this->boot(['import_export' => ['enabled' => true]], '-defaults');
        RecordingQuotaListener::$denyWith = 'No more pages on this plan.';
        $before                           = $this->pageCount();

        $response = $this->import(['one', 'two', 'three', 'four', 'five', 'six', 'seven']);

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertCount(1, RecordingQuotaListener::$seen);
        self::assertSame(WikiQuotaCheckEvent::IMPORT, RecordingQuotaListener::$seen[0]->action);
        self::assertSame(7, RecordingQuotaListener::$seen[0]->count);
        self::assertGreaterThan(0, RecordingQuotaListener::$seen[0]->bytes);
        self::assertSame($before, $this->pageCount());
    }

    public function testAnAllowedImportReportsItsDocumentsAndWrites(): void
    {
        $this->boot(['import_export' => ['enabled' => true]], '-defaults');
        $before = $this->pageCount();

        self::assertSame(Response::HTTP_FOUND, $this->import(['eight', 'nine'])->getStatusCode());
        self::assertSame(2, RecordingQuotaListener::$seen[0]->count);
        self::assertSame($before + 2, $this->pageCount());
    }

    /** 1.39 (tree.context_menu, default on): the tree menu replaces the 1.35 menu; entries follow the configuration. */
    public function testTheTreeContextMenu(): void
    {
        $this->boot(['tree' => ['enabled' => true, 'context_menu' => true], 'page_icon' => ['form_field' => true]], '-treemenu');
        $html = $this->html('/tools/wiki/spaces/eng/pages/home');

        self::assertStringContainsString('data-wiki-tree-menu ', $html);
        self::assertStringNotContainsString('data-wiki-new-page-menu', $html);
        // 1.41: New page above / below are two entries with or without the new-page dialog; Delete needs the trash.
        $items = ['open', 'edit', 'rename', 'icon', 'child', 'above', 'below', 'duplicate', 'up', 'down', 'moveto', 'copy', 'archive'];
        foreach ($items as $item) {
            self::assertStringContainsString('data-wiki-tree-menu-item="' . $item . '"', $html);
        }
        self::assertStringNotContainsString('data-wiki-tree-menu-item="delete"', $html);
        self::assertSame(count($items), substr_count($html, 'role="menuitem"'));
        self::assertSame(4, substr_count($html, 'role="separator"'));
        foreach (['archive', 'unarchive', 'duplicate', 'parents'] as $action) {
            self::assertStringContainsString('data-wiki-tree-' . $action . '="/tools/wiki/spaces/eng/tree/' . $action . '"', $html);
        }
        self::assertStringNotContainsString('data-wiki-tree-delete=', $html);
        self::assertStringContainsString('data-wiki-tree-space="/tools/wiki/spaces/eng"', $html);
        self::assertStringContainsString('data-wiki-edit-href="/tools/wiki/spaces/eng/pages/home/edit"', $html);
        self::assertStringContainsString('data-wiki-menu-move="1"', $html);
        self::assertStringContainsString('data-wiki-menu-archive="1"', $html);
        self::assertStringContainsString('data-wiki-tree-confirm-dialog', $html);
        self::assertStringContainsString('data-wiki-tree-undo', $html);
        self::assertStringContainsString('data-wiki-tree-move-dialog', $html);
        self::assertStringContainsString('data-wiki-tree-rename="/tools/wiki/spaces/eng/tree/rename"', $html);
        self::assertStringContainsString('data-wiki-tree-icon="/tools/wiki/spaces/eng/tree/icon"', $html);
        self::assertStringContainsString('data-wiki-tree-new="/tools/wiki/spaces/eng/pages/new"', $html);
        self::assertStringContainsString('data-wiki-menu-edit="1"', $html);
        self::assertStringContainsString('data-wiki-menu-icon="1"', $html);
        self::assertStringContainsString('data-wiki-tree-menu-more', $html);
        self::assertStringContainsString('<template data-wiki-tree-icon-template>', $html);
        self::assertStringContainsString('name="form[icon]"', $html);

        $this->kernel?->shutdown();
        $this->boot(['tree' => ['enabled' => true, 'context_menu' => true], 'web_ui' => ['new_page_dialog' => true]], '-treemenu-dialog');
        $html = $this->html('/tools/wiki/spaces/eng/pages/home');
        self::assertStringContainsString('data-wiki-tree-menu-item="above"', $html);
        self::assertStringNotContainsString('data-wiki-tree-menu-item="icon"', $html);
        self::assertStringNotContainsString('data-wiki-tree-new=', $html);
        self::assertStringNotContainsString('data-wiki-new-page-menu', $html);        // Trash on: Delete, behind the confirm dialog.
        $this->kernel?->shutdown();
        $this->boot(['tree' => ['enabled' => true, 'context_menu' => true], 'trash' => ['enabled' => true]], '-treemenu-trash');
        $html = $this->html('/tools/wiki/spaces/eng/pages/home');
        self::assertStringContainsString('data-wiki-tree-menu-item="delete"', $html);
        self::assertStringContainsString('data-wiki-menu-delete="1"', $html);
        self::assertStringContainsString('data-wiki-tree-delete="/tools/wiki/spaces/eng/tree/delete"', $html);
    }

    /** 1.39: the new-page form takes ?place= with the tree menu but without the dialog. */
    public function testTheNewPageFormTakesThePlaceWithTheTreeMenu(): void
    {
        $this->boot(['tree' => ['enabled' => true, 'context_menu' => true]], '-treemenu');
        $html = $this->html('/tools/wiki/spaces/eng/pages/new?place=child%3Aabc');

        self::assertStringContainsString('name="wiki_page_form[place]"', $html);
        self::assertStringContainsString('value="child:abc"', $html);
        self::assertStringContainsString('nowo-wiki-title-input', $html);
    }

    /** Audit RS-L2: by default (tree.context_menu off) the new-page form takes no place without the dialog, as in 1.38. */
    public function testByDefaultTheNewPageFormHasNoPlaceWithoutTheDialog(): void
    {
        $this->boot(['tree' => ['enabled' => true]], '-defaults-tree');
        $html = $this->html('/tools/wiki/spaces/eng/pages/new?place=child%3Aabc');

        self::assertStringNotContainsString('wiki_page_form[place]', $html);
        self::assertStringNotContainsString('data-wiki-tree-menu', $html);
    }

    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function pageNavPositions(): iterable
    {
        yield 'none' => ['none', false, false];
        yield 'top' => ['top', true, false];
        yield 'bottom' => ['bottom', false, true];
        yield 'both' => ['both', true, true];
    }

    /** 1.39 (web_ui.page_nav): previous / next in reading order, rel and accessible names, per position. */
    #[DataProvider('pageNavPositions')]
    public function testThePageNavigation(string $position, bool $top, bool $bottom): void
    {
        $this->boot(['tree' => ['enabled' => true], 'web_ui' => ['page_nav' => $position]], '-nav-' . $position);
        $container = $this->services();
        $pages     = $container->get(WikiPageService::class);
        self::assertInstanceOf(WikiPageService::class, $pages);
        $home  = $this->pageBySlug('home');
        $child = $pages->create($this->space, 'Variables <por> tipo', '<p>x</p>', $this->user, $home);
        $pages->create($this->space, 'Listas', '<p>y</p>', $this->user);

        $html = $this->html('/tools/wiki/spaces/eng/pages/' . $child->getSlug());
        self::assertSame($top, str_contains($html, 'data-testid="wiki-page-nav-top"'));
        self::assertSame($bottom, str_contains($html, 'data-testid="wiki-page-nav-bottom"'));
        if ($position === 'none') {
            self::assertStringNotContainsString('rel="prev"', $html);

            return;
        }
        self::assertStringContainsString('rel="prev"', $html);
        self::assertStringContainsString('aria-label="Previous page: Home"', $html);
        self::assertStringContainsString('aria-label="Next page: Listas"', $html);
        self::assertStringContainsString('href="/tools/wiki/spaces/eng/pages/home"', $html);

        // The first page has no "previous" link, only its empty cell (one request per kernel).
        $this->kernel?->shutdown();
        $this->boot(['tree' => ['enabled' => true], 'web_ui' => ['page_nav' => $position]], '-nav-' . $position);
        $pages = $this->services()->get(WikiPageService::class);
        self::assertInstanceOf(WikiPageService::class, $pages);
        $pages->create($this->space, 'Variables <por> tipo', '<p>x</p>', $this->user, $this->pageBySlug('home'));
        $html = $this->html('/tools/wiki/spaces/eng/pages/home');
        self::assertStringNotContainsString('rel="prev"', $html);
        self::assertStringContainsString('aria-label="Next page: Variables &lt;por&gt; tipo"', $html);
        self::assertStringContainsString('nowo-wiki-page-nav__link--empty', $html);
    }

    /** 1.39: an emoji icon is escaped text in the tree and the title line. */
    public function testEmojiIconsRenderAsText(): void
    {
        if (!interface_exists(WikiExtension::ICON_SELECTOR_EMOJI_CATALOG)) {
            self::markTestSkipped('nowo-tech/icon-selector-bundle >= 1.6 (dev dependency) is not installed.');
        }
        $this->boot(['tree' => ['enabled' => true, 'context_menu' => true], 'page_icon' => ['form_field' => true, 'emoji' => true]], '-icon-emoji');
        $container = $this->services();
        $icons     = $container->get(WikiPageIconService::class);
        self::assertInstanceOf(WikiPageIconService::class, $icons);
        $pages = $container->get(WikiPageService::class);
        self::assertInstanceOf(WikiPageService::class, $pages);
        $home  = $this->pageBySlug('home');
        $child = $pages->create($this->space, 'Child', '<p>x</p>', $this->user, $home);
        $icons->setIcon($home, 'emoji:🚀');
        $icons->setIcon($child, 'emoji:👩🏽‍💻');

        $html = $this->html('/tools/wiki/spaces/eng/pages/home');
        self::assertGreaterThanOrEqual(2, substr_count($html, 'nowo-wiki-page-icon--emoji" aria-hidden="true">🚀</span>'));
        self::assertStringContainsString('aria-hidden="true">👩🏽‍💻</span>', $html);
        self::assertStringContainsString('name="form[icon]"', $html);
    }

    /** GV-I5: with search.scope: space the index search form has no "all spaces" choice, which would answer 404. */
    /** 1.35 (web_ui.new_page_dialog): the dialog, the page action, the tree menu and its «…» buttons; a placed create. */
    public function testTheNewPageDialogAndTheTreeMenu(): void
    {
        $this->boot(['tree' => ['enabled' => true], 'web_ui' => ['new_page_dialog' => true]], '-newpage');
        $html = $this->html('/tools/wiki/spaces/eng/pages/home');

        self::assertStringContainsString('<dialog id="nowo-wiki-new-page-dialog"', $html);
        self::assertStringContainsString('data-nowo-ui-dialog-open="nowo-wiki-new-page-dialog"', $html);
        self::assertStringContainsString('action="/tools/wiki/spaces/eng/pages/new"', $html);
        self::assertStringContainsString('name="wiki_page_form[place]"', $html);
        self::assertStringNotContainsString('wiki_page_form[position]', $html);
        self::assertStringContainsString('name="wiki_page_form[_token]"', $html);
        self::assertStringContainsString('role="menu"', $html);
        self::assertSame(3, substr_count($html, 'role="menuitem"'));
        self::assertStringContainsString('New page above', $html);
        self::assertStringContainsString('New page as child', $html);
        self::assertStringContainsString('data-wiki-new-page-more', $html);
        self::assertStringContainsString('aria-label="Add a page next to “Home”"', $html);
        self::assertStringContainsString('data-wiki-new-child="1"', $html);
        self::assertStringContainsString('data-wiki-new-sibling="1"', $html);
        self::assertStringContainsString('data-above="Above “%title%”"', $html);

        // One request per kernel, as the other tests (the token does not survive a request here).
        $this->kernel?->shutdown();
        $this->boot(['tree' => ['enabled' => true], 'web_ui' => ['new_page_dialog' => true]], '-newpage');
        $space = $this->html('/tools/wiki/spaces/eng');
        self::assertStringContainsString('<dialog id="nowo-wiki-new-page-dialog"', $space);
        self::assertStringContainsString('data-wiki-new-page-menu', $space);

        $this->kernel?->shutdown();
        $this->boot(['tree' => ['enabled' => true], 'web_ui' => ['new_page_dialog' => true]], '-newpage');
        $entityManager = $this->services()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $home = $entityManager->getRepository(WikiPage::class)->findOneBy(['slug' => 'home']);
        self::assertInstanceOf(WikiPage::class, $home);
        $response = $this->request(Request::create('/tools/wiki/spaces/eng/pages/new', 'POST', ['wiki_page_form' => [
            '_token'  => 'ok-wiki_new_' . $this->space->getId(),
            'title'   => 'Before home',
            'content' => 'Body',
            'place'   => 'above:' . $home->getId(),
        ]]));
        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode(), (string) $response->getContent());
        $entityManager->clear();
        $placed = $entityManager->getRepository(WikiPage::class)->findOneBy(['slug' => 'before-home']);
        $home   = $entityManager->getRepository(WikiPage::class)->findOneBy(['slug' => 'home']);
        self::assertInstanceOf(WikiPage::class, $placed);
        self::assertInstanceOf(WikiPage::class, $home);
        self::assertLessThan($home->getPosition(), $placed->getPosition());

        // Without the option the same pages print none of it.
        $this->kernel?->shutdown();
        $this->boot(['tree' => ['enabled' => true]], '-defaults-tree');
        $html = $this->html('/tools/wiki/spaces/eng/pages/home');
        self::assertStringNotContainsString('nowo-wiki-new-page', $html);
        self::assertStringNotContainsString('data-wiki-new-page-more', $html);
        self::assertStringNotContainsString('wiki_page_form[position]', $html);
    }

    public function testTheIndexSearchFormHasNoDeadChoiceWithTheSpaceScope(): void
    {
        $this->boot(['import_export' => ['enabled' => true]], '-defaults');
        self::assertStringContainsString('All spaces', $this->html('/tools/wiki'));
        $this->kernel?->shutdown();

        $this->boot(['search' => ['scope' => 'space']], '-scoped');
        $html = $this->html('/tools/wiki');
        self::assertStringNotContainsString('All spaces', $html);
        self::assertStringContainsString('wiki-index-search-space', $html);
        self::assertSame(Response::HTTP_NOT_FOUND, $this->request(Request::create('/tools/wiki/search?q=x'))->getStatusCode());
    }

    /**
     * @param array<string, mixed> $wiki
     */
    private function boot(array $wiki, string $variant): void
    {
        $this->kernel = new RoutingTestKernel(true, ['space_scope' => 'user', 'editor' => ['type' => 'markdown']] + $wiki, $variant);
        $this->kernel->boot();
        $container = $this->services();

        $entityManager = $container->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $tool = new SchemaTool($entityManager);
        $tool->dropDatabase();
        $tool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $this->user = new TestUserEntity();
        $entityManager->persist($this->user);
        $this->space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, $this->user->getId());
        $entityManager->persist($this->space);
        $entityManager->flush();

        $pages = $container->get(WikiPageService::class);
        self::assertInstanceOf(WikiPageService::class, $pages);
        $pages->create($this->space, 'Home', '<p>Home</p>', $this->user);

        $tokens = $container->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $tokens);
        $tokens->setToken(new UsernamePasswordToken($this->user, 'main', ['ROLE_USER']));
    }

    private function pageBySlug(string $slug): WikiPage
    {
        $em = $this->services()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $page = $em->getRepository(WikiPage::class)->findOneBy(['space' => $this->space, 'slug' => $slug]);
        self::assertInstanceOf(WikiPage::class, $page);

        return $page;
    }

    private function services(): ContainerInterface
    {
        $container = $this->kernel?->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    private function request(Request $request): Response
    {
        self::assertNotNull($this->kernel);

        return $this->kernel->handle($request);
    }

    private function html(string $path): string
    {
        $response = $this->request(Request::create($path));
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        return (string) $response->getContent();
    }

    private function newPage(?string $title): Response
    {
        $fields = ['_token' => 'ok-wiki_new_' . $this->space->getId(), 'content' => '<p>Body</p>'];
        if ($title !== null) {
            $fields['title'] = $title;
        }

        return $this->request(Request::create('/tools/wiki/spaces/eng/pages/new', 'POST', ['wiki_page_form' => $fields]));
    }

    /**
     * @param list<string> $documents
     */
    private function import(array $documents): Response
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'wiki-import-');
        $zip  = new ZipArchive();
        self::assertTrue($zip->open($path, ZipArchive::OVERWRITE));
        foreach ($documents as $document) {
            $zip->addFromString($document . '.md', "# Page {$document}\n\nBody.\n");
        }
        $zip->close();

        try {
            $request = Request::create(
                '/tools/wiki/spaces/eng/import',
                'POST',
                ['_token' => 'ok-wiki_import_' . $this->space->getId(), 'format' => 'auto'],
                [],
                ['archive' => new UploadedFile($path, 'pages.zip', 'application/zip', null, true)],
            );

            return $this->request($request);
        } finally {
            @unlink($path);
        }
    }

    private function pageCount(): int
    {
        $entityManager = $this->services()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager->getRepository(WikiPage::class)->count([]);
    }
}
