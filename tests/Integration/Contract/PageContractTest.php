<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\Contract;

use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;
use Nowo\WikiBundle\Tests\Support\PageFixtures;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

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
        yield 'history' => new PageFixture('@NowoWikiBundle/manage/page_history.html.twig', PageFixtures::history());
        yield 'history (empty)' => new PageFixture('@NowoWikiBundle/manage/page_history.html.twig', PageFixtures::history(true));
        yield 'diff' => new PageFixture('@NowoWikiBundle/manage/page_diff.html.twig', PageFixtures::diff());
        yield 'diff (same content)' => new PageFixture('@NowoWikiBundle/manage/page_diff.html.twig', PageFixtures::diff(true));
        yield 'search' => new PageFixture('@NowoWikiBundle/manage/search.html.twig', PageFixtures::search());
        yield 'search (no query)' => new PageFixture('@NowoWikiBundle/manage/search.html.twig', PageFixtures::search('', false));
        yield 'search (no hit)' => new PageFixture('@NowoWikiBundle/manage/search.html.twig', PageFixtures::search('nothing'));
        yield 'ai ask (answered)' => new PageFixture('@NowoWikiBundle/manage/ai_ask.html.twig', PageFixtures::aiAsk());
        yield 'ai ask (error)' => new PageFixture('@NowoWikiBundle/manage/ai_ask.html.twig', PageFixtures::aiAsk(false, 'wiki.ai.unavailable'));
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

    public function testHistoryAvatarsOnlyBesideRealNames(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoWikiBundle/manage/page_history.html.twig', PageFixtures::history(false, null, true)))->html;

        self::assertSame(1, substr_count($html, 'data-testid="wiki-history-avatar"'));
        self::assertStringContainsString('src="/files/maria/s64"', $html);
        self::assertStringContainsString('Editor 3f2a', $html);
    }
}
