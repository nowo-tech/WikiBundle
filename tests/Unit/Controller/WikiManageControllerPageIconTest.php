<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Controller;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\Ai\NullWikiAiAssistant;
use Nowo\WikiBundle\Controller\WikiManageController;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Interchange\WikiArchiveHelper;
use Nowo\WikiBundle\Interchange\WikiDocumentExporter;
use Nowo\WikiBundle\Interchange\WikiDocumentImporter;
use Nowo\WikiBundle\Interchange\WikiDocumentTreeReader;
use Nowo\WikiBundle\Interchange\WikiFormatDetector;
use Nowo\WikiBundle\Interchange\WikiFrontMatterParser;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Service\WikiPageIconService;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiPageTreeBuilder;
use Nowo\WikiBundle\Service\WikiRevisionDiffService;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use Nowo\WikiBundle\Tests\Support\WikiControllerContainerBuilder;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * 1.38 (`page_icon.form_field`): the page form's icon field on the new and edit forms, checked against the allowed
 * prefixes (a refused icon writes nothing), kept when the field is off, and the icon handed to the read view.
 */
final class WikiManageControllerPageIconTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    private WikiPage $ops;

    private TestUserEntity $user;

    private object $twig;

    private Container $container;

    protected function setUp(): void
    {
        $this->em    = SqliteWikiEntityManager::create();
        $this->space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $this->user  = new TestUserEntity('u-1');
        $this->em->persist($this->space);
        $this->em->persist($this->user);
        $this->ops = new WikiPage($this->space, 'ops', 'Operations');
        $this->em->persist($this->ops);
        $this->em->flush();
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

    public function testTheNewPageFormStoresTheIcon(): void
    {
        $controller = $this->controller();
        $controller->newPage(Request::create('/new'), 'eng');
        self::assertArrayHasKey('icon', $this->form()->children);

        $response = $controller->newPage($this->submit(['title' => 'Launch', 'icon' => 'tabler:rocket'], 'wiki_new_' . $this->space->getId()), 'eng');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('tabler:rocket', $this->stored('launch'));
    }

    public function testANotAllowedIconIsRefusedAndNothingIsWritten(): void
    {
        $controller = $this->controller();
        foreach (['bi:rocket', '<img src=x onerror=alert(1)>', "tabler:rocket\n"] as $i => $bad) {
            $response = $controller->newPage($this->submit(['title' => 'Bad ' . $i, 'icon' => $bad], 'wiki_new_' . $this->space->getId()), 'eng');
            self::assertNotInstanceOf(RedirectResponse::class, $response);
            self::assertFalse($this->stored('bad-' . $i), 'No page is created with a refused icon.');
            self::assertNotEmpty($this->form()->children['icon']->vars['errors']);
        }

        $response = $controller->editPage($this->submit(['title' => 'Operations', 'icon' => 'evil:x'], 'wiki_edit_' . $this->ops->getId()), 'eng', 'ops');
        self::assertNotInstanceOf(RedirectResponse::class, $response);
        self::assertNull($this->stored('ops'));
    }

    public function testTheEditFormChangesAndClearsTheIcon(): void
    {
        $controller = $this->controller();
        $controller->editPage(Request::create('/edit'), 'eng', 'ops');
        self::assertNull($this->form()->children['icon']->vars['value'] ?: null);

        self::assertInstanceOf(RedirectResponse::class, $controller->editPage($this->submit(['title' => 'Operations', 'icon' => 'tabler:tool'], 'wiki_edit_' . $this->ops->getId()), 'eng', 'ops'));
        self::assertSame('tabler:tool', $this->stored('ops'));

        $controller->editPage(Request::create('/edit'), 'eng', 'ops');
        self::assertSame('tabler:tool', $this->form()->children['icon']->vars['value']);

        self::assertInstanceOf(RedirectResponse::class, $controller->editPage($this->submit(['title' => 'Operations', 'icon' => ''], 'wiki_edit_' . $this->ops->getId()), 'eng', 'ops'));
        self::assertNull($this->stored('ops'));
    }

    public function testWithoutTheFieldTheIconIsKeptAndTheReadViewShowsIt(): void
    {
        $this->ops->setIcon('tabler:tool');
        $this->em->flush();
        $controller = $this->controller(false);

        $controller->editPage(Request::create('/edit'), 'eng', 'ops');
        self::assertArrayNotHasKey('icon', $this->form()->children);
        self::assertInstanceOf(RedirectResponse::class, $controller->editPage($this->submit(['title' => 'Operations 2'], 'wiki_edit_' . $this->ops->getId()), 'eng', 'ops'));
        self::assertSame('tabler:tool', $this->stored('ops'));

        $controller->viewPage('eng', 'ops');
        self::assertSame('tabler:tool', $this->twig->context['page_icon']);
        self::assertSame('tabler:tool', $this->twig->context['tree'][0]['icon']);
    }

    private function form(): FormView
    {
        $page = $this->twig->context['page'] ?? null;
        self::assertIsObject($page);
        self::assertTrue(isset($page->form) && $page->form instanceof FormView);

        return $page->form;
    }

    private function stored(string $slug): mixed
    {
        return $this->em->getConnection()->fetchOne('SELECT icon FROM wiki_pages WHERE slug = ?', [$slug]);
    }

    /**
     * @param array<string, string> $fields
     */
    private function submit(array $fields, string $tokenId): Request
    {
        $fields += ['content' => '<p>Body</p>'];
        $fields['_token'] = WikiControllerContainerBuilder::csrfToken($this->container, $tokenId);

        return Request::create('/form', 'POST', ['wiki_page_form' => $fields]);
    }

    private function controller(bool $field = true): WikiManageController
    {
        $repository = new DoctrineOrmWikiPageRepository($this->em);
        $revisions  = new DoctrineOrmWikiPageRevisionRepository($this->em);
        $roles      = $this->createStub(WikiAccessCheckerInterface::class);
        foreach (['canAccess', 'canList', 'canViewHistory', 'canArchive', 'canAskAi', 'canImport', 'canExport', 'canCreate', 'canEdit'] as $method) {
            $roles->method($method)->willReturn(true);
        }
        $resolver = $this->createStub(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$this->space]);
        $service = new WikiPageService($repository, $revisions, new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher());
        $routes  = [];
        foreach (['space', 'page_view', 'page_edit', 'page_new', 'page_history', 'search'] as $key) {
            $routes[$key] = ['path' => '/' . $key, 'name' => 'nowo_wiki_' . $key];
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
            new WikiDocumentImporter(new WikiArchiveHelper(), new WikiFormatDetector(new WikiFrontMatterParser()), new WikiDocumentTreeReader(new WikiFrontMatterParser()), new WikiMarkdownConverter(), $repository, $service, new WikiSlugger()),
            new WikiDocumentExporter($repository, new WikiPageTreeBuilder(), new WikiMarkdownConverter(), new WikiFrontMatterParser()),
            new WikiArchiveHelper(),
            $routes,
            [],
            null,
            ['tiptap_config' => 'notion'],
            ['enabled' => false, 'max_upload_bytes' => 1],
            spaceGuard: new WikiSpaceGuard(new FixedSpaceLocator($this->space), new CallbackSpaceAccessChecker(static fn (): bool => true)),
            pageIcons: new WikiPageIconService($repository),
            pageIconField: $field,
        );
        $this->container = WikiControllerContainerBuilder::bind($controller, $this->user, ['twig' => $this->twig]);

        return $controller;
    }
}
