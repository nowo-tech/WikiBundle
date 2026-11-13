<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Ui;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\AreaNavItem;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Label;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Link\WikiLinkedObject;
use Nowo\WikiBundle\Service\WikiPageTreeBuilder;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\PageFixtures;
use Nowo\WikiBundle\Ui\WikiPageFactory;
use Nowo\WikiBundle\Ui\WikiPages;
use PHPUnit\Framework\TestCase;

use function array_column;
use function array_diff_key;
use function array_map;
use function is_scalar;

/**
 * View models of the wiki pages: actions per permission, value-free rows, optional routes.
 */
final class WikiPageFactoryTest extends TestCase
{
    public function testTheImportExportDialogReplacesTheExportActions(): void
    {
        $space   = PageFixtures::space('eng', 'Engineering');
        $factory = new WikiPageFactory(PageFixtures::urlGenerator(), PageFixtures::routes(), new WikiPages(), null, WikiPageFactory::TAB_ICONS, true);

        $full = $factory->space($space, ['create' => true, 'import' => true, 'export' => true]);
        self::assertSame(['create', 'custom'], $this->kinds($full->header->actions));
        self::assertSame(WikiPageFactory::IMPORT_EXPORT_DIALOG_ID, $full->header->actions[1]->dialogId);
        self::assertSame('wiki.import_export.title', $this->label($full->header->actions[1]->label)->key);
        // Import only, export only, neither.
        self::assertSame(['custom'], $this->kinds($factory->space($space, ['create' => false, 'import' => true, 'export' => false])->header->actions));
        self::assertSame(['custom'], $this->kinds($factory->space($space, ['create' => false, 'import' => false, 'export' => true])->header->actions));
        self::assertSame([], $this->kinds($factory->space($space, ['create' => false, 'import' => false, 'export' => false])->header->actions));

        $links = $factory->exportLinks($space, ['export' => true]);
        self::assertSame(['outline', 'notion'], array_column($links, 'format'));
        self::assertSame('wiki.export.notion', $links[1]['label']);
        self::assertStringContainsString('format=notion', $links[1]['href']);
        self::assertSame([], $factory->exportLinks($space, ['export' => false]));
        $routes = PageFixtures::routes();
        unset($routes['space_export']);
        self::assertSame([], (new WikiPageFactory(PageFixtures::urlGenerator(), $routes))->exportLinks($space, ['export' => true]));
    }

    public function testHeaderActionsFollowThePermissionsAndTheDashboardRoute(): void
    {
        $factory  = PageFixtures::factory(null, 'app_dashboard');
        $space    = PageFixtures::space('eng', 'Engineering');
        [, $page] = PageFixtures::pages($space);

        $full = $factory->space($space, ['create' => true, 'import' => true, 'export' => true]);
        self::assertSame(['create', 'export', 'export', 'prev'], $this->kinds($full->header->actions));
        self::assertSame('/app_dashboard', $full->header->actions[3]->href);

        self::assertSame(['prev'], $this->kinds($factory->space($space, ['create' => false, 'import' => false, 'export' => false])->header->actions));
        // 1.12: "or create one" only for users who may create pages.
        self::assertSame('wiki.space.pick_page', $this->label($full->header->intro)->key);
        self::assertSame('wiki.space.pick_page_read_only', $this->label($factory->space($space, ['create' => false, 'import' => true, 'export' => true])->header->intro)->key);
        self::assertSame([], $this->kinds($factory->pageView($space, $page, ['edit' => false, 'history' => false])->header->actions));
        // 1.25 (WK-6): Edit and History are tabs only, never header actions as well.
        $view = $factory->pageView($space, $page, ['edit' => true, 'history' => true], PageFixtures::postForm('t'));
        self::assertSame(['delete'], $this->kinds($view->header->actions));
        self::assertSame(['wiki-tab-page', 'wiki-tab-edit', 'wiki-tab-history'], array_map(static fn (AreaNavItem $item): ?string => $item->attributes['data-testid'] ?? null, $view->header->tabs->items ?? []));
        self::assertSame([], $this->kinds((new WikiPageFactory(PageFixtures::urlGenerator(), PageFixtures::routes(), new WikiPages(), ''))->search(PageFixtures::searchForm(null), [], '', false)->header->actions));
    }

    public function testRowsAreValueFree(): void
    {
        $factory = PageFixtures::factory();
        $index   = PageFixtures::index()['page'];
        $search  = PageFixtures::search()['page'];
        $history = PageFixtures::history()['page'];

        foreach ([$index, $search, $history] as $list) {
            foreach ($list->rows as $row) {
                self::assertIsArray($row);
                foreach ($row as $value) {
                    self::assertTrue($value === null || is_scalar($value), 'rows hold scalars only');
                }
            }
        }

        self::assertSame(['id' => 'engineering', 'name' => 'Engineering', 'slug' => 'engineering', 'icon' => null, 'href' => '/nowo_wiki_space?spaceSlug=engineering'], $index->rows[0]);
        $welcome = new WikiPage(PageFixtures::space('engineering', 'E'), 'welcome', 'Welcome');
        self::assertSame([['id' => $welcome->getId(), 'title' => 'Welcome', 'href' => '/nowo_wiki_page_view?spaceSlug=engineering&pageSlug=welcome', 'current' => false, 'children' => []]], $factory->tree(PageFixtures::space('engineering', 'Engineering'), [['page' => $welcome, 'children' => 'not-a-list']]));
    }

    public function testIndexRowsCarryTheSpaceIcon(): void
    {
        $space = PageFixtures::space('eng', 'Engineering');
        $space->setIcon('tabler:rocket');
        $plain = PageFixtures::space('ops', 'Operations');
        $rows  = PageFixtures::factory()->index([$space, $plain], PageFixtures::index()['page']->pagination, PageFixtures::searchForm(null), false)->rows;

        self::assertSame('tabler:rocket', $rows[0]['icon']);
        self::assertNull($rows[1]['icon']);
    }

    public function testTheTreeViewModels(): void
    {
        $routes                           = PageFixtures::routes() + ['space_tree' => ['path' => '/t', 'name' => 'nowo_wiki_space_tree'], 'page_move' => ['path' => '/m', 'name' => 'nowo_wiki_page_move']];
        $factory                          = new WikiPageFactory(PageFixtures::urlGenerator(), $routes, new WikiPages(), '');
        $space                            = PageFixtures::space('eng', 'Engineering');
        [$root, $page, $child, $glossary] = PageFixtures::pages($space);
        $nodes                            = $factory->tree($space, (new WikiPageTreeBuilder())->build([$root, $page, $child, $glossary]), 'rollback');

        $ui = $factory->uiTree($nodes, [$root->getId(), 'gone']);
        self::assertSame(['id' => $root->getId(), 'label' => 'Welcome', 'href' => '/nowo_wiki_page_view?spaceSlug=eng&pageSlug=welcome', 'current' => false, 'expanded' => true], array_diff_key($ui[0], ['children' => true]));
        self::assertFalse($ui[0]['children'][0]['expanded']);
        self::assertTrue($ui[0]['children'][0]['children'][0]['current']);
        self::assertSame([], $ui[1]['children']);

        self::assertSame([
            'id'                   => 'nowo-wiki-tree',
            'data-wiki-tree'       => true,
            'data-wiki-tree-state' => '/nowo_wiki_space_tree?spaceSlug=eng&action=state',
            'data-wiki-tree-token' => 'tok',
            'data-testid'          => 'wiki-tree',
            'data-wiki-tree-move'  => '/nowo_wiki_space_tree?spaceSlug=eng&action=move',
        ], $factory->treeAttributes($space, 'tok', true));
        self::assertArrayNotHasKey('data-wiki-tree-move', $factory->treeAttributes($space, 'tok', false));

        self::assertSame(['edit'], $this->kinds($factory->pageView($space, $page, ['edit' => true, 'history' => false, 'move' => true])->header->actions));
        self::assertSame([], $this->kinds($factory->pageView($space, $page, ['edit' => true, 'history' => false, 'move' => false])->header->actions));

        $move = $factory->pageMove($space, $page, PageFixtures::postForm('t'));
        self::assertSame('wiki.tree.move_title', $this->label($move->header->title)->key);
        self::assertSame('/nowo_wiki_page_view?spaceSlug=eng&pageSlug=deploy', $move->cancelHref);
        self::assertSame('wiki.tree.move', $this->label($move->submitLabel)->key);
    }

    /** 1.29 web_ui.page_header.layout: compact — the read view's header actions use the UiKit split layout. */
    public function testTheCompactHeaderSplitsThePageViewActions(): void
    {
        $space    = PageFixtures::space('eng', 'Engineering');
        [, $page] = PageFixtures::pages($space);
        $default  = new WikiPageFactory(PageFixtures::urlGenerator(), PageFixtures::routes(), new WikiPages(), '');
        self::assertSame('toolbar', $default->pageView($space, $page, ['edit' => true])->header->actionsLayout);
        $compact = new WikiPageFactory(PageFixtures::urlGenerator(), PageFixtures::routes(), new WikiPages(), '', compactHeader: true);
        $header  = $compact->pageView($space, $page, ['edit' => true])->header;
        self::assertSame('split', $header->actionsLayout);
        self::assertNotNull($header->tabs);

        // Move, Delete and Archive: danger, their own verb (icon), last in the "more" menu after Save version.
        $routes  = PageFixtures::routes() + ['page_version_save' => ['path' => '/v', 'name' => 'version'], 'page_move' => ['path' => '/m', 'name' => 'move'], 'page_delete' => ['path' => '/d', 'name' => 'delete']];
        $compact = new WikiPageFactory(PageFixtures::urlGenerator(), $routes, new WikiPages(), '', compactHeader: true);
        $default = new WikiPageFactory(PageFixtures::urlGenerator(), $routes, new WikiPages(), '');
        $all     = ['edit' => true, 'version' => true, 'move' => true];
        $forms   = [PageFixtures::postForm('a'), PageFixtures::postForm('d')];
        $split   = $compact->pageView($space, $page, $all, $forms[0], $forms[1])->header->splitActions();
        self::assertSame('create', $split->primary?->kind);
        self::assertSame(['move', 'delete', 'archive'], array_map(static fn (Action $a): string => $a->kind, $split->more));
        foreach ($split->more as $action) {
            self::assertSame('danger', $action->resolvedVariant());
        }
        // The stacked header keeps the 1.28 kinds.
        $kinds = array_map(static fn (Action $a): string => $a->kind, $default->pageView($space, $page, $all, $forms[0], $forms[1])->header->actions);
        self::assertSame(['create', 'edit', 'delete', 'delete'], $kinds);
    }

    /**
     * 1.31: page_header.tabs: toolbar (the edit form without back link and space intro, Reset collaboration a danger
     * item of the split "more" menu) and page_meta: card (no scaffold meta).
     */
    public function testTheTabsToolbarEditFormAndTheMetaInTheCard(): void
    {
        $space    = PageFixtures::space('eng', 'Engineering');
        [, $page] = PageFixtures::pages($space);
        $page->setCurrentRevision(new WikiPageRevision($page, 2, '<p>x</p>', new TestUser()));
        $form  = PageFixtures::searchForm(null);
        $reset = PageFixtures::postForm('wiki_collab_reset');

        $default = new WikiPageFactory(PageFixtures::urlGenerator(), PageFixtures::routes(), new WikiPages(), '');
        $header  = $default->pageForm($space, $page, $form, $reset)->header;
        self::assertSame('Engineering', $header->intro);
        self::assertNotNull($header->back);
        self::assertSame('toolbar', $header->actionsLayout);
        self::assertNull($header->actions[0]->variant);
        self::assertCount(2, $default->pageView($space, $page, ['edit' => true])->meta);

        $toolbar = new WikiPageFactory(PageFixtures::urlGenerator(), PageFixtures::routes(), new WikiPages(), '', tabsToolbar: true, metaInCard: true);
        $header  = $toolbar->pageForm($space, $page, $form, $reset)->header;
        self::assertNull($header->intro);
        self::assertNull($header->back);
        self::assertSame('split', $header->actionsLayout);
        $split = $header->splitActions();
        self::assertNull($split->primary);
        self::assertSame(['delete'], array_map(static fn (Action $a): string => $a->kind, $split->more));
        self::assertSame('danger', $split->more[0]->resolvedVariant());
        self::assertNotNull($split->more[0]->confirm);
        self::assertSame([], $toolbar->pageView($space, $page, ['edit' => true])->meta);
        // A new page has no toolbar: its header is unchanged.
        $new = $toolbar->pageForm($space, null, $form)->header;
        self::assertSame('Engineering', $new->intro);
        self::assertNotNull($new->back);
    }

    public function testThePageTabsCarryTheirIcons(): void
    {
        $routes   = PageFixtures::routes();
        $space    = PageFixtures::space('eng', 'Engineering');
        [, $page] = PageFixtures::pages($space);
        $can      = ['edit' => true, 'history' => true];

        $default = new WikiPageFactory(PageFixtures::urlGenerator(), $routes, new WikiPages(), '');
        self::assertSame(['tabler:file-text', 'tabler:pencil', 'tabler:history'], array_map(static fn (AreaNavItem $item): ?string => $item->icon, $default->pageTabs($space, $page, $can, 'page')->items));

        // Overridden, removed (null / ''), and a key the host left out falls back to the default.
        $custom = new WikiPageFactory(PageFixtures::urlGenerator(), $routes, new WikiPages(), '', ['page' => 'bi:file-earmark', 'edit' => null, 'history' => '']);
        self::assertSame(['bi:file-earmark', null, null], array_map(static fn (AreaNavItem $item): ?string => $item->icon, $custom->pageTabs($space, $page, $can, 'edit')->items));
        $partial = new WikiPageFactory(PageFixtures::urlGenerator(), $routes, new WikiPages(), '', ['edit' => 'bi:pencil']);
        self::assertSame(['tabler:file-text', 'bi:pencil', 'tabler:history'], array_map(static fn (AreaNavItem $item): ?string => $item->icon, $partial->pageTabs($space, $page, $can, 'page')->items));
    }

    public function testTheHistoryTimelineViewModels(): void
    {
        $routes    = PageFixtures::routes() + ['page_version_save' => ['path' => '/vs', 'name' => 'nowo_wiki_page_version_save'], 'page_version_copy' => ['path' => '/vc', 'name' => 'nowo_wiki_page_version_copy']];
        $factory   = new WikiPageFactory(PageFixtures::urlGenerator(), $routes, new WikiPages(), '');
        $space     = PageFixtures::space('eng', 'Engineering');
        [, $page]  = PageFixtures::pages($space);
        $revisions = [new WikiPageRevision($page, 3, '', new TestUser()), new WikiPageRevision($page, 2, '', new TestUser()), new WikiPageRevision($page, 1, '', new TestUser())];

        $actions = $factory->pageView($space, $page, ['edit' => true, 'history' => true, 'version' => true])->header->actions;
        self::assertSame(['create'], $this->kinds($actions));
        self::assertSame('wiki-save-version', $actions[0]->attributes['data-testid']);

        $history = $factory->history($space, $page, $revisions, null, [], null, [
            3 => ['author' => 'Editor ab12', 'label' => 'Reviewed', 'saves' => 1],
            2 => ['author' => 'Editor cd34', 'label' => null, 'saves' => 2],
        ], [3 => PageFixtures::postForm('t')]);
        self::assertSame(['number', 'label', 'author', 'created', 'session'], array_map(static fn (Column $c): string => $c->key, $history->columns));
        self::assertSame(['Reviewed', 'Editor ab12', ''], [$history->rows[0]['label'], $history->rows[0]['author'], $history->rows[0]['session']]);
        self::assertSame(2, $history->rows[1]['session']);
        // A revision missing from the timeline gets empty cells.
        self::assertSame(['', '', ''], [$history->rows[2]['label'], $history->rows[2]['author'], $history->rows[2]['session']]);
        $copy = $history->rowActions($history->rows[0]);
        self::assertSame('wiki-version-copy', $copy[1]->attributes['data-testid']);
        self::assertSame('POST', $copy[1]->method);
        self::assertCount(1, $history->rowActions($history->rows[1]));

        $form = $factory->pageVersion($space, $page, PageFixtures::postForm('t'));
        self::assertSame('wiki.history.save_version_title', $this->label($form->header->title)->key);
        self::assertSame('wiki.history.save_version', $this->label($form->submitLabel)->key);
        self::assertSame('/nowo_wiki_page_view?spaceSlug=eng&pageSlug=deploy', $form->cancelHref);
    }

    public function testRoutesThatAreNotConfiguredGiveNoActionOrLink(): void
    {
        $factory  = new WikiPageFactory(PageFixtures::urlGenerator(), ['space' => ['path' => '/s', 'name' => 'space'], 'page_view' => ['path' => '/v', 'name' => 'view']]);
        $space    = PageFixtures::space('eng', 'Engineering');
        [, $page] = PageFixtures::pages($space);

        self::assertSame([], $factory->index([], PageFixtures::index()['page']->pagination, PageFixtures::searchForm(null), true)->header->actions);
        self::assertSame([], $factory->space($space, ['create' => true, 'import' => true, 'export' => true])->header->actions);
        self::assertSame([], $factory->pageView($space, $page, ['edit' => true, 'history' => true, 'version' => true], PageFixtures::postForm('t'))->header->actions);
        self::assertNull($factory->diff($space, $page, 1, 2)->header->back);
        self::assertNull($factory->pageView($space, $page, ['edit' => false, 'history' => false])->header->breadcrumbs[0]->href);
        self::assertSame([['title' => 'Deploy', 'action' => null]], $factory->archived($space, [$page], [$page->getId() => PageFixtures::postForm('t')]));

        $history = $factory->history($space, $page, [new WikiPageRevision($page, 2, '', new TestUser())], null, [2 => PageFixtures::postForm('t')], PageFixtures::index()['page']->pagination, [], [2 => PageFixtures::postForm('t')]);
        self::assertSame([], $history->rowActions($history->rows[0]));
    }

    public function testHitsAndUsedInAreValueFree(): void
    {
        $factory  = PageFixtures::factory();
        $space    = PageFixtures::space('eng', 'Engineering');
        [, $page] = PageFixtures::pages($space);

        self::assertSame([['title' => 'Deploy', 'space' => 'Engineering', 'excerpt' => '…', 'href' => '/nowo_wiki_page_view?spaceSlug=eng&pageSlug=deploy']], $factory->hits([['page' => $page, 'space' => $space, 'excerpt' => '…']]));
        self::assertSame([['type' => 'ticket', 'label' => 'T-1', 'href' => '/t/1', 'badge' => null]], $factory->usedIn(['ticket' => [new WikiLinkedObject('ticket', '1', 'T-1', '/t/1')]]));
    }

    public function testLabelsUseTheBundleDomain(): void
    {
        $title = PageFixtures::index()['page']->header->title;

        self::assertInstanceOf(Label::class, $title);
        self::assertSame(WikiPages::DOMAIN, $title->domain);
        self::assertSame(WikiPages::TEMPLATES['index'], PageFixtures::factory()->template('index'));
    }

    /**
     * @param iterable<Action> $actions
     *
     * @return list<string>
     */
    private function kinds(iterable $actions): array
    {
        return array_map(static fn (Action $action): string => $action->kind, [...$actions]);
    }

    private function label(mixed $label): Label
    {
        self::assertInstanceOf(Label::class, $label);

        return $label;
    }
}
