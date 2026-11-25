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
use Nowo\WikiBundle\Port\Internal\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Port\Internal\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Port\Internal\WikiTreeStateStoreInterface;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Service\WikiPageIconService;
use Nowo\WikiBundle\Service\WikiPageMover;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiPageTreeBuilder;
use Nowo\WikiBundle\Service\WikiRevisionDiffService;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use Nowo\WikiBundle\Tests\Support\WikiControllerContainerBuilder;
use Nowo\WikiBundle\Ui\WikiNewPageMenu;
use Nowo\WikiBundle\Ui\WikiTreeMenu;
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
use function str_repeat;

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

        $controller->newPage(Request::create('/new?place=child:' . $this->ops->getId()), 'eng');
        $form = $this->twig->context['page']->form;
        self::assertInstanceOf(FormView::class, $form);
        self::assertFalse(isset($form['place']));

        // A place sent anyway is an extra field: the form is invalid, nothing is created.
        $response = $controller->newPage($this->submit(['title' => 'Sneaky', 'place' => 'child:' . $this->ops->getId()]), 'eng');
        self::assertSame(422, $response->getStatusCode());
        self::assertNull($this->find('sneaky'));

        // A plain create is unchanged: a root page at position 0.
        self::assertInstanceOf(RedirectResponse::class, $controller->newPage($this->submit(['title' => 'Plain']), 'eng'));
        self::assertNull($this->find('plain')?->getParent());
        self::assertSame(0, $this->find('plain')?->getPosition());
    }

    public function testThePageViewOffersTheDialogAndTheTreeMarksItsRowsWithOneCheck(): void
    {
        // PT-L4: Edit on the space decides the menu once; a page the user may not edit is still marked (the server
        // answers 403 for it).
        $checks     = 0;
        $controller = $this->controller(decide: function (WikiAction $action, WikiSpace $space, ?WikiPage $page) use (&$checks): bool {
            if ($action === WikiAction::Edit && $page instanceof WikiPage) {
                ++$checks;
            }

            return $action !== WikiAction::Edit || $page !== $this->locked;
        });

        $response = $controller->viewPage('eng', 'run');
        // The dialog carries the user's form token: never cached.
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));

        $dialog = $this->twig->context['new_page_dialog'];
        self::assertSame('tiptap', $dialog['editor_type']);
        // 1.53 (lazy dialogs): no form in the page; the dialog loads the new-page form's page when it opens.
        self::assertArrayNotHasKey('form', $dialog);
        self::assertSame('/generated/nowo_wiki_page_new', $dialog['url']);
        $action = $this->action('wiki-new-page');
        self::assertSame(WikiNewPageMenu::DIALOG_ID, $action->dialogId);
        self::assertSame('/generated/nowo_wiki_page_new', $action->href);
        self::assertTrue($action->attributes['data-nowo-ui-dialog-form']);

        $tree = $this->twig->context['tree_ui'];
        self::assertSame(WikiNewPageMenu::ACTIONS_TEMPLATE, $tree['actions_template']);
        self::assertSame(['Operations', 'Runbooks', 'Deep', 'Guide', 'Locked'], $this->marked($tree['nodes'], 'data-wiki-new-child'));
        self::assertSame(['Operations', 'Runbooks', 'Deep', 'Guide', 'Locked'], $this->marked($tree['nodes'], 'data-wiki-new-sibling'));
        $before = $checks;

        // The space home: the same menu on its tree; its "New page" stays the link to the new-page form.
        self::assertTrue($controller->space('eng')->headers->hasCacheControlDirective('no-store'));
        self::assertArrayHasKey('new_page_dialog', $this->twig->context);
        self::assertSame(['Operations', 'Runbooks', 'Deep', 'Guide', 'Locked'], $this->marked($this->twig->context['tree_ui']['nodes'], 'data-wiki-new-child'));
        self::assertSame('/generated/nowo_wiki_page_new', $this->action('wiki-new-page')->href);
        // No page-level Edit check for the marks (the page view's own Move check aside).
        self::assertLessThanOrEqual(2, $before);
        self::assertSame($before, $checks);
    }

    public function testARowWhoseParentIsArchivedGetsNoAboveNorBelow(): void
    {
        // Runbooks' parent (Operations) is archived: Runbooks shows as a top-level row, but it is not one (a hidden
        // parent hides its whole branch instead).
        $this->ops->archive();
        $this->em->flush();
        $this->controller()->space('eng');

        $nodes = $this->twig->context['tree_ui']['nodes'];
        self::assertSame(['Runbooks', 'Deep', 'Guide', 'Locked'], $this->marked($nodes, 'data-wiki-new-child'));
        self::assertSame(['Deep', 'Guide', 'Locked'], $this->marked($nodes, 'data-wiki-new-sibling'));

        // The server agrees: no place next to it; under it is fine.
        $controller = $this->controller();
        $response   = $controller->newPage($this->submit(['title' => 'Next', 'place' => 'below:' . $this->run->getId()]), 'eng');
        self::assertSame(422, $response->getStatusCode());
        self::assertSame('wiki.tree.error.parent_missing', $this->flashes('error')[0]->getMessage());
        self::assertInstanceOf(RedirectResponse::class, $controller->newPage($this->submit(['title' => 'Under', 'place' => 'child:' . $this->run->getId()]), 'eng'));
    }

    public function testWithoutTheEditRightsTheDialogStaysAndTheMenuGoes(): void
    {
        // PT-L2 / PT-L4: no Edit on the space, or no role gate canEdit(): no menu.
        foreach ([$this->controller(decide: static fn (WikiAction $action): bool => $action !== WikiAction::Edit), $this->controller(canEdit: false)] as $controller) {
            $controller->viewPage('eng', 'run');
            self::assertArrayHasKey('new_page_dialog', $this->twig->context);
            self::assertArrayNotHasKey('actions_template', $this->twig->context['tree_ui']);
            self::assertSame([], $this->marked($this->twig->context['tree_ui']['nodes'], 'data-wiki-new-child'));
        }
    }

    public function testWithoutTheCreateRightThereIsNoDialogNorMenu(): void
    {
        $controller = $this->controller(decide: static fn (WikiAction $action): bool => $action !== WikiAction::Create);

        $controller->viewPage('eng', 'run');
        self::assertArrayNotHasKey('new_page_dialog', $this->twig->context);
        self::assertNotContains('wiki-new-page', $this->actionIds());
        self::assertArrayNotHasKey('actions_template', $this->twig->context['tree_ui']);
        self::assertSame([], $this->marked($this->twig->context['tree_ui']['nodes'], 'data-wiki-new-child'));

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
        $this->controller()->newPage(Request::create('/new?place=below:' . $this->run->getId()), 'eng');

        $form = $this->twig->context['page']->form;
        self::assertSame('below:' . $this->run->getId(), $form['place']->vars['value']);
    }

    public function testTheServerComputesThePlaceFromTheIntent(): void
    {
        $controller = $this->controller();

        // PT-L3: the browser sends what was asked; the server finds the place.
        $response = $controller->newPage($this->submit(['title' => 'Above guide', 'place' => 'above:' . $this->guide->getId()]), 'eng');
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(['Operations', 'Above guide', 'Guide', 'Locked'], $this->order(null));
        $controller->newPage($this->submit(['title' => 'Last', 'place' => 'below:' . $this->locked->getId()]), 'eng');
        $controller->newPage($this->submit(['title' => 'First', 'place' => 'above:' . $this->ops->getId()]), 'eng');
        self::assertSame(['First', 'Operations', 'Above guide', 'Guide', 'Locked', 'Last'], $this->order(null));
        // As child: the last place among the children, whatever the browser shows of them.
        $controller->newPage($this->submit(['title' => 'Child', 'place' => 'child:' . $this->ops->getId()]), 'eng');
        $controller->newPage($this->submit(['title' => 'Child 2', 'place' => 'child:' . $this->ops->getId()]), 'eng');
        self::assertSame(['Runbooks', 'Child', 'Child 2'], $this->order($this->ops));
        // Next to a nested page: under its parent.
        $controller->newPage($this->submit(['title' => 'Above run', 'place' => 'above:' . $this->run->getId()]), 'eng');
        self::assertSame(['Above run', 'Runbooks', 'Child', 'Child 2'], $this->order($this->ops));
    }

    public function testAParentTheUserMayNotEditIsForbidden(): void
    {
        $controller = $this->controller(decide: fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => $action !== WikiAction::Edit || $page !== $this->ops);

        foreach (['child:' . $this->ops->getId(), 'below:' . $this->run->getId()] as $place) {
            try {
                $controller->newPage($this->submit(['title' => 'Under ops', 'place' => $place]), 'eng');
                self::fail('The parent was not refused.');
            } catch (AccessDeniedHttpException) {
                self::assertNull($this->find('under-ops'));
            }
        }
        // At the top level the create right is enough.
        self::assertInstanceOf(RedirectResponse::class, $controller->newPage($this->submit(['title' => 'Top', 'place' => 'below:' . $this->ops->getId()]), 'eng'));
    }

    public function testAPlaceNeedsTheRoleGateCanEdit(): void
    {
        // PT-L2: as a tree move.
        $controller = $this->controller(canEdit: false);

        try {
            $controller->newPage($this->submit(['title' => 'Placed', 'place' => 'below:' . $this->guide->getId()]), 'eng');
            self::fail('The place was not refused.');
        } catch (AccessDeniedHttpException) {
            self::assertNull($this->find('placed'));
        }
        // Without a place: the create right is enough, as before.
        self::assertInstanceOf(RedirectResponse::class, $controller->newPage($this->submit(['title' => 'Unplaced']), 'eng'));
    }

    public function testAHiddenArchivedOrForeignPageIsGone(): void
    {
        $controller = $this->controller(decide: fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => $action !== WikiAction::View || $page !== $this->locked);
        $other      = new WikiSpace('ops', 'Ops', WikiSpaceOwnerScope::Team, 't');
        $foreign    = new WikiPage($other, 'x', 'X');
        $this->em->persist($other);
        $this->em->persist($foreign);
        $this->guide->archive();
        $this->em->flush();

        foreach ([$this->locked->getId(), $this->guide->getId(), $foreign->getId(), '00000000-0000-4000-8000-000000000000'] as $id) {
            foreach (['child', 'above', 'below'] as $kind) {
                $response = $controller->newPage($this->submit(['title' => 'Orphan', 'place' => $kind . ':' . $id]), 'eng');
                self::assertSame(422, $response->getStatusCode());
                self::assertSame('wiki.tree.error.parent_missing', $this->flashes('error')[0]->getMessage());
            }
        }
        self::assertNull($this->find('orphan'));
    }

    public function testNextToAPageWhoseParentIsHiddenIsRefused(): void
    {
        // Operations hidden: Runbooks is readable but its parent is not, so there is no place next to it.
        $controller = $this->controller(decide: fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => $page !== $this->ops);

        $response = $controller->newPage($this->submit(['title' => 'Next', 'place' => 'below:' . $this->run->getId()]), 'eng');

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('wiki.tree.error.parent_missing', $this->flashes('error')[0]->getMessage());
        self::assertNull($this->find('next'));
    }

    public function testAPrivateSpaceOrNoCreateRightRefusesBeforeAnything(): void
    {
        $controller = $this->controller(decide: static fn (WikiAction $action): bool => $action !== WikiAction::Create);
        try {
            $controller->newPage($this->submit(['title' => 'Nope', 'place' => 'child:' . $this->ops->getId()]), 'eng');
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
        foreach (['abc', 'child:', 'side:' . $this->ops->getId(), "child:x'1", '2', 'child:' . str_repeat('a', 37)] as $place) {
            $response = $controller->newPage($this->submit(['title' => 'Bad', 'place' => $place]), 'eng');
            self::assertSame(422, $response->getStatusCode(), $place);
            self::assertSame('wiki.new_page.error.placement', $this->flashes('error')[0]->getMessage());
        }

        // Without tree.enabled there is no ordering (nor depth limit) to place it in.
        $controller = $this->controller(tree: false);
        $response   = $controller->newPage($this->submit(['title' => 'Bad', 'place' => 'child:' . $this->guide->getId()]), 'eng');
        self::assertSame(422, $response->getStatusCode());
        self::assertSame('wiki.new_page.error.placement', $this->flashes('error')[0]->getMessage());
        self::assertNull($this->find('bad'));
    }

    public function testTooDeepIsAFormErrorAndWritesNothing(): void
    {
        // PT-M1: a child always goes through the mover, so the depth limit always applies.
        $controller = $this->controller(maxDepth: 3);
        foreach (['child:' . $this->deep->getId(), 'below:' . $this->deep->getId()] as $i => $place) {
            $response = $controller->newPage($this->submit(['title' => 'Deeper', 'place' => $place]), 'eng');
            if ($i === 0) {
                self::assertSame(422, $response->getStatusCode());
                self::assertSame('wiki.tree.error.too_deep', $this->flashes('error')[0]->getMessage());
                self::assertNull($this->find('deeper'));
            } else {
                // Next to Deep (level 3) is level 3: allowed.
                self::assertInstanceOf(RedirectResponse::class, $response);
            }
        }
    }

    public function testADuplicateSlugInsideThePlacedCreateRollsBack(): void
    {
        $response = $this->controller()->newPage($this->submit(['title' => 'Guide', 'place' => 'above:' . $this->ops->getId()]), 'eng');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('wiki.page.error.slug_exists', $this->flashes('error')[0]->getMessage());
        self::assertFalse($this->em->getConnection()->isTransactionActive());
        self::assertSame(1024, $this->ops->getPosition());
    }

    public function testTheCsrfTokenIsStillRequired(): void
    {
        $controller = $this->controller();

        $response = $controller->newPage($this->submit(['title' => 'Forged', 'place' => 'child:' . $this->ops->getId()], 'wiki_new_other'), 'eng');

        self::assertSame(422, $response->getStatusCode());
        self::assertNull($this->find('forged'));
    }

    /**
     * @return list<string> labels of the nodes with the attribute, depth first
     */
    public function testTheTreeContextMenuMarksRowsAndReplacesTheNewPageMenu(): void
    {
        // 1.39 (tree.context_menu): with the dialog, the menu offers it ("above" too); rows marked once per page.
        $this->controller(menu: true, iconField: true)->viewPage('eng', 'run');
        $tree = $this->twig->context['tree_ui'];
        self::assertSame(WikiTreeMenu::ACTIONS_TEMPLATE, $tree['actions_template']);
        self::assertSame(['Operations', 'Runbooks', 'Deep', 'Guide', 'Locked'], $this->marked($tree['nodes'], WikiTreeMenu::EDIT_ATTRIBUTE));
        self::assertSame(['Operations', 'Runbooks', 'Deep', 'Guide', 'Locked'], $this->marked($tree['nodes'], WikiTreeMenu::ICON_ATTRIBUTE));
        self::assertSame(['Operations', 'Runbooks', 'Deep', 'Guide', 'Locked'], $this->marked($tree['nodes'], 'data-wiki-new-child'));
        self::assertSame('/generated/nowo_wiki_space_tree', $tree['attr']['data-wiki-tree-rename']);
        self::assertArrayNotHasKey('data-wiki-tree-new', $tree['attr']);
        $menu = $this->twig->context['tree_menu'];
        self::assertTrue($menu['dialog']);
        // 1.53 (lazy dialogs): the icon form is not built; its dialog loads it from the space page.
        self::assertNull($menu['icon_form']);
        self::assertSame('/generated/nowo_wiki_space', $menu['icon_src']);

        // Without the dialog: the new-page form (?place=); a row whose parent is archived gets no "sibling".
        $this->ops->archive();
        $this->em->flush();
        $this->controller(dialog: false, menu: true)->space('eng');
        $tree = $this->twig->context['tree_ui'];
        self::assertSame('/generated/nowo_wiki_page_new', $tree['attr']['data-wiki-tree-new']);
        self::assertSame(['Deep', 'Guide', 'Locked'], $this->marked($tree['nodes'], 'data-wiki-new-sibling'));
        self::assertSame([], $this->marked($tree['nodes'], WikiTreeMenu::ICON_ATTRIBUTE));
        self::assertNull($this->twig->context['tree_menu']['icon_form']);
        self::assertNull($this->twig->context['tree_menu']['icon_src']);
    }

    public function testReadersGetNoTreeMenuAndEditorsWithoutCreateNoAddEntries(): void
    {
        $this->controller(canEdit: false, menu: true)->viewPage('eng', 'run');
        self::assertArrayNotHasKey('tree_menu', $this->twig->context);
        self::assertSame([], $this->marked($this->twig->context['tree_ui']['nodes'], WikiTreeMenu::EDIT_ATTRIBUTE));

        $this->controller(dialog: false, canCreate: false, menu: true)->viewPage('eng', 'run');
        self::assertSame(['Operations', 'Runbooks', 'Deep', 'Guide', 'Locked'], $this->marked($this->twig->context['tree_ui']['nodes'], WikiTreeMenu::EDIT_ATTRIBUTE));
        self::assertSame([], $this->marked($this->twig->context['tree_ui']['nodes'], 'data-wiki-new-child'));
    }

    private function marked(array $nodes, string $attribute): array
    {
        $out = [];
        foreach ($nodes as $node) {
            if (($node['item_attr'][$attribute] ?? null) === '1') {
                $out[] = $node['label'];
            }
            /** @var list<array<string, mixed>> $children */
            $children = $node['children'] ?? [];
            $out      = [...$out, ...$this->marked($children, $attribute)];
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
    private function controller(bool $dialog = true, bool $tree = true, ?Closure $decide = null, int $maxDepth = 10, bool $canCreate = true, ?array $spaces = null, bool $canEdit = true, bool $menu = false, bool $iconField = false): WikiManageController
    {
        $repository = new DoctrineOrmWikiPageRepository($this->em);
        $revisions  = new DoctrineOrmWikiPageRevisionRepository($this->em);
        $roles      = $this->createStub(WikiAccessCheckerInterface::class);
        foreach (['canAccess', 'canList', 'canViewHistory', 'canArchive', 'canAskAi', 'canImport', 'canExport'] as $method) {
            $roles->method($method)->willReturn(true);
        }
        $roles->method('canCreate')->willReturn($canCreate);
        $roles->method('canEdit')->willReturn($canEdit);
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
            pageIcons: $iconField ? new WikiPageIconService($repository) : null,
            pageIconField: $iconField,
            treeContextMenu: $menu,
        );
        $this->container = WikiControllerContainerBuilder::bind($controller, $this->user, ['twig' => $this->twig]);

        return $controller;
    }
}
