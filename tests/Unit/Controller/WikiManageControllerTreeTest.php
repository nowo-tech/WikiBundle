<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Controller;

use Closure;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\ShowPage;
use Nowo\WikiBundle\Ai\NullWikiAiAssistant;
use Nowo\WikiBundle\Controller\WikiManageController;
use Nowo\WikiBundle\Controller\WikiTreeController;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Form\WikiPageMoveType;
use Nowo\WikiBundle\Interchange\WikiArchiveHelper;
use Nowo\WikiBundle\Interchange\WikiDocumentExporter;
use Nowo\WikiBundle\Interchange\WikiDocumentImporter;
use Nowo\WikiBundle\Interchange\WikiDocumentTreeReader;
use Nowo\WikiBundle\Interchange\WikiFormatDetector;
use Nowo\WikiBundle\Interchange\WikiFrontMatterParser;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Service\WikiPageMover;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiPageTreeBuilder;
use Nowo\WikiBundle\Service\WikiRevisionDiffService;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use Nowo\WikiBundle\Tests\Support\WikiControllerContainerBuilder;
use Nowo\WikiBundle\Tree\WikiTreeStateStoreInterface;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_column;
use function array_diff_key;
use function array_map;
use function array_values;

/**
 * 1.14: the sidebar renders the UiKit tree with the fold state, the page view offers Move, and the Move form moves.
 */
final class WikiManageControllerTreeTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    private WikiPage $ops;

    private WikiPage $run;

    private WikiPage $deep;

    private WikiPage $guide;

    private WikiPage $locked;

    private object $twig;

    private Container $container;

    /** @var list<string> */
    private array $expanded = [];

    protected function setUp(): void
    {
        $this->em    = SqliteWikiEntityManager::create();
        $this->space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $this->em->persist($this->space);
        $this->ops    = $this->page('ops', 'Operations');
        $this->run    = $this->page('run', 'Runbooks', $this->ops);
        $this->deep   = $this->page('deep', 'Deep', $this->run);
        $this->guide  = $this->page('guide', 'Guide');
        $this->locked = $this->page('locked', 'Locked');
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

    public function testWithoutTheTreeTheSidebarAndThePageViewStayAsIn113(): void
    {
        $controller = $this->controller(tree: false);

        $controller->space('eng');
        self::assertArrayNotHasKey('tree_ui', $this->twig->context);
        self::assertContains($this->ops->getId(), array_column($this->twig->context['tree'], 'id'));

        $controller->viewPage('eng', 'run');
        self::assertArrayNotHasKey('tree_ui', $this->twig->context);
        self::assertNotContains('wiki-move', $this->actionIds());

        $this->expectException(NotFoundHttpException::class);
        $controller->movePage(Request::create('/'), 'eng', 'run');
    }

    public function testTheSidebarGetsTheUiKitTreeWithTheFoldStateAndTheEndpoints(): void
    {
        $this->expanded = [$this->ops->getId()];
        $controller     = $this->controller();

        $controller->space('eng');
        $tree = $this->twig->context['tree_ui'];
        self::assertTrue($tree['movable']);
        self::assertSame(['Guide', 'Locked', 'Operations'], array_column($tree['nodes'], 'label'));
        self::assertSame(['id' => $this->ops->getId(), 'label' => 'Operations', 'href' => '/generated/nowo_wiki_page_view', 'current' => false, 'expanded' => true], array_diff_key($tree['nodes'][2], ['children' => true]));
        self::assertFalse($tree['nodes'][0]['expanded']);
        self::assertFalse($tree['nodes'][2]['children'][0]['expanded']);
        self::assertSame('nowo-wiki-tree', $tree['attr']['id']);
        self::assertTrue($tree['attr']['data-wiki-tree']);
        self::assertSame('/generated/nowo_wiki_space_tree', $tree['attr']['data-wiki-tree-state']);
        self::assertSame('/generated/nowo_wiki_space_tree', $tree['attr']['data-wiki-tree-move']);
        /** @var CsrfTokenManagerInterface $csrf */
        $csrf = $this->container->get('security.csrf.token_manager');
        self::assertTrue($csrf->isTokenValid(new CsrfToken(WikiTreeController::csrfTokenId($this->space), $tree['attr']['data-wiki-tree-token'])));

        $controller->viewPage('eng', 'run');
        self::assertTrue($this->twig->context['tree_ui']['nodes'][2]['children'][0]['current']);
        self::assertContains('wiki-move', $this->actionIds());
    }

    public function testReadersGetAFixedTreeAndNoMoveAction(): void
    {
        $controller = $this->controller(decide: static fn (WikiAction $action): bool => $action === WikiAction::View);

        $controller->viewPage('eng', 'run');

        self::assertFalse($this->twig->context['tree_ui']['movable']);
        self::assertArrayNotHasKey('data-wiki-tree-move', $this->twig->context['tree_ui']['attr']);
        self::assertNotContains('wiki-move', $this->actionIds());
    }

    public function testTheMoveFormOffersTheEditableParentsOutsideTheSubTree(): void
    {
        $controller = $this->controller(decide: fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => $action === WikiAction::View || $page !== $this->locked);

        $response = $controller->movePage(Request::create('/'), 'eng', 'run');

        self::assertSame(200, $response->getStatusCode());
        self::assertInstanceOf(FormPage::class, $this->twig->context['page']);
        self::assertNull($this->twig->context['error']);
        /** @var FormView $form */
        $form   = $this->twig->context['page']->form;
        $labels = array_map(static fn (object $choice): string => $choice->label, $form['parent']->vars['choices']);
        self::assertSame(['Guide', 'Operations'], array_values($labels));
        self::assertSame($this->ops->getId(), $form['parent']->vars['value']);
    }

    public function testAValidMoveRedirectsWithAFlash(): void
    {
        $controller = $this->controller();

        $response = $controller->movePage($this->submit('run', ['parent' => $this->guide->getId(), 'position' => '1']), 'eng', 'run');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame($this->guide, $this->run->getParent());
        $flash = $this->flashes('success')[0];
        self::assertInstanceOf(TranslatableMessage::class, $flash);
        self::assertSame(['wiki.tree.moved_under', ['%title%' => 'Runbooks', '%position%' => 1, '%count%' => 1, '%parent%' => 'Guide']], [$flash->getMessage(), $flash->getParameters()]);

        $controller->movePage($this->submit('run', ['parent' => '', 'position' => '']), 'eng', 'run');
        self::assertNull($this->run->getParent());
        self::assertSame('wiki.tree.moved_root', $this->flashes('success')[0]->getMessage());
    }

    public function testARefusedMoveRendersTheReason(): void
    {
        $controller = $this->controller(maxDepth: 2, translator: true);

        $response = $controller->movePage($this->submit('run', ['parent' => $this->guide->getId()]), 'eng', 'run');

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('translated wiki.tree.error.too_deep', $this->twig->context['error']);
        self::assertSame($this->ops, $this->run->getParent());

        $controller = $this->controller(maxDepth: 2);
        $controller->movePage($this->submit('run', ['parent' => $this->guide->getId()]), 'eng', 'run');
        self::assertSame('wiki.tree.error.too_deep', $this->twig->context['error']);
    }

    public function testAnInvalidFormIsRenderedAgain(): void
    {
        $controller = $this->controller();

        $response = $controller->movePage($this->submit('run', ['parent' => $this->deep->getId()]), 'eng', 'run');

        self::assertSame(422, $response->getStatusCode());
        self::assertNull($this->twig->context['error']);
        self::assertSame($this->ops, $this->run->getParent());
    }

    public function testMovingNeedsTheEditRights(): void
    {
        $controller = $this->controller(decide: fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => $action === WikiAction::View || $page !== $this->ops);

        $this->expectException(AccessDeniedHttpException::class);
        $controller->movePage(Request::create('/'), 'eng', 'run');
    }

    /**
     * @return list<string>
     */
    private function actionIds(): array
    {
        /** @var ShowPage $page */
        $page = $this->twig->context['page'];

        return array_map(static fn (Action $action): string => (string) ($action->attributes['data-testid'] ?? ''), $page->header->actions);
    }

    /**
     * @param array<string, string> $fields
     */
    private function submit(string $slug, array $fields): Request
    {
        $page = $this->em->getRepository(WikiPage::class)->findOneBy(['slug' => $slug]);
        self::assertInstanceOf(WikiPage::class, $page);
        $fields['_token'] = WikiControllerContainerBuilder::csrfToken($this->container, WikiPageMoveType::csrfTokenId($page->getId()));

        return Request::create('/', 'POST', $fields);
    }

    /**
     * @return list<mixed>
     */
    private function flashes(string $type): array
    {
        /** @var RequestStack $stack */
        $stack = $this->container->get('request_stack');
        /** @var FlashBagAwareSessionInterface $session */
        $session = $stack->getSession();

        return array_values($session->getFlashBag()->get($type));
    }

    private function page(string $slug, string $title, ?WikiPage $parent = null): WikiPage
    {
        $page = new WikiPage($this->space, $slug, $title, $parent);
        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }

    /**
     * @param (Closure(WikiAction, WikiSpace, ?WikiPage): bool)|null $decide
     */
    private function controller(bool $tree = true, ?Closure $decide = null, int $maxDepth = 10, bool $translator = false): WikiManageController
    {
        $repository = new DoctrineOrmWikiPageRepository($this->em);
        $revisions  = $this->createStub(WikiPageRevisionRepositoryInterface::class);
        $roles      = $this->createStub(WikiAccessCheckerInterface::class);
        foreach (['canAccess', 'canList', 'canCreate', 'canEdit', 'canViewHistory', 'canArchive', 'canAskAi', 'canImport', 'canExport'] as $method) {
            $roles->method($method)->willReturn(true);
        }
        $resolver = $this->createStub(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$this->space]);
        $decide ??= static fn (): bool => true;
        $service  = new WikiPageService($repository, $revisions, new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher());
        $expanded = $this->expanded;
        $state    = new class($expanded) implements WikiTreeStateStoreInterface {
            /** @param list<string> $ids */
            public function __construct(private readonly array $ids)
            {
            }

            public function expanded(UserInterface $user, WikiSpace $space): array
            {
                return $this->ids;
            }

            public function setExpanded(UserInterface $user, WikiSpace $space, string $pageId, bool $expanded): void
            {
            }
        };
        $routes = [];
        foreach (['space', 'page_view', 'page_edit', 'page_history', 'search', 'page_move', 'space_tree'] as $key) {
            $routes[$key] = ['path' => '/' . $key, 'name' => 'nowo_wiki_' . $key];
        }
        if (!$tree) {
            unset($routes['page_move'], $routes['space_tree']);
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
            spaceGuard: new WikiSpaceGuard(new FixedSpaceLocator($this->space), new CallbackSpaceAccessChecker($decide)),
            translator: $translator ? new class implements TranslatorInterface {
                public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
                {
                    return 'translated ' . $id;
                }

                public function getLocale(): string
                {
                    return 'en';
                }
            } : null,
            mover: $tree ? new WikiPageMover($repository, $repository, null, $maxDepth) : null,
            treeState: $tree ? $state : null,
        );
        $this->container = WikiControllerContainerBuilder::bind($controller, new TestUser(), ['twig' => $this->twig]);

        return $controller;
    }
}
