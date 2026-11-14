<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Controller;

use Closure;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\ShowPage;
use Nowo\WikiBundle\Ai\NullWikiAiAssistant;
use Nowo\WikiBundle\Controller\WikiManageController;
use Nowo\WikiBundle\Entity\WikiPage;
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
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
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
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use Nowo\WikiBundle\Tests\Support\WikiControllerContainerBuilder;
use Nowo\WikiBundle\Tree\WikiTreeStateStoreInterface;
use Nowo\WikiBundle\Ui\WikiNewPageMenu;
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
use Symfony\Component\Translation\TranslatableMessage;

use function array_filter;
use function array_map;
use function array_values;

/**
 * 1.35 (`web_ui.new_page_dialog`): "New page" as a dialog from the page actions, the tree menu's rights, and the
 * new-page form's parent / position (above, below, child, first, last) with the create checks of the space and the
 * edit right on the parent; CSRF unchanged.
 */
final class WikiManageControllerNewPageTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    private WikiPage $ops;

    private WikiPage $run;

    private WikiPage $deep;

    private WikiPage $guide;

    private WikiPage $locked;

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
        $this->ops    = $this->page('ops', 'Operations', null, 1024);
        $this->run    = $this->page('run', 'Runbooks', $this->ops, 1024);
        $this->deep   = $this->page('deep', 'Deep', $this->run, 1024);
        $this->guide  = $this->page('guide', 'Guide', null, 2048);
        $this->locked = $this->page('locked', 'Locked', null, 3072);
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

    public function testWithoutTheOptionThePagesAndTheFormStayAsIn134(): void
    {
        $controller = $this->controller(dialog: false);

        self::assertFalse($controller->viewPage('eng', 'run')->headers->hasCacheControlDirective('no-store'));
        self::assertArrayNotHasKey('new_page_dialog', $this->twig->context);
        self::assertNotContains('wiki-new-page', $this->actionIds());
        self::assertArrayNotHasKey('actions_template', $this->twig->context['tree_ui']);
        self::assertArrayNotHasKey('item_attr', $this->twig->context['tree_ui']['nodes'][0]);
        self::assertFalse($controller->space('eng')->headers->hasCacheControlDirective('no-store'));
        self::assertArrayNotHasKey('new_page_dialog', $this->twig->context);

        $controller->newPage(Request::create('/new?parent=' . $this->ops->getId() . '&position=1'), 'eng');
        $form = $this->twig->context['page']->form;
        self::assertInstanceOf(FormView::class, $form);
        self::assertFalse(isset($form['parent']));
        self::assertFalse(isset($form['position']));

        // Placement fields sent anyway are extra fields: the form is invalid, nothing is created.
        $response = $controller->newPage($this->submit(['title' => 'Sneaky', 'parent' => $this->ops->getId(), 'position' => '1']), 'eng');
        self::assertSame(422, $response->getStatusCode());
        self::assertNull($this->find('sneaky'));

        // A plain create is unchanged: a root page at position 0.
        self::assertInstanceOf(RedirectResponse::class, $controller->newPage($this->submit(['title' => 'Plain']), 'eng'));
        self::assertNull($this->find('plain')?->getParent());
        self::assertSame(0, $this->find('plain')?->getPosition());
    }

    public function testThePageViewOffersTheDialogAndTheTreeMarksTheRowsTheUserMayCreateUnder(): void
    {
        $controller = $this->controller(decide: fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => $action !== WikiAction::Edit || $page !== $this->locked);

        $response = $controller->viewPage('eng', 'run');
        // The dialog carries the user's form token: never cached.
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));

        $dialog = $this->twig->context['new_page_dialog'];
        self::assertSame('tiptap', $dialog['editor_type']);
        self::assertInstanceOf(FormView::class, $dialog['form']);
        self::assertSame('/generated/nowo_wiki_page_new', $dialog['form']->vars['action']);
        self::assertTrue(isset($dialog['form']['parent'], $dialog['form']['position'], $dialog['form']['_token']));
        $action = $this->action('wiki-new-page');
        self::assertSame(WikiNewPageMenu::DIALOG_ID, $action->dialogId);
        self::assertNull($action->href);

        $tree = $this->twig->context['tree_ui'];
        self::assertSame(WikiNewPageMenu::ACTIONS_TEMPLATE, $tree['actions_template']);
        $marked = $this->marked($tree['nodes']);
        self::assertSame(['Operations', 'Runbooks', 'Deep', 'Guide'], $marked);

        // The space home: the same menu on its tree; its "New page" stays the link to the new-page form.
        self::assertTrue($controller->space('eng')->headers->hasCacheControlDirective('no-store'));
        self::assertArrayHasKey('new_page_dialog', $this->twig->context);
        self::assertSame(['Operations', 'Runbooks', 'Deep', 'Guide'], $this->marked($this->twig->context['tree_ui']['nodes']));
        self::assertSame('/generated/nowo_wiki_page_new', $this->action('wiki-new-page')->href);
    }

    public function testWithoutTheCreateRightThereIsNoDialogNorMenu(): void
    {
        $controller = $this->controller(decide: static fn (WikiAction $action): bool => $action !== WikiAction::Create);

        $controller->viewPage('eng', 'run');
        self::assertArrayNotHasKey('new_page_dialog', $this->twig->context);
        self::assertNotContains('wiki-new-page', $this->actionIds());
        self::assertArrayNotHasKey('actions_template', $this->twig->context['tree_ui']);
        self::assertSame([], $this->marked($this->twig->context['tree_ui']['nodes']));

        // The role gate (canCreate) too.
        $this->controller(canCreate: false)->viewPage('eng', 'run');
        self::assertArrayNotHasKey('new_page_dialog', $this->twig->context);
    }

    public function testWithoutTheTreeThePageViewStillOffersTheDialog(): void
    {
        $this->controller(tree: false)->viewPage('eng', 'run');

        self::assertArrayHasKey('new_page_dialog', $this->twig->context);
        self::assertArrayNotHasKey('tree_ui', $this->twig->context);
    }

    public function testTheNewPageFormIsPrefilledFromTheQuery(): void
    {
        $this->controller()->newPage(Request::create('/new?parent=' . $this->ops->getId() . '&position=2'), 'eng');

        $form = $this->twig->context['page']->form;
        self::assertSame($this->ops->getId(), $form['parent']->vars['value']);
        self::assertSame('2', $form['position']->vars['value']);
    }

    public function testAboveBelowFirstLastAndChild(): void
    {
        $controller = $this->controller();

        // Above "Guide" (position 2 at the top level: Operations, Guide, Locked).
        $response = $controller->newPage($this->submit(['title' => 'Above guide', 'parent' => '', 'position' => '2']), 'eng');
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(['Operations', 'Above guide', 'Guide', 'Locked'], $this->order(null));
        // Below "Locked" = last.
        $controller->newPage($this->submit(['title' => 'Last', 'position' => '5']), 'eng');
        // First.
        $controller->newPage($this->submit(['title' => 'First', 'position' => '1']), 'eng');
        self::assertSame(['First', 'Operations', 'Above guide', 'Guide', 'Locked', 'Last'], $this->order(null));
        // As child of Operations: last among its children.
        $controller->newPage($this->submit(['title' => 'Child', 'parent' => $this->ops->getId(), 'position' => '2']), 'eng');
        self::assertSame(['Runbooks', 'Child'], $this->order($this->ops));
        // A parent without a position: created under it as before 1.35 (position 0, first by the sort).
        $controller->newPage($this->submit(['title' => 'Unplaced', 'parent' => $this->ops->getId()]), 'eng');
        self::assertSame($this->ops, $this->find('unplaced')?->getParent());
        self::assertSame(0, $this->find('unplaced')?->getPosition());
    }

    public function testAParentTheUserMayNotEditIsForbidden(): void
    {
        $controller = $this->controller(decide: fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => $action !== WikiAction::Edit || $page !== $this->locked);

        try {
            $controller->newPage($this->submit(['title' => 'Under locked', 'parent' => $this->locked->getId(), 'position' => '1']), 'eng');
            self::fail('The parent was not refused.');
        } catch (AccessDeniedHttpException) {
            self::assertNull($this->find('under-locked'));
        }
    }

    public function testAHiddenArchivedOrForeignParentIsGone(): void
    {
        $controller = $this->controller(decide: fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => $action !== WikiAction::View || $page !== $this->locked);
        $other      = new WikiSpace('ops', 'Ops', WikiSpaceOwnerScope::Team, 't');
        $foreign    = new WikiPage($other, 'x', 'X');
        $this->em->persist($other);
        $this->em->persist($foreign);
        $this->guide->archive();
        $this->em->flush();

        foreach ([$this->locked->getId(), $this->guide->getId(), $foreign->getId(), '00000000-0000-4000-8000-000000000000'] as $parent) {
            $response = $controller->newPage($this->submit(['title' => 'Orphan', 'parent' => $parent, 'position' => '1']), 'eng');
            self::assertSame(422, $response->getStatusCode());
            self::assertSame('wiki.tree.error.parent_missing', $this->flashes('error')[0]->getMessage());
        }
        self::assertNull($this->find('orphan'));
    }

    public function testAPrivateSpaceOrNoCreateRightRefusesBeforeAnything(): void
    {
        $controller = $this->controller(decide: static fn (WikiAction $action): bool => $action !== WikiAction::Create);
        try {
            $controller->newPage($this->submit(['title' => 'Nope', 'position' => '1']), 'eng');
            self::fail('The create was not refused.');
        } catch (AccessDeniedHttpException) {
        }

        // A space the user cannot reach (private, another tenant): 404.
        $this->expectException(NotFoundHttpException::class);
        $this->controller(spaces: [])->newPage($this->submit(['title' => 'Nope']), 'eng');
    }

    public function testAnInvalidPlaceOrAMissingTreeIsAFormError(): void
    {
        $controller = $this->controller();
        foreach (['abc', '0', '-2', '100001'] as $position) {
            $response = $controller->newPage($this->submit(['title' => 'Bad', 'position' => $position]), 'eng');
            self::assertSame(422, $response->getStatusCode());
            self::assertSame('wiki.new_page.error.placement', $this->flashes('error')[0]->getMessage());
        }
        $response = $controller->newPage($this->submit(['title' => 'Bad', 'parent' => "x'1"]), 'eng');
        self::assertSame(422, $response->getStatusCode());

        // Without tree.enabled there is no ordering to place it in.
        $controller = $this->controller(tree: false);
        $response   = $controller->newPage($this->submit(['title' => 'Bad', 'position' => '1']), 'eng');
        self::assertSame(422, $response->getStatusCode());
        self::assertSame('wiki.new_page.error.placement', $this->flashes('error')[0]->getMessage());
        self::assertNull($this->find('bad'));
        // A parent alone still works.
        self::assertInstanceOf(RedirectResponse::class, $controller->newPage($this->submit(['title' => 'Kid', 'parent' => $this->guide->getId()]), 'eng'));
        self::assertSame($this->guide, $this->find('kid')?->getParent());
    }

    public function testTooDeepIsAFormErrorAndWritesNothing(): void
    {
        $response = $this->controller(maxDepth: 3)->newPage($this->submit(['title' => 'Deeper', 'parent' => $this->deep->getId(), 'position' => '1']), 'eng');

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('wiki.tree.error.too_deep', $this->flashes('error')[0]->getMessage());
        self::assertNull($this->find('deeper'));
    }

    public function testADuplicateSlugInsideThePlacedCreateRollsBack(): void
    {
        $response = $this->controller()->newPage($this->submit(['title' => 'Guide', 'position' => '1']), 'eng');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('wiki.page.error.slug_exists', $this->flashes('error')[0]->getMessage());
        self::assertFalse($this->em->getConnection()->isTransactionActive());
        self::assertSame(1024, $this->ops->getPosition());
    }

    public function testTheCsrfTokenIsStillRequired(): void
    {
        $controller = $this->controller();

        $response = $controller->newPage($this->submit(['title' => 'Forged', 'position' => '1'], 'wiki_new_other'), 'eng');

        self::assertSame(422, $response->getStatusCode());
        self::assertNull($this->find('forged'));
    }

    /**
     * @param list<array<string, mixed>> $nodes
     *
     * @return list<string> labels of the nodes marked `data-wiki-new-child`, depth first
     */
    private function marked(array $nodes): array
    {
        $out = [];
        foreach ($nodes as $node) {
            if (($node['item_attr']['data-wiki-new-child'] ?? null) === '1') {
                $out[] = $node['label'];
            }
            /** @var list<array<string, mixed>> $children */
            $children = $node['children'] ?? [];
            $out      = [...$out, ...$this->marked($children)];
        }

        return $out;
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

    private function action(string $testId): Action
    {
        /** @var ShowPage $page */
        $page = $this->twig->context['page'];
        $hits = array_values(array_filter($page->header->actions, static fn (Action $action): bool => ($action->attributes['data-testid'] ?? null) === $testId));
        self::assertCount(1, $hits);

        return $hits[0];
    }

    /**
     * @param array<string, string> $fields
     */
    private function submit(array $fields, ?string $tokenId = null): Request
    {
        $fields += ['content' => '<p>Body</p>'];
        $fields['_token'] = WikiControllerContainerBuilder::csrfToken($this->container, $tokenId ?? 'wiki_new_' . $this->space->getId());

        return Request::create('/new', 'POST', ['wiki_page_form' => $fields]);
    }

    /**
     * @return list<TranslatableMessage>
     */
    private function flashes(string $type): array
    {
        /** @var RequestStack $stack */
        $stack = $this->container->get('request_stack');
        /** @var FlashBagAwareSessionInterface $session */
        $session = $stack->getSession();

        /* @var list<TranslatableMessage> */
        return array_values($session->getFlashBag()->get($type));
    }

    private function find(string $slug): ?WikiPage
    {
        return (new DoctrineOrmWikiPageRepository($this->em))->findBySlug($this->space, $slug);
    }

    /**
     * @return list<string> titles of the active children of the parent (null: the top level), in tree order
     */
    private function order(?WikiPage $parent): array
    {
        $titles = [];
        foreach ((new DoctrineOrmWikiPageRepository($this->em))->findActiveBySpace($this->space) as $page) {
            if ($page->getParent() === $parent) {
                $titles[] = $page->getTitle();
            }
        }

        return $titles;
    }

    private function page(string $slug, string $title, ?WikiPage $parent, int $position): WikiPage
    {
        $page = new WikiPage($this->space, $slug, $title, $parent);
        $page->setPosition($position);
        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }

    /**
     * @param (Closure(WikiAction, WikiSpace, ?WikiPage): bool)|null $decide
     * @param list<WikiSpace>|null $spaces
     */
    private function controller(bool $dialog = true, bool $tree = true, ?Closure $decide = null, int $maxDepth = 10, bool $canCreate = true, ?array $spaces = null): WikiManageController
    {
        $repository = new DoctrineOrmWikiPageRepository($this->em);
        $revisions  = new DoctrineOrmWikiPageRevisionRepository($this->em);
        $roles      = $this->createStub(WikiAccessCheckerInterface::class);
        foreach (['canAccess', 'canList', 'canEdit', 'canViewHistory', 'canArchive', 'canAskAi', 'canImport', 'canExport'] as $method) {
            $roles->method($method)->willReturn(true);
        }
        $roles->method('canCreate')->willReturn($canCreate);
        $resolver = $this->createStub(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn($spaces ?? [$this->space]);
        $decide ??= static fn (): bool => true;
        $service = new WikiPageService($repository, $revisions, new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher());
        $state   = new class implements WikiTreeStateStoreInterface {
            public function expanded(UserInterface $user, WikiSpace $space): array
            {
                return [];
            }

            public function setExpanded(UserInterface $user, WikiSpace $space, string $pageId, bool $expanded): void
            {
            }
        };
        $routes = [];
        foreach (['space', 'page_view', 'page_edit', 'page_new', 'page_history', 'search', 'page_move', 'space_tree'] as $key) {
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
            spaceGuard: new WikiSpaceGuard(new FixedSpaceLocator(...($spaces ?? [$this->space])), new CallbackSpaceAccessChecker($decide)),
            mover: $tree ? new WikiPageMover($repository, $repository, null, $maxDepth) : null,
            treeState: $tree ? $state : null,
            layout: ['new_page_dialog' => $dialog],
        );
        $this->container = WikiControllerContainerBuilder::bind($controller, $this->user, ['twig' => $this->twig]);

        return $controller;
    }
}
