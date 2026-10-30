<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Resources;

use DateTimeImmutable;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;
use Nowo\WikiBundle\Twig\WikiHtmlTwigExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

use function dirname;
use function is_array;
use function sprintf;

/**
 * FR-006 (spec 178 T004): manage templates expose semantic `nowo-wiki-*` hooks, overridable blocks, the CSP nonce on
 * scripts, and no inline style / event-handler attributes.
 */
final class ManageTemplatesTest extends TestCase
{
    private const VIEWS = '/src/Resources/views';

    /**
     * @param array<string, string> $extraTemplates host templates (name => source) loaded before the bundle ones
     */
    private function twig(array $extraTemplates = []): Environment
    {
        $files = new FilesystemLoader();
        $files->addPath(dirname(__DIR__, 3) . self::VIEWS, 'NowoWikiBundle');

        $host = new ArrayLoader($extraTemplates + [
            '@NowoUiKitBundle/macros/ui.html.twig' => '',
            'host/layout.html.twig'                => '<body>{% block stylesheets %}{% endblock %}{% block body %}{% endblock %}{% block javascripts %}{% endblock %}</body>',
        ]);

        $twig = new Environment(new ChainLoader([$host, $files]), ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension(new Translator('en')));
        $twig->addExtension(new WikiHtmlTwigExtension(new class implements WikiHtmlSanitizerInterface {
            public function sanitize(string $html): string
            {
                return $html;
            }
        }));

        $safe = ['is_safe' => ['html']];
        $twig->addFunction(new TwigFunction('path', static fn (string $name, array $params = []): string => '/' . $name));
        $twig->addFunction(new TwigFunction('asset', static fn (string $path, ?string $package = null): string => '/assets/' . $path));
        $twig->addFunction(new TwigFunction('nowo_tiptap_editor_asset_path', static fn (string $path): string => $path));
        $twig->addFunction(new TwigFunction('nowo_tiptap_editor_asset_package', static fn (): string => 'tiptap'));
        $twig->addFunction(new TwigFunction('form_start', static function (mixed $form, array $options = []): string {
            $class = is_array($options['attr'] ?? null) ? (string) ($options['attr']['class'] ?? '') : '';

            return sprintf('<form class="%s">', $class);
        }, $safe));
        $twig->addFunction(new TwigFunction('form_end', static fn (mixed $form): string => '</form>', $safe));
        foreach (['form_label', 'form_widget', 'form_row', 'form_errors', 'form_rest'] as $name) {
            $twig->addFunction(new TwigFunction($name, static fn (mixed ...$args): string => '', $safe));
        }

        return $twig;
    }

    /**
     * @param list<string> $fields
     *
     * @return array<string, array{rendered: bool}>
     */
    private function form(array $fields): array
    {
        $form = [];
        foreach ($fields as $field) {
            $form[$field] = ['rendered' => true];
        }

        return $form;
    }

    /**
     * @return array<string, mixed>
     */
    private function context(string $framework = 'custom', ?string $nonce = null): array
    {
        $routeNames = ['index', 'space', 'page_view', 'page_new', 'page_edit', 'page_history', 'page_diff', 'search', 'space_export', 'ai_ask'];
        $routes     = [];
        foreach ($routeNames as $route) {
            $routes[$route] = ['name' => 'wiki_' . $route];
        }

        $space = ['name' => 'Docs', 'slug' => 'docs'];
        $page  = ['title' => 'Home', 'slug' => 'home', 'id' => 1];
        $child = ['title' => 'Child', 'slug' => 'child'];
        $deep  = ['title' => 'Deep', 'slug' => 'deep'];

        return [
            'app' => [
                'flashes' => ['success' => ['Saved.']],
                'request' => ['locale' => 'en', 'attributes' => new ParameterBag($nonce === null ? [] : ['csp_nonce' => $nonce])],
            ],
            'layout'           => 'host/layout.html.twig',
            'nowo_wiki_web_ui' => ['css_framework' => $framework],
            'routes'           => $routes,
            'dashboard_route'  => null,
            'space'            => $space,
            'spaces'           => [$space],
            'page'             => $page,
            'tree'             => [['page' => $page, 'children' => [['page' => $child, 'children' => [['page' => $deep, 'children' => []]]]]]],
            'revision'         => ['contentHtml' => '<p>Body text</p>'],
            'can_edit'         => true,
            'can_history'      => true,
            'can_create'       => true,
            'can_export'       => true,
            'can_import'       => true,
            'can_ask_ai'       => false,
            'used_in'          => [[['type' => 'issue', 'url' => '/issues/1', 'label' => 'Issue 1', 'badge' => 'open']]],
            'archived_pages'   => [['id' => 7, 'title' => 'Old page']],
            'unarchive_forms'  => [],
            'restore_forms'    => [],
            'search_form'      => $this->form(['space', 'q']),
            'import_form'      => $this->form(['format', 'archive', 'update_existing']),
            'ai_form'          => $this->form(['space', 'q']),
            'form'             => $this->form(['title', 'content']),
            'revisions'        => [
                ['revisionNumber' => 2, 'createdAt' => new DateTimeImmutable('2026-10-30 10:00')],
                ['revisionNumber' => 1, 'createdAt' => new DateTimeImmutable('2026-10-29 10:00')],
            ],
            'from_revision' => ['revisionNumber' => 1],
            'to_revision'   => ['revisionNumber' => 2],
            'diff'          => [['kind' => 'add', 'line' => 'new'], ['kind' => 'remove', 'line' => 'old'], ['kind' => 'same', 'line' => 'kept']],
            'results'       => [['space' => $space, 'page' => $page, 'excerpt' => 'Excerpt']],
            'global_search' => true,
            'query'         => 'home',
            'error'         => null,
            'answer'        => 'An answer',
            'question'      => 'A question?',
            'sources'       => [['space' => $space, 'page' => $page, 'excerpt' => 'Source excerpt']],
        ];
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function pages(): iterable
    {
        yield 'index' => ['index', ['nowo-wiki--index', 'nowo-wiki-header', 'nowo-wiki-highlight--search', 'nowo-wiki-search-form', 'nowo-wiki-spaces__card', 'nowo-wiki-flash nowo-wiki-flash--success']];
        yield 'space' => ['space', ['nowo-wiki--space', 'nowo-wiki-layout', 'nowo-wiki-sidebar', 'nowo-wiki-tree', 'nowo-wiki-import-export', 'nowo-wiki-archived__item', 'nowo-wiki-btn nowo-wiki-btn--sm nowo-wiki-btn--primary']];
        yield 'page_view' => ['page_view', ['nowo-wiki--page', 'nowo-wiki-layout__sidebar', 'nowo-wiki-page__header', 'nowo-wiki-page__actions', 'nowo-wiki-content', 'nowo-wiki-used-in__item', '<p>Body text</p>']];
        yield 'page_edit' => ['page_edit', ['nowo-wiki--edit', 'nowo-wiki-editor__form', 'nowo-wiki-form__field--title', 'nowo-wiki-form__actions']];
        yield 'page_history' => ['page_history', ['nowo-wiki--history', 'nowo-wiki-history__table', 'nowo-wiki-history__row', 'nowo-wiki-table-scroll', 'nowo-wiki-panel__footer']];
        yield 'page_diff' => ['page_diff', ['nowo-wiki--diff', 'nowo-wiki-diff__line--add', 'nowo-wiki-diff__line--remove', 'nowo-wiki-diff__line--same', 'wiki-diff-add']];
        yield 'search' => ['search', ['nowo-wiki--search', 'nowo-wiki-search-form', 'nowo-wiki-results__item', 'nowo-wiki-results__excerpt', 'nowo-wiki-results__count']];
        yield 'ai_ask' => ['ai_ask', ['nowo-wiki--ai', 'nowo-wiki-ai__answer', 'nowo-wiki-ai__sources', 'nowo-wiki-ai__follow-up', 'nowo-wiki-btn--ai']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('pages')]
    public function testEveryPageRendersSemanticClassesNextToTheFrameworkOnes(string $template, array $expected): void
    {
        $html = $this->twig()->render('@NowoWikiBundle/manage/' . $template . '.html.twig', $this->context());

        self::assertStringContainsString('class="nowo-wiki nowo-wiki--', $html);
        self::assertStringContainsString('data-nowo-wiki-skin="tokens"', $html);
        foreach ($expected as $needle) {
            self::assertStringContainsString($needle, $html, $template);
        }
        // Tabler / Bootstrap classes stay for framework hosts and the demo.
        self::assertMatchesRegularExpression('/class="[^"]*\b(card|btn|row|alert)\b/', $html);
    }

    public function testSkinFollowsTheConfiguredCssFramework(): void
    {
        $twig = $this->twig();

        foreach (['tabler', 'bootstrap', 'bootstrap4', 'bootstrap5'] as $framework) {
            self::assertStringContainsString('data-nowo-wiki-skin="framework"', $twig->render('@NowoWikiBundle/manage/page_view.html.twig', $this->context($framework)), $framework);
        }
        foreach (['custom', 'none', 'tailwind', 'foundation'] as $framework) {
            self::assertStringContainsString('data-nowo-wiki-skin="tokens"', $twig->render('@NowoWikiBundle/manage/page_view.html.twig', $this->context($framework)), $framework);
        }
    }

    public function testScriptsCarryTheRequestCspNonceWhenTheHostSetsIt(): void
    {
        $twig = $this->twig();

        $html = $twig->render('@NowoWikiBundle/manage/page_edit.html.twig', $this->context('custom', 'n0nce'));
        self::assertSame(2, substr_count($html, 'nonce="n0nce"'));
        self::assertStringContainsString('<script src="/assets/wiki.js" defer nonce="n0nce">', $html);
        self::assertStringContainsString('<script src="/assets/tiptap-editor.js" nonce="n0nce">', $html);

        $demo = $twig->render('@NowoWikiBundle/manage/index.html.twig', ['layout' => '@NowoWikiBundle/layout.html.twig'] + $this->context('tabler', 'n0nce'));
        self::assertStringContainsString('bootstrap.bundle.min.js" defer nonce="n0nce"', $demo);
        self::assertStringContainsString('class="container container-wiki nowo-ui-container nowo-wiki-container py-4"', $demo);

        $without = $twig->render('@NowoWikiBundle/manage/page_edit.html.twig', $this->context());
        self::assertStringNotContainsString('nonce=', $without);
    }

    public function testPageTreeNestsListsWithCappedDepthClassesAndMarksTheCurrentPage(): void
    {
        $html = $this->twig()->render('@NowoWikiBundle/manage/page_view.html.twig', $this->context());

        self::assertSame(3, substr_count($html, '<ul class="list-unstyled mb-0 wiki-tree nowo-wiki-tree'));
        self::assertSame(2, substr_count($html, 'nowo-wiki-tree--nested'));
        self::assertStringContainsString('nowo-wiki-tree__item--depth-0 nowo-wiki-tree__item--parent', $html);
        self::assertStringContainsString('nowo-wiki-tree__item--depth-2"', $html);
        self::assertStringContainsString('data-wiki-depth="2"', $html);
        self::assertSame(1, substr_count($html, 'aria-current="page"'));
        self::assertStringContainsString('nowo-wiki-tree__link--current" aria-current="page">Home</a>', $html);
    }

    public function testDepthClassIsCappedAtSix(): void
    {
        $html = $this->twig()->render('@NowoWikiBundle/manage/_page_tree_item.html.twig', [
            'node'         => ['page' => ['title' => 'Leaf', 'slug' => 'leaf'], 'children' => []],
            'space'        => ['slug' => 'docs'],
            'routes'       => ['page_view' => ['name' => 'wiki_page_view']],
            'depth'        => 9,
            'current_slug' => null,
        ]);

        self::assertStringContainsString('nowo-wiki-tree__item--depth-6"', $html);
        self::assertStringContainsString('data-wiki-depth="9"', $html);
        self::assertStringNotContainsString('aria-current', $html);
    }

    public function testHostCanOverrideThePageViewBlocks(): void
    {
        $twig = $this->twig([
            'host/page_view.html.twig' => "{% extends '@NowoWikiBundle/manage/page_view.html.twig' %}"
                . '{% block page_actions %}<div class="host-actions">{{ parent() }}</div>{% endblock %}'
                . '{% block page_aside %}<aside class="host-aside">Linked issues</aside>{% endblock %}'
                . '{% block page_tree %}<p class="host-tree"></p>{% endblock %}',
        ]);

        $html = $twig->render('host/page_view.html.twig', $this->context());

        self::assertMatchesRegularExpression('/<div class="host-actions">\s*<div class="btn-list nowo-wiki-actions nowo-wiki-page__actions">/', $html);
        self::assertStringContainsString('<aside class="host-aside">Linked issues</aside>', $html);
        self::assertStringNotContainsString('nowo-wiki-used-in', $html);
        self::assertStringContainsString('<p class="host-tree"></p>', $html);
        self::assertStringNotContainsString('nowo-wiki-tree__item', $html);
        self::assertStringContainsString('nowo-wiki-content', $html);
    }

    public function testHostCanOverrideBlocksOfTheOtherPages(): void
    {
        $twig = $this->twig([
            'host/space.html.twig'   => "{% extends '@NowoWikiBundle/manage/space.html.twig' %}{% block space_import_export %}<p class=\"host-io\"></p>{% endblock %}{% block wiki_header %}<h1 class=\"host-header\">{{ space.name }}</h1>{% endblock %}",
            'host/history.html.twig' => "{% extends '@NowoWikiBundle/manage/page_history.html.twig' %}{% block history_footer %}<p class=\"host-footer\"></p>{% endblock %}",
            'host/search.html.twig'  => "{% extends '@NowoWikiBundle/manage/search.html.twig' %}{% block search_results %}<p class=\"host-results\"></p>{% endblock %}",
        ]);

        $space = $twig->render('host/space.html.twig', $this->context());
        self::assertStringContainsString('<p class="host-io"></p>', $space);
        self::assertStringNotContainsString('nowo-wiki-import-export', $space);
        self::assertStringContainsString('<h1 class="host-header">Docs</h1>', $space);
        self::assertStringNotContainsString('nowo-wiki-header__title', $space);

        $history = $twig->render('host/history.html.twig', $this->context());
        self::assertStringContainsString('<p class="host-footer"></p>', $history);
        self::assertStringContainsString('nowo-wiki-history__table', $history);

        $search = $twig->render('host/search.html.twig', $this->context());
        self::assertStringContainsString('<p class="host-results"></p>', $search);
        self::assertStringNotContainsString('nowo-wiki-results__item', $search);
    }

    public function testNoTemplateHasInlineStylesEventHandlersOrUnnoncedScripts(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3) . self::VIEWS));
        $checked  = 0;

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.twig')) {
                continue;
            }
            ++$checked;
            $source = (string) file_get_contents($file->getPathname());
            $name   = $file->getFilename();

            self::assertDoesNotMatchRegularExpression('/<[a-z][^>]*\sstyle\s*=/i', $source, $name . ': inline style attribute');
            self::assertDoesNotMatchRegularExpression('/<[a-z][^>]*\son[a-z]+\s*=/i', $source, $name . ': inline event handler');
            self::assertDoesNotMatchRegularExpression('/<style\b/i', $source, $name . ': inline <style> block');
            self::assertSame(0, preg_match('/<script\b(?![^>]*nonce=)[^>]*>/i', $source), $name . ': <script> without the CSP nonce');
        }

        self::assertGreaterThanOrEqual(16, $checked);
    }
}
