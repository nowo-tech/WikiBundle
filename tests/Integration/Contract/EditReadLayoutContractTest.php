<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\Contract;

use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;
use Nowo\UiKitBundle\Test\StubHost;
use Nowo\WikiBundle\Tests\Support\PageFixtures;
use Nowo\WikiBundle\Ui\WikiPageFactory;
use Nowo\WikiBundle\Ui\WikiPages;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;

use function preg_match;
use function strpos;
use function substr_count;

/**
 * 1.33 `web_ui.edit_view.layout: read` and `web_ui.page_header.edit_toolbar`: the edit form of a page in the read
 * view's layout (tree aside, reading column, card frame and toolbar, page head with the path, the form in place of the
 * body, block wiki_page_form_body) and the edit toolbar options. The defaults keep the 1.32 form.
 */
final class EditReadLayoutContractTest extends PageContractTestCase
{
    use WikiContractEnvironment;

    private const TEMPLATE = '@NowoWikiBundle/manage/page_edit.html.twig';

    public static function pageFixtures(): iterable
    {
        yield 'page edit (read layout)' => new PageFixture(self::TEMPLATE, self::readLayout());
        yield 'page edit (read layout, comments, rail, path)' => new PageFixture(self::TEMPLATE, self::readLayout(['tree_rail' => true, 'reading_width' => 'readable', 'reading_align' => 'start', 'path' => 'card'], null, true, true));
        yield 'page edit (read layout, card toolbar)' => new PageFixture(self::TEMPLATE, self::readLayout(['toolbar_position' => 'card', 'tabs' => 'toolbar', 'tree_collapsed_mode' => 'hidden', 'path' => 'card'], self::factory(true, ['current_tab' => 'hidden', 'back' => 'view']), true, true));
        yield 'page edit (edit toolbar)' => new PageFixture(self::TEMPLATE, PageFixtures::pageForm(true, self::factory(false, ['current_tab' => 'hidden', 'back' => 'view'])));
        yield 'page edit (read layout, host form body)' => new PageFixture('host/page_edit_form_body.html.twig', self::readLayout());
    }

    /**
     * The read scaffold: the layout, the aside with the tree, the main column, the page head, then the form (the UiKit
     * form scaffold) inside .nowo-wiki-edit-body; the defaults (form layout, no tree) keep the 1.32 markup.
     */
    public function testTheReadLayoutDrawsTheReadScaffoldAroundTheForm(): void
    {
        $default = $this->renderPage(new PageFixture(self::TEMPLATE, PageFixtures::pageForm()))->html;
        foreach (['nowo-wiki-layout', 'nowo-wiki-edit-body', 'data-wiki-sidebar', 'wiki.js'] as $absent) {
            self::assertStringNotContainsString($absent, $default, $absent);
        }
        // The option alone without the controller's tree (a new page, or a host that renders the form itself): the form.
        $noTree                     = PageFixtures::pageForm();
        $noTree['nowo_wiki_web_ui'] = ['layout' => ['edit_layout' => 'read']];
        self::assertStringNotContainsString('nowo-wiki-layout', $this->renderPage(new PageFixture(self::TEMPLATE, $noTree))->html);
        $new                     = PageFixtures::pageForm(false);
        $new['nowo_wiki_web_ui'] = ['layout' => ['edit_layout' => 'read']];
        $new['tree']             = [];
        self::assertStringNotContainsString('nowo-wiki-layout', $this->renderPage(new PageFixture(self::TEMPLATE, $new))->html);

        $html = $this->renderPage(new PageFixture(self::TEMPLATE, self::readLayout()))->html;
        self::assertSame(1, preg_match('#<div class="nowo-wiki-layout">\s*<div class="nowo-wiki-layout__aside">\s*<aside class="[^"]*nowo-wiki-sidebar[^"]*" data-wiki-sidebar.*?</aside>\s*</div>\s*<div class="nowo-wiki-layout__main">.*?<div class="nowo-wiki-edit-body" data-testid="wiki-edit-body">\s*<div class="nowo-ui-form-page" data-nowo-ui-page="form" data-testid="wiki-page-form">.*?name="wiki_page_form\[title\]".*?</form>#s', $html));
        self::assertStringContainsString('data-testid="wiki-tabs"', $html);
        self::assertStringContainsString('wiki.js', $html);
        self::assertStringNotContainsString('nowo-ui-tree.js', $html);
        self::assertStringNotContainsString('nowo-ui-rail.js', $html);
        self::assertStringNotContainsString('data-nowo-wiki-card-frame', $html);
        self::assertStringNotContainsString('wiki-page-head', $html);
        self::assertSame(1, substr_count($html, '<form'));

        // Rail (remembered state in the head), reading column, path, comments panel around the whole layout.
        $html = $this->renderPage(new PageFixture(self::TEMPLATE, self::readLayout(['tree_rail' => true, 'reading_width' => 'readable', 'reading_align' => 'start', 'path' => 'card'], null, true, true)))->html;
        self::assertMatchesRegularExpression('#<aside class="[^"]*nowo-wiki-sidebar[^"]*" id="nowo-wiki-tree" data-nowo-ui-rail-host data-nowo-ui-rail="collapsed" data-wiki-sidebar#', $html);
        self::assertStringContainsString('data-nowo-ui-rail-persist="wiki-tree"', $html);
        self::assertSame(1, preg_match('#data-nowo-ui-contract-block="nowo_ui_styles">(?:(?!data-nowo-ui-contract-block).)*js/nowo-ui-layout-state\.js#s', $html));
        self::assertStringContainsString('js/nowo-ui-rail.js', $html);
        self::assertStringContainsString('js/nowo-ui-tree.js', $html);
        self::assertStringContainsString('class="nowo-wiki-layout__main nowo-wiki-reading nowo-ui-readable nowo-wiki-reading--start"', $html);
        self::assertSame(1, preg_match('#<div class="nowo-wiki-page-head" data-testid="wiki-page-head">\s*<nav class="nowo-wiki-path".*?Engineering.*?</nav>\s*</div>\s*<div class="nowo-wiki-edit-body"#s', $html));
        self::assertSame(1, preg_match('#data-testid="wiki-comments-layout".*?nowo-wiki-layout.*?nowo-wiki-edit-body.*?id="nowo-wiki-comments"#s', $html));
        self::assertStringNotContainsString('<script>', $html);
    }

    /**
     * Card toolbar (page_header.toolbar_position: card) with the tabs as buttons and the tree hidden mode: the
     * toolbar opens the card frame (tree toggle, View page, History, host buttons, comments, Reset collaboration),
     * the header keeps nothing of it; the edit toolbar options apply.
     */
    public function testTheCardToolbarAndTheEditToolbarOptions(): void
    {
        $context = static function (string $position = 'card', string $tabs = 'toolbar'): array {
            $context                              = self::readLayout(['toolbar_position' => $position, 'tabs' => $tabs, 'tree_collapsed_mode' => 'hidden', 'path' => 'card'], self::factory($tabs === 'toolbar', ['current_tab' => 'hidden', 'back' => 'view']), true, true);
            $context['nowo_wiki_web_ui']['slots'] = ['page_header_toolbar' => 'slots/page_header_toolbar.html.twig'];

            return $context;
        };
        $html = $this->renderWithKitHeader($context());
        self::assertSame(1, preg_match('#<div class="nowo-wiki-layout nowo-wiki-layout--tree-hidden">.*?<div class="nowo-wiki-card-frame" data-nowo-wiki-card-frame>\s*<div class="nowo-wiki-card-toolbar" data-nowo-wiki-card-toolbar>\s*<div class="nowo-wiki-card-toolbar__bar"[^>]*data-testid="wiki-card-toolbar">.*?data-testid="wiki-tree-toggle".*?data-testid="wiki-toolbar-view".*?data-testid="wiki-toolbar-history".*?data-testid="host-toolbar-button".*?data-testid="wiki-comments-toggle".*?</div>\s*</div>\s*<div class="nowo-wiki-page-head".*?<div class="nowo-wiki-edit-body"#s', $html));
        self::assertSame(1, preg_match('#data-testid="wiki-toolbar-view"[^>]*aria-label="View page"#', $html));
        self::assertStringNotContainsString('wiki-toolbar-back', $html);
        self::assertStringNotContainsString('wiki-toolbar-edit', $html);
        self::assertStringNotContainsString('wiki-tab-edit', $html);
        foreach (['wiki-comments-toggle', 'host-toolbar-button', 'wiki-tree-toggle', 'wiki-toolbar-view'] as $once) {
            self::assertSame(1, substr_count($html, 'data-testid="' . $once . '"'), $once);
        }
        $frame = strpos($html, 'data-nowo-wiki-card-frame');
        self::assertIsInt($frame);
        self::assertGreaterThan($frame, strpos($html, 'data-testid="wiki-comments-toggle"'));

        // Toolbar in the header (tabs: toolbar), tree hidden: the expand button and the toolbar in the header row.
        $header = $this->renderWithKitHeader($context('header'));
        self::assertStringNotContainsString('data-nowo-wiki-card-frame', $header);
        self::assertSame(1, preg_match('#data-testid="wiki-tree-toggle".*?data-testid="wiki-toolbar-view".*?data-testid="host-toolbar-button".*?data-testid="wiki-comments-toggle"#s', $header));

        // Stacked header with the tab row (Edit tab shown): the tree toggle at the end of the row.
        $stacked         = $context('header', 'row');
        $stacked['page'] = self::factory(false, [])->pageForm(...$this->formArguments());
        $stacked         = $this->renderPage(new PageFixture(self::TEMPLATE, $stacked))->html;
        self::assertSame(1, preg_match('#nowo-wiki-tabs__end">.*?data-testid="host-toolbar-button".*?data-testid="wiki-tree-toggle".*?data-testid="wiki-comments-toggle"#s', $stacked));
        self::assertStringContainsString('data-testid="wiki-tab-edit"', $stacked);

        // The form layout with back: view and the Edit tab hidden: the eye button among the header actions.
        $form = $this->renderWithKitHeader(PageFixtures::pageForm(true, self::factory(false, ['current_tab' => 'hidden', 'back' => 'view'])));
        self::assertSame(1, preg_match('#<a [^>]*href="/nowo_wiki_page_view\?spaceSlug=engineering&amp;pageSlug=deploy"[^>]*data-testid="wiki-edit-view-page"[^>]*aria-label="View page"#', $form));
        self::assertStringNotContainsString('wiki-tab-edit', $form);
        self::assertStringContainsString('data-testid="wiki-tab-page"', $form);
    }

    /** A host template that fills wiki_page_form_body gets the form untouched; parent() is the UiKit form scaffold. */
    public function testTheFormBodyBlock(): void
    {
        $html = $this->renderPage(new PageFixture('host/page_edit_form_body.html.twig', self::readLayout()))->html;
        self::assertSame(1, preg_match('#<div class="nowo-wiki-edit-body" data-testid="wiki-edit-body">\s*<p data-testid="host-form-intro">Deploy</p><form[^>]*>.*?name="wiki_page_form\[title\]".*?</form>\s*</div>#s', $html));
        self::assertStringNotContainsString('nowo-ui-form-page', $html);

        $parent = $this->renderPage(new PageFixture('host/page_edit_form_body_parent.html.twig', self::readLayout()))->html;
        self::assertSame(1, preg_match('#<p data-testid="host-form-intro">Deploy</p>\s*<div class="nowo-ui-form-page" data-nowo-ui-page="form"#s', $parent));
        self::assertStringNotContainsString('<!--nowo-wiki-page-form-->', $parent);
    }

    /**
     * The edit form of a page in the read layout (the controller's tree variables, WikiManageController).
     *
     * @param array<string, mixed> $layout
     *
     * @return array<string, mixed>
     */
    private static function readLayout(array $layout = [], ?WikiPageFactory $factory = null, bool $comments = false, bool $rail = false): array
    {
        $context = PageFixtures::pageForm(true, $factory, $comments);
        $view    = PageFixtures::pageView();
        $space   = PageFixtures::space('engineering', 'Engineering');
        foreach (['tree', 'tree_truncated', 'max_tree_pages', 'tree_search_href'] as $key) {
            $context[$key] = $view[$key];
        }
        $context['nowo_wiki_web_ui'] = ['layout' => ['edit_layout' => 'read'] + $layout];
        if ($rail) {
            $tree                 = new WikiPageFactory(PageFixtures::urlGenerator(), PageFixtures::routes() + ['space_tree' => ['path' => '/t', 'name' => 'nowo_wiki_space_tree']]);
            $context['tree_ui']   = ['nodes' => $tree->uiTree($view['tree'], []), 'attr' => $tree->treeAttributes($space, 'tree-token', false), 'movable' => false];
            $context['tree_rail'] = [
                'id'          => 'nowo-wiki-tree',
                'body_id'     => 'nowo-wiki-tree-body',
                'collapsed'   => true,
                'href'        => '/p?tree=full',
                'state_url'   => null,
                'state_token' => null,
                'state_panel' => 'tree_rail',
                'persist'     => 'wiki-tree',
            ];
        }
        if (($layout['path'] ?? 'none') === 'card') {
            $context['page_path'] = [['label' => 'Engineering', 'href' => '/s/eng'], ['label' => 'Parent', 'href' => '/p/parent']];
        }

        return $context;
    }

    /**
     * @param array{current_tab?: string, back?: string} $editToolbar
     */
    private static function factory(bool $tabsToolbar, array $editToolbar): WikiPageFactory
    {
        return new WikiPageFactory(PageFixtures::urlGenerator(), PageFixtures::routes(), new WikiPages(), null, WikiPageFactory::TAB_ICONS, false, $tabsToolbar, $tabsToolbar, false, $editToolbar);
    }

    /**
     * Arguments of WikiPageFactory::pageForm() for the fixture page with Reset collaboration.
     *
     * @return list<mixed>
     */
    private function formArguments(): array
    {
        $context  = PageFixtures::pageForm();
        $space    = PageFixtures::space('engineering', 'Engineering');
        [, $page] = PageFixtures::pages($space);

        return [$space, $page, $context['page']->form, PageFixtures::postForm('wiki_collab_reset_1'), ['history' => true]];
    }

    /**
     * Renders with the real UiKit `page_header` partial (toolbar, tabs_inline).
     *
     * @param array<string, mixed> $context
     */
    private function renderWithKitHeader(array $context): string
    {
        $twig = $this->createTwig();
        $twig->setLoader(new ChainLoader([new ArrayLoader([StubHost::PARTIAL_TEMPLATE => "{{ include('@NowoUiKitBundle/partials/_page_header.html.twig') }}"]), $twig->getLoader()]));

        return $twig->render(self::TEMPLATE, $context);
    }

    protected function configureTwig(Environment $twig): void
    {
        $this->configureWikiTwig($twig);
        $twig->setLoader(new ChainLoader([new ArrayLoader([
            'slots/page_header_toolbar.html.twig'       => '<button type="button" data-testid="host-toolbar-button">Copy {{ page.bundle }}</button>',
            'host/page_edit_form_body.html.twig'        => "{% extends '@NowoWikiBundle/manage/page_edit.html.twig' %}{% block wiki_page_form_body %}<p data-testid=\"host-form-intro\">{{ page.form.vars.value.title }}</p>{{ form_start(page.form) }}{{ form_rest(page.form) }}{{ form_end(page.form) }}{% endblock %}",
            'host/page_edit_form_body_parent.html.twig' => "{% extends '@NowoWikiBundle/manage/page_edit.html.twig' %}{% block wiki_page_form_body %}<p data-testid=\"host-form-intro\">{{ page.form.vars.value.title }}</p>{{ parent() }}{% endblock %}",
        ]), $twig->getLoader()]));
    }
}
