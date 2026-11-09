<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\Routing;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiQuotaCheckEvent;
use Nowo\WikiBundle\Link\WikiPageLinkService;
use Nowo\WikiBundle\Link\WikiPageLinkServiceInterface;
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
use ZipArchive;

use function array_keys;
use function array_search;
use function dirname;
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
        foreach (['-defaults', '-hooks', '-scoped'] as $variant) {
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
        $this->boot([], '-defaults');
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

    /** GV-I5: with search.scope: space the index search form has no "all spaces" choice, which would answer 404. */
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
