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

        self::assertSame(1, substr_count($html, 'aria-current="page"'));
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
}
