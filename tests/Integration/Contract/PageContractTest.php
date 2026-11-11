<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\Contract;

use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;
use Nowo\UiKitBundle\Test\StubHost;
use Nowo\WikiBundle\Tests\Support\PageFixtures;
use Nowo\WikiBundle\Ui\WikiPageFactory;
use Nowo\WikiBundle\Ui\WikiPages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;

use function is_array;
use function preg_match;
use function strpos;
use function substr_count;

/**
 * REQ-UI-003 page contract: every wiki page extends a UiKit scaffold (list, show, form); no <html>, CDN, inline handler
 * or confirm(); framework classes only through theme slots; the stub theme is reflected; no raw or missing translation
 * keys (strict rule); the CSRF forms of the archive / unarchive / restore actions keep their hidden fields. Plus the
 * wiki-specific regions: page tree, AI dialog, sanitized content.
 */
final class PageContractTest extends PageContractTestCase
{
    use WikiContractEnvironment;

    public static function pageFixtures(): iterable
    {
        yield 'index' => new PageFixture('@NowoWikiBundle/manage/index.html.twig', PageFixtures::index());
        yield 'index (empty, no AI)' => new PageFixture('@NowoWikiBundle/manage/index.html.twig', PageFixtures::index(true, false));
        yield 'space' => new PageFixture('@NowoWikiBundle/manage/space.html.twig', PageFixtures::spaceHome());
        yield 'space (read only)' => new PageFixture('@NowoWikiBundle/manage/space.html.twig', PageFixtures::spaceHome(false));
        yield 'page view' => new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', PageFixtures::pageView());
        yield 'page view (read only)' => new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', PageFixtures::pageView(false));
        yield 'page view (comments open)' => new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', PageFixtures::pageView(true, null, true));
        yield 'page view (comments, server list, editor link)' => new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', PageFixtures::pageView(false, null, false, false, '/edit?comments=1'));
        yield 'page view (comments, server list)' => new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', PageFixtures::pageView(false, null, false, false));
        yield 'page view (comments closed)' => new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', PageFixtures::pageView(false, null, false));
        yield 'page edit (comments)' => new PageFixture('@NowoWikiBundle/manage/page_edit.html.twig', PageFixtures::pageForm(true, null, true));
        yield 'history (timeline)' => new PageFixture('@NowoWikiBundle/manage/page_history.html.twig', PageFixtures::history(false, null, true));
        yield 'page edit' => new PageFixture('@NowoWikiBundle/manage/page_edit.html.twig', PageFixtures::pageForm());
        yield 'page new' => new PageFixture('@NowoWikiBundle/manage/page_edit.html.twig', PageFixtures::pageForm(false));
        yield 'restore confirmation' => new PageFixture('@NowoWikiBundle/manage/page_restore_conflict.html.twig', PageFixtures::restoreConflict());
        yield 'page edit (save conflict)' => new PageFixture('@NowoWikiBundle/manage/page_edit.html.twig', ['collab_conflict' => ['form_id' => 'wiki-page-form-conflict', 'replace_name' => '_nowo_wiki_collab_replace', 'live_url' => '/tools/wiki/spaces/engineering/pages/deploy/edit']] + PageFixtures::pageForm());
        yield 'history' => new PageFixture('@NowoWikiBundle/manage/page_history.html.twig', PageFixtures::history());
        yield 'history (empty)' => new PageFixture('@NowoWikiBundle/manage/page_history.html.twig', PageFixtures::history(true));
        yield 'diff' => new PageFixture('@NowoWikiBundle/manage/page_diff.html.twig', PageFixtures::diff());
        yield 'diff (same content)' => new PageFixture('@NowoWikiBundle/manage/page_diff.html.twig', PageFixtures::diff(true));
        yield 'search' => new PageFixture('@NowoWikiBundle/manage/search.html.twig', PageFixtures::search());
        yield 'search (no query)' => new PageFixture('@NowoWikiBundle/manage/search.html.twig', PageFixtures::search('', false));
        yield 'search (no hit)' => new PageFixture('@NowoWikiBundle/manage/search.html.twig', PageFixtures::search('nothing'));
        yield 'ai ask (answered)' => new PageFixture('@NowoWikiBundle/manage/ai_ask.html.twig', PageFixtures::aiAsk());
        yield 'ai ask (error)' => new PageFixture('@NowoWikiBundle/manage/ai_ask.html.twig', PageFixtures::aiAsk(false, 'wiki.ai.unavailable'));
        // 1.29 layout options (block A): everything on.
        yield 'page view (layout 1.29)' => new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', self::layoutContext(PageFixtures::pageView(true, self::compactFactory(), false)));
        yield 'page view (layout 1.29, reader, no session)' => new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', self::layoutContext(PageFixtures::pageView(false, self::compactFactory(), true, false), true));
        yield 'space (tree rail)' => new PageFixture('@NowoWikiBundle/manage/space.html.twig', self::layoutContext(PageFixtures::spaceHome()));
        yield 'page edit (compact header)' => new PageFixture('@NowoWikiBundle/manage/page_edit.html.twig', self::layoutContext(PageFixtures::pageForm(true, null, true)));
        // 1.30 layout options: everything on.
        yield 'page view (layout 1.30)' => new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', self::layout130(PageFixtures::pageView(true, self::compactFactory(), false)));
        yield 'page view (layout 1.30, toolbar in the header)' => new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', self::layout130(PageFixtures::pageView(true, self::compactFactory(), false), 'header'));
        yield 'page edit (tabs toolbar)' => new PageFixture('@NowoWikiBundle/manage/page_edit.html.twig', self::layout130(PageFixtures::pageForm(true, null, true)));
        yield 'history (tabs toolbar)' => new PageFixture('@NowoWikiBundle/manage/page_history.html.twig', self::historyTabs());
    }

    /**
     * 1.30: every layout option on (tree hidden mode, reading column at the start, title in the body, tabs in the
     * toolbar, toolbar in the content card unless $position is header, a host toolbar slot), on top of 1.29's.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private static function layout130(array $context, string $position = 'card'): array
    {
        $context                               = self::layoutContext($context);
        $context['nowo_wiki_web_ui']['layout'] = ['tree_collapsed_mode' => 'hidden', 'reading_align' => 'start', 'title' => 'body', 'tabs' => 'toolbar', 'toolbar_position' => $position] + $context['nowo_wiki_web_ui']['layout'];
        $context['nowo_wiki_web_ui']['slots']  = ['page_header_toolbar' => 'slots/page_header_toolbar.html.twig'];
        $context['title_in_body']              = true;
        $context['tree_rail']['collapsed']     = true;
        // 1.30 items 7-8: the path (a hidden ancestor, folded) and the sub-pages.
        $context['page_path']     = self::path();
        $context['page_children'] = ['mode' => 'auto', 'items' => [['label' => 'Alpha <b>', 'href' => '/p/alpha'], ['label' => 'Bravo', 'href' => '/p/bravo']]];

        return $context;
    }

    /**
     * 1.30: a path of five levels with a hidden ancestor (WikiManageController::pagePath()).
     *
     * @return list<array{label: string|null, href: string|null}>
     */
    private static function path(): array
    {
        return [
            ['label' => 'Engineering', 'href' => '/s/eng'],
            ['label' => 'Operations', 'href' => '/p/ops'],
            ['label' => null, 'href' => null],
            ['label' => 'Deep & "dark"', 'href' => '/p/deep'],
            ['label' => 'Parent', 'href' => '/p/parent'],
        ];
    }

    /**
     * 1.30: the history with the tabs as header buttons (web_ui.page_header.tabs: toolbar, the controller's history_tabs).
     *
     * @return array<string, mixed>
     */
    private static function historyTabs(bool $edit = true): array
    {
        $context                 = PageFixtures::history();
        $space                   = PageFixtures::space('engineering', 'Engineering');
        [, $page]                = PageFixtures::pages($space);
        $context['history_tabs'] = PageFixtures::factory()->pageTabs($space, $page, ['edit' => $edit, 'history' => true], 'history');

        return $context;
    }

    /**
     * 1.29: a page context with every layout option on (web_ui.tree_rail, narrow_tree_position, reading_width,
     * page_header compact + dedupe) and, when it has comments, the lg dock.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private static function layoutContext(array $context, bool $noSession = false): array
    {
        $context['nowo_wiki_web_ui'] = ['layout' => ['tree_rail' => true, 'narrow_tree_position' => 'after', 'reading_width' => 'readable', 'page_header' => 'compact', 'title' => 'dedupe']];
        $context['tree_rail']        = [
            'id'          => 'nowo-wiki-tree',
            'body_id'     => 'nowo-wiki-tree-body',
            'collapsed'   => $noSession,
            'href'        => '/p?tree=' . ($noSession ? 'full' : 'rail'),
            'state_url'   => $noSession ? null : '/s/panel-state',
            'state_token' => $noSession ? null : 'state-token',
            'state_panel' => 'tree_rail',
            'persist'     => $noSession ? 'wiki-tree' : null,
        ];
        if (isset($context['comments']) && is_array($context['comments'])) {
            $context['comments'] = ['drawer_below' => 'lg', 'persist' => $noSession ? 'wiki-comments' : null, 'open_requested' => $noSession] + $context['comments'];
        }
        if (isset($context['comments_view']) && is_array($context['comments_view'])) {
            $context['comments_view']['dedupe_title'] = 'Deploy "<runbook>"';
        }

        return $context;
    }

    /**
     * Renders a page with the real UiKit `page_header` partial (the stub host's override delegates to it), for the
     * header markup the stub does not print (toolbar, tabs_inline).
     *
     * @param array<string, mixed> $context
     */
    private function renderWithKitHeader(array $context, string $template): string
    {
        $twig = $this->createTwig();
        $twig->setLoader(new ChainLoader([new ArrayLoader([StubHost::PARTIAL_TEMPLATE => "{{ include('@NowoUiKitBundle/partials/_page_header.html.twig') }}"]), $twig->getLoader()]));

        return $twig->render($template, $context);
    }

    private static function compactFactory(): WikiPageFactory
    {
        return new WikiPageFactory(PageFixtures::urlGenerator(), PageFixtures::routes(), new WikiPages(), null, WikiPageFactory::TAB_ICONS, false, true);
    }

    /**
     * 1.29 (block A): the defaults keep the 1.28 page (no rail, no reading column, the stacked header and tab row, the
     * md dock, no head script); the only default changes are the rendered-content marker of the body and the UiKit
     * scroll-region script. Each option then shows its markup, escaped.
     */
    public function testTheLayoutOptions(): void
    {
        $default = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', PageFixtures::pageView(true, null, false)))->html;
        foreach (['data-nowo-ui-rail-host', 'nowo-ui-rail.js', 'nowo-wiki-reading', 'nowo-ui-readable', 'nowo-wiki-layout--tree-after', 'nowo-ui-layout-state.js', 'drawer-lg', 'data-drawer-below', 'nowo-ui-page-header--tabs-inline', 'data-nowo-ui-side-panel-persist', 'data-nowo-wiki-comments-requested', 'data-nowo-wiki-dedupe-title'] as $absent) {
            self::assertStringNotContainsString($absent, $default, $absent);
        }
        self::assertStringContainsString('data-testid="wiki-tabs"', $default);
        self::assertStringContainsString('data-testid="wiki-content" data-nowo-ui-rendered-content', $default);
        self::assertStringContainsString('js/nowo-ui-scroll-region.js', $default);
        self::assertStringNotContainsString('data-nowo-ui-rail-host', $this->renderPage(new PageFixture('@NowoWikiBundle/manage/space.html.twig', PageFixtures::spaceHome()))->html);
        // MA-L1: every table of the sanitized body sits in a labelled scroll box and keeps its table element.
        $tables                 = PageFixtures::pageView();
        $tables['content_html'] = '<table><tr><td onclick="x()">1</td></tr></table>';
        $wrapped                = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', $tables))->html;
        self::assertStringContainsString('<div class="nowo-wiki-table-scroll" data-nowo-ui-scroll-region data-nowo-ui-scroll-label="Table"><table>', $wrapped);
        self::assertStringNotContainsString('onclick', $wrapped);

        $html = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', self::layoutContext(PageFixtures::pageView(true, self::compactFactory(), false))))->html;
        // Tree rail: the card is the rail host, the toggle names and controls the body; no-JS link; host store.
        self::assertMatchesRegularExpression('#<aside class="[^"]*nowo-wiki-sidebar[^"]*" id="nowo-wiki-tree" data-nowo-ui-rail-host data-nowo-ui-rail="expanded" data-wiki-sidebar#', $html);
        self::assertMatchesRegularExpression('#<a href="/p\?tree=rail" role="button" class="[^"]*" aria-controls="nowo-wiki-tree-body" aria-expanded="true" data-nowo-ui-rail-toggle#', $html);
        self::assertStringContainsString('data-nowo-ui-rail-state-url="/s/panel-state" data-nowo-ui-rail-state-token="state-token" data-nowo-ui-rail-state-panel="tree_rail"', $html);
        self::assertStringContainsString('data-testid="wiki-tree-rail-toggle"', $html);
        self::assertMatchesRegularExpression('#<nav class="[^"]*nowo-wiki-sidebar__body" id="nowo-wiki-tree-body" data-nowo-ui-rail-body aria-label="Pages">#', $html);
        self::assertStringContainsString('Collapse Pages', $html);
        self::assertStringContainsString('js/nowo-ui-rail.js', $html);
        // Narrow tree after the page, reading column.
        self::assertStringContainsString('class="nowo-wiki-layout nowo-wiki-layout--tree-after"', $html);
        self::assertStringContainsString('class="nowo-wiki-layout__main nowo-wiki-reading nowo-ui-readable"', $html);
        // Compact header: no separate tab row; the tabs go to the header partial (the stub host override prints them).
        self::assertStringNotContainsString('data-testid="wiki-tabs"', $html);
        self::assertStringContainsString('data-testid="wiki-tab-edit"', $html);
        // With the real UiKit page_header partial: tabs and actions on one row, the comments toggle at its end.
        $real = $this->renderWithKitHeader(self::layoutContext(PageFixtures::pageView(true, self::compactFactory(), false)), '@NowoWikiBundle/manage/page_view.html.twig');
        self::assertStringContainsString('nowo-ui-page-header--tabs-inline', $real);
        self::assertSame(1, preg_match('#nowo-ui-page-header__bar.*?data-testid="wiki-tab-edit".*?data-testid="wiki-comments-toggle"#s', $real));
        self::assertSame(1, substr_count($real, 'data-testid="wiki-comments-toggle"'));
        self::assertStringNotContainsString('data-testid="wiki-tabs"', $real);
        // The read view's actions use the split layout ("more" menu).
        self::assertStringContainsString('data-nowo-ui-header-actions', $real);
        // Archive closes the "more" menu as a danger item with its own verb (1.29, Beacon Q2).
        self::assertSame(1, preg_match('#data-nowo-ui-header-more.*?class="(?=[^"]*nowo-ui-menu__item--danger)(?=[^"]*nowo-ui-action--archive)[^"]*"#s', $real));
        // The lg dock (container and panel) and the head script that applies it before the first paint.
        self::assertMatchesRegularExpression('#<aside id="nowo-wiki-comments" class="[^"]*nowo-ui-side-panel--drawer-lg#', $html);
        self::assertStringContainsString('data-drawer-below="lg"', $html);
        self::assertStringContainsString('nowo-ui-side-panel-container--drawer-lg', $html);
        self::assertSame(1, preg_match('#data-nowo-ui-contract-block="nowo_ui_styles">(?:(?!data-nowo-ui-contract-block).)*js/nowo-ui-layout-state\.js#s', $html), 'The layout-state script is in the head (nowo_ui_styles).');
        self::assertStringNotContainsString('data-nowo-wiki-comments-requested', $html);
        // The dedupe hint is attribute-escaped.
        self::assertStringContainsString('data-nowo-wiki-dedupe-title="Deploy &quot;&lt;runbook&gt;&quot;"', $html);

        // A reader without a session: localStorage for the rail and the panel, the panel asked for in the URL.
        $reader = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', self::layoutContext(PageFixtures::pageView(false, self::compactFactory(), true, false), true)))->html;
        self::assertStringContainsString('data-nowo-ui-rail="collapsed"', $reader);
        self::assertStringContainsString('aria-expanded="false" data-nowo-ui-rail-toggle', $reader);
        self::assertStringContainsString('data-nowo-ui-rail-persist="wiki-tree"', $reader);
        self::assertStringNotContainsString('data-nowo-ui-rail-state-url', $reader);
        self::assertStringContainsString('data-nowo-ui-side-panel-persist="wiki-comments"', $reader);
        self::assertStringContainsString('data-nowo-wiki-comments-requested', $reader);
        self::assertStringContainsString('Expand Pages', $reader);

        // An escaped rail link (a request URI the server echoes).
        $context              = self::layoutContext(PageFixtures::pageView());
        $context['tree_rail'] = ['href' => '/p"><script>alert(1)</script>?tree=rail'] + $context['tree_rail'];
        $escaped              = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', $context))->html;
        self::assertStringNotContainsString('<script>alert(1)</script>', $escaped);

        // Space home with the rail; the edit form with the compact header and the lg dock.
        $space = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/space.html.twig', self::layoutContext(PageFixtures::spaceHome(), true)))->html;
        self::assertStringContainsString('data-nowo-ui-rail-host data-nowo-ui-rail="collapsed"', $space);
        self::assertStringContainsString('js/nowo-ui-rail.js', $space);
        self::assertSame(1, preg_match('#data-nowo-ui-contract-block="nowo_ui_styles">(?:(?!data-nowo-ui-contract-block).)*js/nowo-ui-layout-state\.js#s', $space));
        $edit = $this->renderWithKitHeader(self::layoutContext(PageFixtures::pageForm(true, null, true)), '@NowoWikiBundle/manage/page_edit.html.twig');
        self::assertStringContainsString('nowo-ui-page-header--tabs-inline', $edit);
        self::assertStringNotContainsString('data-testid="wiki-tabs"', $edit);
        self::assertSame(1, substr_count($edit, 'data-testid="wiki-comments-toggle"'));
        self::assertStringContainsString('data-drawer-below="lg"', $edit);
        self::assertStringContainsString('js/nowo-ui-layout-state.js', $edit);

        // Compact header without comments: no toggle, the tabs still inline.
        $plain = $this->renderWithKitHeader(self::layoutContext(PageFixtures::pageView(true, self::compactFactory())), '@NowoWikiBundle/manage/page_view.html.twig');
        self::assertStringContainsString('nowo-ui-page-header--tabs-inline', $plain);
        self::assertStringNotContainsString('wiki-comments-toggle', $plain);
        self::assertStringNotContainsString('nowo-ui-layout-state.js', $plain);
    }

    /**
     * 1.30 host toolbar slot: the block wiki_page_header_toolbar (or web_ui.slots.page_header_toolbar) is printed in
     * the toolbar of the compact header before the comments toggle, at the end of the stacked tab row, and on the edit
     * form; nothing without it (the 1.29 markup).
     */
    public function testTheHostToolbarSlot(): void
    {
        $default = $this->renderWithKitHeader(self::layoutContext(PageFixtures::pageView(true, self::compactFactory(), false)), '@NowoWikiBundle/manage/page_view.html.twig');
        self::assertStringNotContainsString('host-toolbar-button', $default);
        self::assertStringNotContainsString('host-block-button', $default);

        // Compact header, config slot: in the toolbar after the split actions, before the comments toggle.
        $context                              = self::layoutContext(PageFixtures::pageView(true, self::compactFactory(), false));
        $context['nowo_wiki_web_ui']['slots'] = ['page_header_toolbar' => 'slots/page_header_toolbar.html.twig'];
        $html                                 = $this->renderWithKitHeader($context, '@NowoWikiBundle/manage/page_view.html.twig');
        self::assertSame(1, substr_count($html, 'data-testid="host-toolbar-button">Copy NowoWikiBundle</button>'));
        self::assertSame(1, preg_match('#nowo-ui-page-header__bar.*?data-nowo-ui-header-actions.*?data-testid="host-toolbar-button".*?data-testid="wiki-comments-toggle"#s', $html));

        // A host template that extends the page view: the block, with the page variables, and parent() = the slot.
        $html = $this->renderWithKitHeader($context, 'host/page_view_toolbar.html.twig');
        self::assertSame(1, preg_match('#data-testid="host-block-button">Copy</button><button type="button" data-testid="host-toolbar-button">#', $html));

        // Stacked header: at the end of the tab row, before the comments toggle.
        $stacked = PageFixtures::pageView(true, null, false) + ['nowo_wiki_web_ui' => ['slots' => ['page_header_toolbar' => 'slots/page_header_toolbar.html.twig']]];
        $html    = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', $stacked))->html;
        self::assertSame(1, preg_match('#data-testid="wiki-tabs".*?nowo-wiki-tabs__end.*?data-testid="host-toolbar-button".*?data-testid="wiki-comments-toggle"#s', $html));
        // Without comments the end of the row holds the host buttons alone.
        $html = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', PageFixtures::pageView() + ['nowo_wiki_web_ui' => ['slots' => ['page_header_toolbar' => 'slots/page_header_toolbar.html.twig']]]))->html;
        self::assertSame(1, preg_match('#nowo-wiki-tabs__end">\s*<button type="button" data-testid="host-toolbar-button">#', $html));

        // Edit form, compact and stacked.
        $edit = $this->renderWithKitHeader(self::layoutContext(PageFixtures::pageForm(true, null, true)), 'host/page_edit_toolbar.html.twig');
        self::assertSame(1, preg_match('#nowo-ui-page-header__bar.*?data-testid="host-block-button">Edit help</button>.*?data-testid="wiki-comments-toggle"#s', $edit));
        $edit = $this->renderPage(new PageFixture('host/page_edit_toolbar.html.twig', PageFixtures::pageForm(true, null, true)))->html;
        self::assertSame(1, preg_match('#nowo-wiki-tabs__end">.*?data-testid="host-block-button"#s', $edit));
        // A new page has no tabs, so no toolbar slot.
        $new = $this->renderPage(new PageFixture('host/page_edit_toolbar.html.twig', PageFixtures::pageForm(false)))->html;
        self::assertStringNotContainsString('host-block-button', $new);
    }

    /**
     * 1.30 layout options: tree_collapsed_mode hidden (expand button in the toolbar, rail host CSS hook),
     * reading_align start, title body (UiKit compact presentation of the header), tabs toolbar (icon buttons with
     * labels, aria-current, no tab row) and toolbar_position card (the toolbar in the content frame, out of the header).
     */
    public function testTheLayout130Options(): void
    {
        // Toolbar in the header: one row, no tabs, the order tree / Edit / History / host / comments / actions.
        $header = $this->renderWithKitHeader(self::layout130(PageFixtures::pageView(true, self::compactFactory(), false), 'header'), '@NowoWikiBundle/manage/page_view.html.twig');
        self::assertStringNotContainsString('data-testid="wiki-tab-edit"', $header);
        self::assertStringNotContainsString('data-testid="wiki-tabs"', $header);
        self::assertStringNotContainsString('wiki-toolbar-back', $header);
        self::assertSame(1, preg_match('#data-testid="wiki-tree-toggle".*?data-testid="wiki-toolbar-edit".*?data-testid="wiki-toolbar-history".*?data-testid="host-toolbar-button".*?data-testid="wiki-comments-toggle".*?data-nowo-ui-header-actions#s', $header));
        // Icon buttons named by their (translated) label; the tree toggle controls the tree body, without the vertical title.
        self::assertSame(1, preg_match('#<a [^>]*href="/nowo_wiki_page_edit\?spaceSlug=engineering&amp;pageSlug=deploy"[^>]*data-testid="wiki-toolbar-edit"[^>]*aria-label="Edit"#', $header));
        self::assertSame(1, preg_match('#data-testid="wiki-toolbar-history"[^>]*aria-label="History"#', $header));
        self::assertSame(1, preg_match('#class="[^"]*nowo-wiki-tree-toggle[^"]*" aria-controls="nowo-wiki-tree-body" aria-expanded="false"[^>]*data-testid="wiki-tree-toggle"#', $header));
        self::assertSame(1, substr_count($header, 'nowo-ui-rail-toggle__title'), 'Only the card toggle shows the vertical title.');
        // Title in the body: the UiKit compact presentation (title visually hidden, no subtitle).
        self::assertStringContainsString('nowo-ui-page-header--compact', $header);
        self::assertSame(1, preg_match('#<h1 class="[^"]*visually-hidden">Deploy</h1>#', $header));
        self::assertStringNotContainsString('nowo-ui-page-header__intro', $header);
        // Tree hidden mode and the reading column at the start.
        self::assertStringContainsString('nowo-wiki-layout--tree-hidden', $header);
        self::assertStringContainsString('nowo-wiki-reading nowo-ui-readable nowo-wiki-reading--start', $header);
        self::assertStringNotContainsString('data-nowo-wiki-card-frame', $header);

        // Toolbar in the card: the header keeps nothing but the (hidden) title; the frame holds the toolbar first, then
        // the read-only editor and the server body.
        $card = $this->renderWithKitHeader(self::layout130(PageFixtures::pageView(true, self::compactFactory(), false)), '@NowoWikiBundle/manage/page_view.html.twig');
        self::assertSame(1, preg_match('#data-nowo-ui-region="header"(?:(?!</h1>).)*</h1>\s*</div>\s*</div>#s', $card), 'No toolbar row left in the header.');
        self::assertStringContainsString('data-nowo-ui-page-header-empty', $card);
        self::assertSame(1, preg_match('#<div class="nowo-wiki-card-frame" data-nowo-wiki-card-frame>\s*<div class="nowo-wiki-card-toolbar" data-nowo-wiki-card-toolbar>\s*<div class="nowo-wiki-card-toolbar__bar" role="toolbar" aria-label="Page actions" data-testid="wiki-card-toolbar">.*?data-testid="wiki-tree-toggle".*?data-testid="wiki-toolbar-edit".*?data-testid="wiki-comments-toggle".*?data-nowo-ui-header-actions.*?data-testid="wiki-comments-view".*?data-nowo-wiki-server-body#s', $card));
        self::assertSame(1, substr_count($card, 'data-testid="wiki-comments-toggle"'));
        self::assertSame(1, substr_count($card, 'data-testid="host-toolbar-button"'));
        self::assertSame(1, substr_count($card, 'data-testid="wiki-tree-toggle"'));

        // Card position with the tabs kept (tabs: row): the tabs stay in the header, no tab buttons in the card.
        $context                                        = self::layout130(PageFixtures::pageView(true, self::compactFactory(), false));
        $context['nowo_wiki_web_ui']['layout']['tabs']  = 'row';
        $context['nowo_wiki_web_ui']['layout']['title'] = 'both';
        unset($context['title_in_body']);
        $rowTabs = $this->renderWithKitHeader($context, '@NowoWikiBundle/manage/page_view.html.twig');
        self::assertSame(1, preg_match('#nowo-ui-page-header__bar.*?data-testid="wiki-tab-edit"#s', $rowTabs));
        self::assertStringNotContainsString('wiki-toolbar-edit', $rowTabs);
        self::assertStringContainsString('data-testid="wiki-card-toolbar"', $rowTabs);
        self::assertStringNotContainsString('nowo-ui-page-header--compact', $rowTabs);

        // Card position for a reader without anything to show: no empty toolbar box.
        $reader                               = PageFixtures::pageView(false, self::compactFactory());
        $reader['nowo_wiki_web_ui']['layout'] = ['toolbar_position' => 'card'];
        $html                                 = $this->renderWithKitHeader($reader, '@NowoWikiBundle/manage/page_view.html.twig');
        self::assertStringContainsString('data-nowo-wiki-card-frame', $html);
        self::assertStringNotContainsString('data-nowo-wiki-card-toolbar', $html);

        // Stacked header with the tree hidden mode: the expand button at the end of the tab row.
        $stacked                     = PageFixtures::pageView(true, null, false);
        $stacked['nowo_wiki_web_ui'] = ['layout' => ['tree_collapsed_mode' => 'hidden']];
        $stacked['tree_rail']        = self::layoutContext([])['tree_rail'];
        $html                        = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', $stacked))->html;
        self::assertSame(1, preg_match('#nowo-wiki-tabs__end">.*?data-testid="wiki-tree-toggle".*?data-testid="wiki-comments-toggle"#s', $html));
        self::assertStringContainsString('nowo-wiki-layout--tree-hidden', $html);
        // The hidden mode without the tree rail does nothing.
        unset($stacked['tree_rail']);
        $html = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', $stacked))->html;
        self::assertStringNotContainsString('wiki-tree-toggle', $html);
        self::assertStringNotContainsString('nowo-wiki-layout--tree-hidden', $html);
        // reading_align start without reading_width readable: nothing.
        $html = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', ['nowo_wiki_web_ui' => ['layout' => ['reading_align' => 'start']]] + PageFixtures::pageView()))->html;
        self::assertStringNotContainsString('nowo-wiki-reading--start', $html);

        // Title in the body with the stacked header: the UiKit compact presentation too.
        $html = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', ['title_in_body' => true] + PageFixtures::pageView()))->html;
        self::assertStringContainsString('data-testid="wiki-tabs"', $html);

        // Edit form with the tabs in the toolbar: Back to page, Edit current (aria-current), History.
        $edit = $this->renderWithKitHeader(self::layout130(PageFixtures::pageForm(true, null, true)), '@NowoWikiBundle/manage/page_edit.html.twig');
        self::assertStringNotContainsString('data-testid="wiki-tab-page"', $edit);
        self::assertSame(1, preg_match('#data-testid="wiki-toolbar-back"[^>]*aria-label="Back to the page".*?data-testid="wiki-toolbar-edit" aria-current="page".*?data-testid="wiki-toolbar-history".*?data-testid="host-toolbar-button".*?data-testid="wiki-comments-toggle"#s', $edit));

        // History with the tabs as header buttons.
        $history = $this->renderWithKitHeader(self::historyTabs(), '@NowoWikiBundle/manage/page_history.html.twig');
        self::assertSame(1, preg_match('#data-testid="wiki-toolbar-back".*?data-testid="wiki-toolbar-edit".*?data-testid="wiki-toolbar-history" aria-current="page"#s', $history));
        $reader = $this->renderWithKitHeader(self::historyTabs(false), '@NowoWikiBundle/manage/page_history.html.twig');
        self::assertStringNotContainsString('wiki-toolbar-edit', $reader);
        self::assertStringNotContainsString('wiki-toolbar-back', $this->renderWithKitHeader(PageFixtures::history(), '@NowoWikiBundle/manage/page_history.html.twig'));
    }

    /**
     * 1.30 page_header.path: card and web_ui.children: the path above the body (links, the middle levels folded into
     * the "…" menu, a hidden ancestor without any name), the sub-pages chip and the "Pages in this section" card;
     * nothing without them.
     */
    public function testThePathAndTheSubPages(): void
    {
        $default = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', PageFixtures::pageView()))->html;
        foreach (['wiki-page-head', 'wiki-page-path', 'wiki-children'] as $absent) {
            self::assertStringNotContainsString($absent, $default);
        }

        $context = PageFixtures::pageView() + [
            'page_path'     => self::path(),
            'page_children' => ['mode' => 'always', 'items' => [['label' => 'Alpha <b>', 'href' => '/p/alpha'], ['label' => 'Bravo', 'href' => '/p/bravo']]],
        ];
        $html = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', $context))->html;

        self::assertSame(1, preg_match('#<div class="nowo-wiki-page-head" data-testid="wiki-page-head">\s*<nav class="nowo-wiki-path" aria-label="Page path" data-testid="wiki-page-path">(.*?)</nav>#s', $html, $nav));
        // First and last level visible, the three in between in the "…" disclosure, in order.
        self::assertSame(1, preg_match('#<a class="nowo-wiki-path__link" href="/s/eng">Engineering</a>.*?<details class="nowo-wiki-path__menu" data-testid="wiki-page-path-more">\s*<summary class="nowo-wiki-path__more" aria-label="Show 3 more levels"><span aria-hidden="true">…</span></summary>.*?href="/p/ops">Operations</a>.*?nowo-wiki-path__hidden.*?href="/p/deep">Deep &amp; &quot;dark&quot;</a>.*?</details>.*?<li class="nowo-wiki-path__item nowo-wiki-path__item--parent"><a class="nowo-wiki-path__link" href="/p/parent">Parent</a></li>#s', $nav[1]));
        // The hidden ancestor: "…" and a generic text, no link, no title, no name in any attribute.
        self::assertStringContainsString('<span class="nowo-wiki-path__hidden"><span aria-hidden="true">…</span><span class="visually-hidden">Restricted page</span></span>', $nav[1]);
        self::assertSame(4, substr_count($nav[1], '<a '));
        self::assertStringNotContainsString('title=', $nav[1]);

        // Three levels or fewer: no menu.
        $short                  = $context;
        $short['page_path']     = [['label' => 'Engineering', 'href' => '/s/eng'], ['label' => 'Parent', 'href' => '/p/parent']];
        $short['page_children'] = null;
        $flat                   = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', $short))->html;
        self::assertStringNotContainsString('wiki-page-path-more', $flat);
        self::assertSame(1, preg_match('#<li class="nowo-wiki-path__item"><a class="nowo-wiki-path__link" href="/s/eng">Engineering</a></li>\s*<li class="nowo-wiki-path__item nowo-wiki-path__item--parent">#', $flat));
        self::assertStringNotContainsString('wiki-children', $flat);
        // A space without a link prints its name as text.
        $short['page_path'] = [['label' => 'Engineering', 'href' => null]];
        self::assertStringContainsString('<span class="nowo-wiki-path__text">Engineering</span>', $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', $short))->html);

        // The chip (count, escaped list) and the section after the body, in tree order.
        self::assertSame(1, preg_match('#<details class="nowo-wiki-children-chip" data-testid="wiki-children-chip">\s*<summary class="[^"]*nowo-wiki-children-chip__toggle">2 subpages</summary>.*?href="/p/alpha">Alpha &lt;b&gt;</a>#s', $html));
        self::assertSame(1, preg_match('#data-testid="wiki-content".*?<section class="[^"]*nowo-wiki-children" aria-labelledby="nowo-wiki-children-title" data-testid="wiki-children"[^>]*>.*?Pages in this section.*?<span class="nowo-wiki-children__label">Alpha &lt;b&gt;</span>.*?<span class="nowo-wiki-children__label">Bravo</span>#s', $html));
        self::assertStringNotContainsString('nowo-wiki-children--auto', $html);

        // auto: the CSS hook on both; one sub-page: singular; none: neither the chip nor the card (nor an empty head).
        $auto                  = $context;
        $auto['page_path']     = null;
        $auto['page_children'] = ['mode' => 'auto', 'items' => [['label' => 'Alpha', 'href' => '/p/alpha']]];
        $html                  = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', $auto))->html;
        self::assertSame(2, substr_count($html, 'nowo-wiki-children--auto'));
        self::assertStringContainsString('>1 subpage</summary>', $html);
        $auto['page_children'] = ['mode' => 'always', 'items' => []];
        $html                  = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', $auto))->html;
        self::assertStringNotContainsString('wiki-page-head', $html);
        self::assertStringNotContainsString('wiki-children', $html);

        // With the card toolbar the head sits in the frame, after the toolbar and before the body.
        $card = $this->renderWithKitHeader(self::layout130(PageFixtures::pageView(true, self::compactFactory(), false)), '@NowoWikiBundle/manage/page_view.html.twig');
        self::assertSame(1, preg_match('#data-nowo-wiki-card-frame>.*?data-testid="wiki-card-toolbar".*?data-testid="wiki-page-head".*?data-testid="wiki-page-path".*?data-testid="wiki-children-chip".*?data-testid="wiki-comments-view"#s', $card));
    }

    public function testPageTreeNestsListsAndMarksTheCurrentPage(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', PageFixtures::pageView()))->html;

        // The tree's current page and (1.16) the current tab of the tab row.
        self::assertSame(2, substr_count($html, 'aria-current="page"'));
        self::assertSame(1, substr_count($html, 'nowo-wiki-tree__link--current" aria-current="page"'));
        self::assertMatchesRegularExpression('#<a href="/nowo_wiki_page_view\?spaceSlug=engineering&amp;pageSlug=deploy" class="nowo-wiki-tree__link nowo-wiki-tree__link--current" aria-current="page">Deploy</a>#', $html);
        self::assertStringContainsString('nowo-wiki-tree__item--depth-2', $html);
        self::assertStringContainsString('nowo-wiki-tree--nested', $html);
        self::assertStringContainsString('Ticket #42', $html);
    }

    /** 1.20 (page_files): the "Files" card lists the files of the page, escaped, links with rel="noopener noreferrer". */
    public function testPageViewListsThePageFiles(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', PageFixtures::pageView()))->html;

        self::assertStringContainsString('aria-labelledby="nowo-wiki-files-title" data-testid="wiki-page-files"', $html);
        self::assertStringContainsString('id="nowo-wiki-files-title">Files</h2>', $html);
        self::assertStringContainsString('<a href="/files/q3?a=1&amp;b=2" class="nowo-wiki-files__link" rel="noopener noreferrer">Q3 &lt;report&gt;.pdf</a>', $html);
        self::assertStringContainsString('File · 1.2 MB', $html);
        // An image without alt text shows its URL.
        self::assertStringContainsString('rel="noopener noreferrer">/files/flow.png</a>', $html);
        self::assertStringContainsString('data-kind="image"', $html);

        $readOnly = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', PageFixtures::pageView(false)))->html;
        self::assertStringNotContainsString('wiki-page-files', $readOnly);
    }

    public function testPageViewSanitizesTheContent(): void
    {
        $context                 = PageFixtures::pageView();
        $context['content_html'] = '<p>Safe</p><script>alert(1)</script><img src="x" onerror="alert(1)">';

        $html = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', $context))->html;

        self::assertStringContainsString('<p>Safe</p>', $html);
        self::assertStringNotContainsString('alert(1)', $html);
    }

    public function testIndexRendersTheAiQuestionInANativeDialog(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/index.html.twig', PageFixtures::index()))->html;

        self::assertMatchesRegularExpression('#<dialog id="nowo-wiki-ai-dialog"[^>]*data-nowo-ui-dialog#', $html);
        self::assertStringContainsString('data-nowo-ui-dialog-open="nowo-wiki-ai-dialog"', $html);
        self::assertStringContainsString('form="nowo-wiki-ai-dialog-form"', $html);
        self::assertStringContainsString('autofocus', $html);
        self::assertStringNotContainsString('data-bs-toggle', $html);

        // 1.18 (UiKit 2.29 dialog sizes): the medium size (no size modifier), the form in the scrolling body, the footer
        // outside it with Cancel then the one primary (last), bound to the form by form="…".
        self::assertMatchesRegularExpression('#<dialog id="nowo-wiki-ai-dialog" class="nowo-ui-dialog(?: pc-dialog)?"[ >]#', $html);
        self::assertDoesNotMatchRegularExpression('#nowo-ui-dialog--(?:sm|lg|xl|wide)#', $html);
        self::assertSame(1, preg_match('#<dialog id="nowo-wiki-ai-dialog".*?</dialog>#s', $html, $dialog));
        self::assertSame(1, preg_match('#<div class="[^"]*nowo-ui-dialog__body[^"]*"[^>]*>(.*?)</div>\s*<footer class="[^"]*nowo-ui-dialog__footer[^"]*"[^>]*>(.*?)</footer>\s*</dialog>#s', $dialog[0], $parts));
        self::assertStringContainsString('id="nowo-wiki-ai-dialog-form"', $parts[1]);
        self::assertStringNotContainsString('<button', $parts[1]);
        self::assertSame(2, substr_count($parts[2], '<button'));
        self::assertSame(1, substr_count($parts[2], 'nowo-ui-btn--primary'));
        self::assertMatchesRegularExpression('#data-nowo-ui-dialog-close>Cancel</button>\s*<button type="submit" class="[^"]*nowo-ui-btn--primary[^"]*" form="nowo-wiki-ai-dialog-form"[^>]*>[^<]+</button>\s*$#', $parts[2]);
    }

    public function testThePageTabsCarryTheirIconsForTheHostTabStyle(): void
    {
        $context = PageFixtures::pageView();
        $twig    = $this->createTwig();
        // UiKit draws a `tabler:*` icon with the ux_icon or tabler-icons set (else the label alone, as before 1.18).
        $twig->addGlobal('nowo_ui_kit_icon_set', 'tabler-icons');
        $html = $twig->render('@NowoWikiBundle/manage/_page_tabs.html.twig', ['tabs' => $context['page']->header->tabs, 'comments' => null, 'bundle' => 'NowoWikiBundle']);

        self::assertSame(3, substr_count($html, 'nowo-ui-tabs__icon'));
        self::assertSame(3, substr_count($html, 'nowo-ui-tabs__label'));
        foreach (['file-text', 'pencil', 'history'] as $icon) {
            self::assertStringContainsString('ti-' . $icon, $html);
        }
        self::assertStringContainsString('data-rail-label="Page"', $html);

        // The default bootstrap-icons set has no tabler names: the tabs keep their labels only.
        $plain = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', $context))->html;
        self::assertStringContainsString('data-testid="wiki-tab-page"', $plain);
        self::assertStringNotContainsString('nowo-ui-tabs__icon', $plain);
    }

    public function testRestoreAndArchiveGoThroughTheConfirmContract(): void
    {
        $history = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_history.html.twig', PageFixtures::history()))->html;
        $view    = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', PageFixtures::pageView()))->html;

        self::assertStringContainsString('data-nowo-ui-confirm', $history);
        self::assertStringContainsString('name="_token" value="token-wiki_restore_', $history);
        self::assertStringContainsString('data-nowo-ui-confirm', $view);
        self::assertStringContainsString('name="_token" value="token-wiki_archive_', $view);
    }

    public function testConfirmsUseTheUiKitConfirmAnatomy(): void
    {
        // Dialog rule v2 (1.19): the confirms are UiKit's View\Confirm dialogs (UiKit 2.31 gives them the dialog
        // anatomy in CSS): header with the title and ×, body with the message, footer with Cancel then one primary.
        $view = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', PageFixtures::pageView()))->html;

        self::assertSame(1, preg_match('#<dialog\s+id="[^"]+"\s+class="nowo-ui-confirm[^"]*"[^>]*>(?:(?!</dialog>).)*Archive this page\?(?:(?!</dialog>).)*</dialog>#s', $view, $archive));
        self::assertMatchesRegularExpression('#<header class="nowo-ui-confirm__header[^"]*">\s*<h2 [^>]*>Archive this page\?</h2>\s*<button type="button" class="nowo-ui-confirm__close[^"]*" data-nowo-confirm-close#', $archive[0]);
        self::assertMatchesRegularExpression('#<div class="nowo-ui-confirm__footer[^"]*">\s*<button type="button" class="[^"]*nowo-ui-btn--secondary[^"]*" data-nowo-confirm-close>\s*Cancel\s*</button>#', $archive[0]);
        self::assertSame(1, substr_count($archive[0], 'nowo-ui-confirm__submit'));
        self::assertStringNotContainsString('wiki-confirm-dialog', $view, 'no bundle confirm partial (removed before 1.19.0)');
    }

    public function testAHostTemplateReplacingTheContentBlockCanOfferTheWayOut(): void
    {
        $context = ['collab_conflict' => ['form_id' => 'wiki-page-form-conflict', 'replace_name' => '_nowo_wiki_collab_replace', 'live_url' => '/live']] + PageFixtures::pageForm(formAttributes: ['id' => 'wiki-page-form-conflict']);
        $html    = $this->renderPage(new PageFixture('host/page_edit_bare.html.twig', $context))->html;

        self::assertMatchesRegularExpression('#<form[^>]+id="wiki-page-form-conflict"#', $html);
        self::assertMatchesRegularExpression('#<button type="submit" form="wiki-page-form-conflict" name="_nowo_wiki_collab_replace"#', $html);
        self::assertStringContainsString('href="/live"', $html);
    }

    public function testTheRestoreConfirmationHoldsTokenAndField(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_restore_conflict.html.twig', PageFixtures::restoreConflict()))->html;

        self::assertMatchesRegularExpression('#name="_nowo_wiki_collab_replace"[^>]*value="1"|value="1"[^>]*name="_nowo_wiki_collab_replace"#', $html);
        self::assertStringContainsString('name="_token"', $html);
        self::assertStringContainsString('data-testid="wiki-collab-restore-conflict"', $html);
    }

    public function testEditorScriptCarriesTheRequestCspNonce(): void
    {
        $page = new PageFixture('@NowoWikiBundle/manage/page_edit.html.twig', PageFixtures::pageForm());

        self::assertMatchesRegularExpression('#<script src="/assets/tiptap/tiptap-editor.js"></script>#', $this->renderPage($page)->html);

        $this->nonce = 'n0nce';
        self::assertStringContainsString('<script src="/assets/tiptap/tiptap-editor.js" nonce="n0nce"></script>', $this->renderPage($page)->html);
    }

    public function testTheTiptapScriptLoadsOnlyForTheTiptapEditor(): void
    {
        foreach (['milkdown', 'markdown'] as $type) {
            $page = new PageFixture('@NowoWikiBundle/manage/page_edit.html.twig', ['editor_type' => $type] + PageFixtures::pageForm());
            self::assertStringNotContainsString('tiptap-editor.js', $this->renderPage($page)->html, $type);
        }
        $page = new PageFixture('@NowoWikiBundle/manage/page_edit.html.twig', ['editor_type' => 'tiptap'] + PageFixtures::pageForm());
        self::assertStringContainsString('tiptap-editor.js', $this->renderPage($page)->html);
    }

    private ?string $nonce = null;

    protected function configureTwig(Environment $twig): void
    {
        $this->configureWikiTwig($twig);
        // 1.21: the host templates of the web_ui.slots test.
        $twig->setLoader(new ChainLoader([new ArrayLoader([
            'slots/sidebar.html.twig'           => '<aside data-testid="host-sidebar">{{ page.bundle }}</aside>',
            'slots/space_intro.html.twig'       => '<p data-testid="host-intro">{{ page.bundle }}</p>',
            'slots/page_header_extra.html.twig' => '<p data-testid="host-header-extra">{{ page.bundle }}</p>',
            // 1.30: host toolbar buttons (slot and block).
            'slots/page_header_toolbar.html.twig' => '<button type="button" data-testid="host-toolbar-button">Copy {{ page.bundle }}</button>',
            'host/page_view_toolbar.html.twig'    => "{% extends '@NowoWikiBundle/manage/page_view.html.twig' %}{% block wiki_page_header_toolbar %}<button type=\"button\" data-testid=\"host-block-button\">{{ can_edit ? 'Copy' : 'none' }}</button>{{ parent() }}{% endblock %}",
            'host/page_edit_toolbar.html.twig'    => "{% extends '@NowoWikiBundle/manage/page_edit.html.twig' %}{% block wiki_page_header_toolbar %}<button type=\"button\" data-testid=\"host-block-button\">Edit help</button>{% endblock %}",
            // Audit HO-L2: the shape of a host template that replaces the block with no parent().
            'host/page_edit_bare.html.twig' => "{% extends '@NowoWikiBundle/manage/page_edit.html.twig' %}{% block nowo_ui_content %}{% if collab_conflict|default(null) %}{{ include('@NowoWikiBundle/manage/_collab_conflict.html.twig', {collab_conflict: collab_conflict, bundle: page.bundle}, false) }}{% endif %}{{ form_start(page.form, {attr: page.formAttributes}) }}{{ form_end(page.form) }}{% endblock %}",
        ]), $twig->getLoader()]));
        if ($this->nonce === null) {
            return;
        }

        $app     = $twig->getGlobals()['app'];
        $request = Request::create('/');
        $request->attributes->set('csp_nonce', $this->nonce);
        $request->attributes->set('_route', 'nowo_ui_contract_page');
        $request->attributes->set('_route_params', []);
        $stack = new RequestStack();
        $stack->push($request);
        $app->setRequestStack($stack);
    }

    public function testTheTabRowAndTheCommentsPanel(): void
    {
        // 1.16 (contract §1–§2): the tab row always; the toggle and the panel only with a comments panel.
        $plain = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', PageFixtures::pageView()))->html;
        self::assertStringContainsString('data-testid="wiki-tabs"', $plain);
        self::assertStringContainsString('data-testid="wiki-tab-page"', $plain);
        self::assertStringContainsString('data-testid="wiki-tab-edit"', $plain);
        self::assertStringContainsString('data-testid="wiki-tab-history"', $plain);
        self::assertStringNotContainsString('nowo-wiki-comments', $plain);
        self::assertStringNotContainsString('nowo-ui-side-panel.js', $plain);

        $open = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', PageFixtures::pageView(true, null, true)))->html;
        self::assertMatchesRegularExpression('#aria-controls="nowo-wiki-comments" aria-expanded="true"#', $open);
        self::assertStringContainsString('aria-keyshortcuts="Alt+Shift+C"', $open);
        self::assertMatchesRegularExpression('#<aside id="nowo-wiki-comments"[^>]*aria-labelledby="nowo-wiki-comments-title"#', $open);
        self::assertStringContainsString('data-state-url="/s/panel-state"', $open);
        self::assertStringContainsString('data-nowo-wiki-comments-mount', $open);
        self::assertStringContainsString('<strong>this</strong>', $open);
        self::assertStringNotContainsString('<script>alert(1)</script>', $open);
        self::assertStringContainsString('src="/files/maria/s64"', $open);
        self::assertSame(1, substr_count($open, 'nowo-ui-avatar__image'), 'Only the named author has a picture.');
        self::assertStringContainsString('data-nowo-wiki-comments-view', $open);
        self::assertStringContainsString('data-nowo-wiki-server-body', $open);
        self::assertStringContainsString('name="body"', $open);
        self::assertStringContainsString('js/nowo-ui-side-panel.js', $open);

        $closed = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', PageFixtures::pageView(false, null, false)))->html;
        self::assertMatchesRegularExpression('#<aside id="nowo-wiki-comments"[^>]* hidden#', $closed);
        self::assertStringContainsString('aria-expanded="false"', $closed);
        self::assertStringNotContainsString('name="body"', $closed);
        self::assertStringNotContainsString('data-reload-href', $closed);

        // 1.16 default for readers who may not edit: no read-only mount (no page source), the server list is the panel.
        $server = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', PageFixtures::pageView(false, null, false, false)))->html;
        self::assertStringNotContainsString('data-nowo-wiki-comments-view', $server);
        self::assertStringNotContainsString('data-nowo-wiki-comments-mount', $server);
        self::assertStringNotContainsString('data-nowo-wiki-comments-fallback', $server);
        self::assertStringContainsString('data-testid="wiki-comment-quote">make deploy</blockquote>', $server);
        self::assertStringContainsString('data-reload-href="/p?comments=1"', $server);
        // EI-L7: a quote that left the page is not printed; EI-L4: the capped list says so.
        self::assertStringContainsString('data-testid="wiki-comment-quote-gone">The commented text is no longer on the page.</p>', $server);
        self::assertStringContainsString('data-testid="wiki-comments-truncated">Only the first 2 open threads are shown.</p>', $server);
        // EI-I18: the earlier comments of a long thread stay in the editor.
        self::assertStringContainsString('data-testid="wiki-comment-more">3 earlier comment(s) in the editor</p>', $server);
        // A reader who may not edit: the hint is muted plain text.
        self::assertMatchesRegularExpression('#<p class="[^"]*nowo-ui-muted[^"]*nowo-wiki-comment__reply-hint" data-testid="wiki-comment-reply-hint">Reply from the editor</p>#', $server);
        // One who may edit: a link to the editor, opened on the comments panel.
        $editor = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', PageFixtures::pageView(false, null, false, false, '/edit?comments=1')))->html;
        self::assertMatchesRegularExpression('#data-testid="wiki-comment-reply-hint"><a href="/edit\?comments=1">Reply from the editor</a></p>#', $editor);
        self::assertStringContainsString('name="body"', $server);

        $edit = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_edit.html.twig', PageFixtures::pageForm(true, null, true)))->html;
        self::assertMatchesRegularExpression('#data-testid="wiki-tab-edit"[^>]*aria-current="page"|aria-current="page"[^>]*data-testid="wiki-tab-edit"#', $edit);
        self::assertStringContainsString('<aside id="nowo-wiki-comments"', $edit);
        self::assertStringContainsString('wiki.js', $edit);
    }

    /**
     * 1.21 web_ui.assets: every page prints its styles and scripts by default; with the switches off, none of the
     * bundle or the scaffold (a host that bundles the assets, e.g. with Vite).
     */
    public function testTheAssetSwitchesEmptyTheStyleAndScriptBlocks(): void
    {
        foreach (self::pageFixtures() as $name => $fixture) {
            $default = $this->renderPage($fixture)->html;
            self::assertStringContainsString('wiki.css', $default, $name);

            $context                     = $fixture->context;
            $context['nowo_wiki_web_ui'] = ['assets' => ['styles' => false, 'scripts' => false]];
            $html                        = $this->renderPage(new PageFixture($fixture->template, $context))->html;
            self::assertStringNotContainsString('wiki.css', $html, $name);
            self::assertStringNotContainsString('wiki.js', $html, $name);
            self::assertStringNotContainsString('nowo-ui-tree.js', $html, $name);
            self::assertStringNotContainsString('nowo-ui-side-panel.js', $html, $name);
            self::assertStringNotContainsString('tiptap-editor.js', $html, $name);

            // One at a time.
            $context['nowo_wiki_web_ui'] = ['assets' => ['styles' => true, 'scripts' => false]];
            $styles                      = $this->renderPage(new PageFixture($fixture->template, $context))->html;
            self::assertStringContainsString('wiki.css', $styles, $name);
            self::assertStringNotContainsString('wiki.js', $styles, $name);
        }
    }

    /** 1.21 web_ui.slots: host templates in place of the tree card and the search card, and after the page header. */
    public function testTheSlotsDrawTheHostTemplates(): void
    {
        $slots = ['slots' => ['sidebar' => 'slots/sidebar.html.twig', 'space_intro' => 'slots/space_intro.html.twig', 'page_header_extra' => 'slots/page_header_extra.html.twig']];

        $default = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/space.html.twig', PageFixtures::spaceHome()))->html;
        self::assertStringContainsString('data-wiki-sidebar', $default);
        self::assertStringContainsString('data-testid="wiki-space-search"', $default);
        self::assertStringNotContainsString('data-testid="host-', $default);

        $space = PageFixtures::spaceHome();
        $html  = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/space.html.twig', ['nowo_wiki_web_ui' => $slots] + $space))->html;
        self::assertStringContainsString('<aside data-testid="host-sidebar">NowoWikiBundle</aside>', $html);
        self::assertStringContainsString('<p data-testid="host-intro">NowoWikiBundle</p>', $html);
        self::assertStringNotContainsString('data-wiki-sidebar', $html);
        self::assertStringNotContainsString('data-testid="wiki-space-search"', $html);
        // The rest of the page stays.
        self::assertStringContainsString('data-testid="wiki-import"', $html);

        $view = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', ['nowo_wiki_web_ui' => $slots] + PageFixtures::pageView()))->html;
        self::assertStringContainsString('<aside data-testid="host-sidebar">NowoWikiBundle</aside>', $view);
        self::assertStringNotContainsString('data-wiki-sidebar', $view);
        // Between the page header and the tab row.
        self::assertLessThan(strpos($view, 'data-testid="wiki-tabs"'), strpos($view, 'data-testid="host-header-extra"'));
    }

    /** 1.21 web_ui.import_export: dialog: one header action; the exports and the import form move into the dialog. */
    public function testTheImportExportDialog(): void
    {
        $default = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/space.html.twig', PageFixtures::spaceHome()))->html;
        self::assertStringNotContainsString('nowo-wiki-import-export-dialog', $default);
        self::assertStringContainsString('data-testid="wiki-import"', $default);

        $factory                 = new WikiPageFactory(PageFixtures::urlGenerator(), PageFixtures::routes(), new WikiPages(), null, WikiPageFactory::TAB_ICONS, true);
        $context                 = ['nowo_wiki_web_ui' => ['import_export' => 'dialog']] + PageFixtures::spaceHome(true, $factory);
        $context['export_links'] = $factory->exportLinks(PageFixtures::space('engineering', 'Engineering'), ['export' => true]);

        $html = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/space.html.twig', $context))->html;
        self::assertStringContainsString('data-nowo-ui-dialog-open="nowo-wiki-import-export-dialog"', $html);
        self::assertStringNotContainsString('data-testid="wiki-import"', $html);
        self::assertSame(1, preg_match('#<dialog id="nowo-wiki-import-export-dialog".*?</dialog>#s', $html, $dialog));
        self::assertStringContainsString('data-testid="wiki-export-outline"', $dialog[0]);
        self::assertStringContainsString('data-testid="wiki-export-notion"', $dialog[0]);
        self::assertStringContainsString('id="nowo-wiki-import-export-form"', $dialog[0]);
        self::assertStringContainsString('form="nowo-wiki-import-export-form"', $dialog[0]);
        // No export action left in the header beside the dialog.
        self::assertSame(1, substr_count($html, 'data-testid="wiki-export-outline"'));

        // Read only: neither the action nor the dialog.
        $readOnly = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/space.html.twig', ['nowo_wiki_web_ui' => ['import_export' => 'dialog']] + PageFixtures::spaceHome(false, $factory)))->html;
        self::assertStringNotContainsString('nowo-wiki-import-export-dialog', $readOnly);

        // Export only: the links, Cancel, no submit.
        $context['import_form'] = null;
        $export                 = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/space.html.twig', $context))->html;
        self::assertSame(1, preg_match('#<dialog id="nowo-wiki-import-export-dialog".*?</dialog>#s', $export, $dialog));
        self::assertStringContainsString('data-testid="wiki-export-outline"', $dialog[0]);
        self::assertStringNotContainsString('wiki-import-submit', $dialog[0]);
    }

    /** 1.21 comments.error_notices / comments.moderation_panel: nothing unless on; the labels are attribute-escaped. */
    public function testTheCommentsPanelExtras(): void
    {
        $default = PageFixtures::pageView(true, null, true);
        $html    = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', $default))->html;
        self::assertStringNotContainsString('data-nowo-wiki-comments-notice', $html);
        self::assertStringNotContainsString('data-nowo-wiki-comments-toast', $html);
        self::assertStringNotContainsString('data-nowo-wiki-moderation', $html);

        $context             = $default;
        $context['comments'] = ['moderation' => true, 'error_notices' => true] + $default['comments'];
        $html                = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', $context))->html;
        self::assertStringContainsString('data-limit-reached="This page, thread or comment has reached its limit."', $html);
        self::assertStringContainsString('data-rate-limited="Too many comments in a short time: try again in a moment."', $html);
        self::assertSame(1, substr_count($html, 'data-nowo-wiki-comments-toast="error"'));
        self::assertSame(1, substr_count($html, 'data-nowo-wiki-comments-toast="success"'));
        self::assertMatchesRegularExpression('#<details class="nowo-wiki-moderation" data-nowo-wiki-moderation[^>]*data-url="/p/comments"[^>]*data-token="token"#s', $html);
        self::assertStringContainsString('&quot;delete&quot;:&quot;Delete thread&quot;', $html);
        self::assertStringContainsString('<dialog class="nowo-wiki-moderation__confirm"', $html);
    }

    public function testHistoryAvatarsOnlyBesideRealNames(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_history.html.twig', PageFixtures::history(false, null, true)))->html;

        self::assertSame(1, substr_count($html, 'data-testid="wiki-history-avatar"'));
        self::assertStringContainsString('src="/files/maria/s64"', $html);
        self::assertStringContainsString('Editor 3f2a', $html);
    }

    /** 1.26: a revision whose author was deleted reads "Former person", with no picture; the others are unchanged. */
    public function testHistoryLabelsFormerPeople(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_history.html.twig', PageFixtures::history(false, null, true, true)))->html;

        self::assertSame(1, substr_count($html, 'data-testid="wiki-history-former-person"'));
        self::assertMatchesRegularExpression('#data-testid="wiki-history-former-person">Former person</span>#', $html);
        self::assertStringContainsString('Editor 3f2a', $html);
        self::assertStringNotContainsString('data-testid="wiki-history-avatar"', $html);
    }
}
