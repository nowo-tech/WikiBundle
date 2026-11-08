<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Markdown\Milkdown;

use Nowo\MilkdownEditorBundle\Markdown\Directive\DirectiveRendererInterface;
use Nowo\MilkdownEditorBundle\Markdown\HostSchemes;
use Nowo\MilkdownEditorBundle\Markdown\Node\LeafDirective;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\AllowlistIconResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\ContextualHostFileResolverInterface;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\HostFileResolverInterface;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\NullPageResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\NullUserResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\ResolvedFile;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\TemplateHostFileResolver;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Markdown\Milkdown\MilkdownWikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\Milkdown\WikiLeafDirectiveRenderer;
use Nowo\WikiBundle\Markdown\Milkdown\WikiLeafHostFiles;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownHostFileResolver;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownRenderContext;
use Nowo\WikiBundle\Markdown\WikiRenderContext;
use Nowo\WikiBundle\Markdown\WikiRenderFrame;
use Nowo\WikiBundle\Markdown\WikiRenderReach;
use Nowo\WikiBundle\Markdown\WikiViewerMarkdownView;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;

use function explode;
use function substr_count;

/**
 * 1.25 (Beacon request after Appendix IF): files a page references by plain URL (`/files/<uuid>/…`, Milkdown's
 * `host_url_patterns`) or by scheme resolve through the host's resolver with the render's frame. A file whose wiki the
 * reach (the API token's *where*) excludes reads *Content not visible*, with no uuid, alt text or name; the UI, without a
 * reach, renders exactly what it rendered before.
 */
#[CoversClass(MilkdownWikiMarkdownRenderer::class)]
#[CoversClass(WikiMilkdownHostFileResolver::class)]
#[CoversClass(WikiMilkdownRenderContext::class)]
#[CoversClass(WikiLeafDirectiveRenderer::class)]
#[CoversClass(WikiLeafHostFiles::class)]
final class WikiMilkdownHostFilesTest extends TestCase
{
    private const ENG_FILE = '0f8fad5b-d9cb-469f-a165-70867728950e';

    private const HR_FILE = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

    private const SCHEMES = ['beacon-file' => ['file' => '/files/{id}/original', 'image' => '/files/{id}/preview']];

    private const PATTERNS = [['pattern' => '^/files/(?<id>[0-9a-f-]{36})/(?:original|preview)$', 'scheme' => 'beacon-file']];

    private WikiSpace $eng;

    private WikiSpace $hr;

    private WikiPage $page;

    private WikiRenderContext $context;

    protected function setUp(): void
    {
        $this->eng     = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->hr      = new WikiSpace('hr', 'People', WikiSpaceOwnerScope::Team, 'team-1');
        $this->page    = new WikiPage($this->eng, 'runbook', 'Runbook');
        $this->context = new WikiRenderContext();
    }

    private function source(): string
    {
        return '![secret org chart](/files/' . self::HR_FILE . "/preview)\n\n"
            . 'See [salaries 2026.pdf](/files/' . self::HR_FILE . '/original) and ![eng diagram](/files/' . self::ENG_FILE . "/preview).\n\n"
            . '::file[secret payroll.pdf]{href="/files/' . self::HR_FILE . "/original\"}\n\n"
            . '::file[eng notes.pdf]{href="/files/' . self::ENG_FILE . '/original"}';
    }

    private function onlyEng(): WikiRenderReach
    {
        return new class($this->eng) implements WikiRenderReach {
            public function __construct(private readonly WikiSpace $space)
            {
            }

            public function reachesSpace(WikiSpace $space): bool
            {
                return $space === $this->space;
            }
        };
    }

    /**
     * @param list<array{pattern: string, scheme: string}> $patterns
     */
    private function renderer(?HostFileResolverInterface $files, array $patterns = self::PATTERNS, ?TranslatorInterface $translator = null, bool $fileCards = false): MilkdownWikiMarkdownRenderer
    {
        return new MilkdownWikiMarkdownRenderer(
            [],
            new NullPageResolver(),
            new NullUserResolver(),
            new AllowlistIconResolver(),
            translator: $translator,
            hostSchemes: new HostSchemes(self::SCHEMES, [], $patterns),
            hostFiles: $files,
            renderContext: new WikiMilkdownRenderContext($this->context),
            fileCards: $fileCards,
        );
    }

    private function view(MilkdownWikiMarkdownRenderer $renderer): WikiViewerMarkdownView
    {
        return new WikiViewerMarkdownView($renderer, new WikiHtmlSanitizer(), new WikiMarkdownConverter(true), $this->context);
    }

    public function testAFileOutsideTheReachLeavesNoUuidAltOrName(): void
    {
        $markdown = $this->view($this->renderer(new SpaceFiles([self::ENG_FILE => $this->eng, self::HR_FILE => $this->hr])))
            ->markdown($this->eng, $this->page, $this->source(), $this->onlyEng());

        self::assertStringNotContainsString(self::HR_FILE, $markdown);
        self::assertStringNotContainsString('secret', $markdown);
        self::assertStringNotContainsString('salaries', $markdown);
        self::assertSame(3, substr_count($markdown, 'Content not visible'));
        self::assertStringContainsString('![eng diagram](/files/' . self::ENG_FILE . '/preview)', $markdown);
        self::assertStringContainsString('eng notes.pdf', $markdown);
    }

    public function testWithoutAReachThePersonSeesWhatTheResolverAllows(): void
    {
        $markdown = $this->view($this->renderer(new SpaceFiles([self::ENG_FILE => $this->eng, self::HR_FILE => $this->hr])))
            ->markdown($this->eng, $this->page, $this->source());

        self::assertStringContainsString('![secret org chart](/files/' . self::HR_FILE . '/preview)', $markdown);
        self::assertStringContainsString('[salaries 2026.pdf](/files/' . self::HR_FILE . '/original)', $markdown);
        self::assertStringNotContainsString('Content not visible', $markdown);
    }

    /** The UI page body for a person who may see every file is byte for byte what 1.24 rendered. */
    public function testTheUiOutputDoesNotChangeForAPersonWithAccess(): void
    {
        $before   = new MilkdownWikiMarkdownRenderer([], new NullPageResolver(), new NullUserResolver(), new AllowlistIconResolver());
        $after    = $this->renderer(new SpaceFiles([self::ENG_FILE => $this->eng, self::HR_FILE => $this->hr]));
        $markdown = $this->source() . "\n\n[docs](https://example.com/docs) ![logo](/img/logo.png)";

        $expected = $before->renderForViewer($markdown);
        self::assertSame($expected, $this->context->within($this->eng, $this->page, static fn (): string => $after->renderForViewer($markdown)));
        self::assertSame($before->render($markdown), $after->render($markdown));
        self::assertStringContainsString(self::HR_FILE, $expected);
    }

    public function testARefusedCardIsTranslatedAndTheNeutralRenderNeverResolves(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Contenido no visible');
        $renderer = $this->renderer(new SpaceFiles([]), self::PATTERNS, $translator);

        $html = $this->context->within($this->eng, $this->page, static fn (): string => $renderer->renderForViewer('::file[secret.pdf]{href="/files/' . self::HR_FILE . '/original"}'));
        self::assertStringContainsString('<span class="nowo-md-ref nowo-md-ref--hidden">Contenido no visible</span>', $html);
        self::assertStringNotContainsString('secret', $html);
        self::assertStringContainsString('secret.pdf', $renderer->render('::file[secret.pdf]{href="/files/' . self::HR_FILE . '/original"}'));
    }

    public function testAResolverThatCannotSeeTheFrameResolvesNothingUnderAReach(): void
    {
        $schemes  = new HostSchemes(self::SCHEMES, [], self::PATTERNS);
        $renderer = $this->renderer(new TemplateHostFileResolver($schemes));

        $reached = $this->view($renderer)->markdown($this->eng, $this->page, $this->source(), $this->onlyEng());
        self::assertStringNotContainsString(self::HR_FILE, $reached);
        self::assertStringNotContainsString(self::ENG_FILE, $reached);
        self::assertSame(5, substr_count($reached, 'Content not visible'));

        self::assertStringContainsString(self::HR_FILE, $this->view($renderer)->markdown($this->eng, $this->page, $this->source()));
    }

    public function testWithoutAResolverEveryHostFileIsRefused(): void
    {
        $markdown = $this->view($this->renderer(null))->markdown($this->eng, $this->page, $this->source());

        self::assertStringNotContainsString('/files/', $markdown);
        self::assertSame(5, substr_count($markdown, 'Content not visible'));
    }

    /** Audit IJ-L2: every URL attribute of a leaf directive that names a host file goes through the resolver. */
    public function testDirectiveUrlAttributesGoThroughTheResolver(): void
    {
        $view = $this->view($this->renderer(new SpaceFiles([self::ENG_FILE => $this->eng, self::HR_FILE => $this->hr])));
        $hr   = '/files/' . self::HR_FILE . '/original';
        $eng  = '/files/' . self::ENG_FILE . '/original';
        foreach ([
            '::file[secret card.pdf]{url="' . $hr . '"}',
            '::file[secret card.pdf]{link="' . $hr . '"}',
            '::file[secret card.pdf]{href="' . $eng . '" url="' . $hr . '"}',
            '::download[secret]{href="' . $hr . '"}',
            '::download{href="' . $hr . '"}',
        ] as $source) {
            $markdown = $view->markdown($this->eng, $this->page, $source, $this->onlyEng());
            self::assertStringNotContainsString(self::HR_FILE, $markdown, $source);
            self::assertStringNotContainsString('secret', $markdown, $source);
            self::assertStringContainsString('Content not visible', $markdown, $source);
        }

        // Within reach: unchanged for the person, and a `::file` links its href, never a url next to it.
        self::assertSame('[download](' . $eng . ')', $view->markdown($this->eng, $this->page, '::download{href="' . $eng . '"}', $this->onlyEng()));
        self::assertSame('[notes.pdf](' . $eng . ')', $view->markdown($this->eng, $this->page, '::file[notes.pdf]{href="' . $eng . '" url="/elsewhere"}', $this->onlyEng()));
        self::assertSame('[notes.pdf](/elsewhere)', $view->markdown($this->eng, $this->page, '::file[notes.pdf]{href="javascript:x" url="/elsewhere"}', $this->onlyEng()));
    }

    public function testDirectiveFilesFailClosed(): void
    {
        $failing = new class implements ContextualHostFileResolverInterface {
            public function resolveFiles(array $references): array
            {
                throw new RuntimeException('down');
            }

            public function resolveFilesInContext(array $references, ?object $context): array
            {
                throw new RuntimeException('down');
            }
        };
        foreach ([$failing, null] as $files) {
            $markdown = $this->view($this->renderer($files))->markdown($this->eng, $this->page, '::download[secret]{href="/files/' . self::ENG_FILE . '/original"}');
            self::assertStringNotContainsString('secret', $markdown);
            self::assertStringNotContainsString(self::ENG_FILE, $markdown);
        }
        // A matching URL with an invalid id is refused without asking.
        $markdown = $this->view($this->renderer(new SpaceFiles([]), [['pattern' => '^/files/(?<id>[^/]+)/original$', 'scheme' => 'beacon-file']]))
            ->markdown($this->eng, $this->page, '::download[secret]{href="/files/.env/original"}');
        self::assertStringNotContainsString('secret', $markdown);
        self::assertStringContainsString('Content not visible', $markdown);
    }

    /**
     * 1.25 (WK-8, file_cards: card): a card the viewer may see is Milkdown's file card with the resolved URL; one outside
     * the reach is the hidden placeholder, without uuid or name; no host renderer is involved.
     */
    public function testFileCardsReadAsMilkdownCardsThroughTheHostResolution(): void
    {
        $renderer = $this->renderer(new SpaceFiles([self::ENG_FILE => $this->eng, self::HR_FILE => $this->hr]), fileCards: true);
        $html     = $this->context->within($this->eng, $this->page, fn (): string => $renderer->renderForViewer($this->source()), $this->onlyEng());

        self::assertStringContainsString('<div class="nowo-md-file nowo-md-file--pdf" data-directive="file"><a class="nowo-md-file__link" href="/files/' . self::ENG_FILE . '/original" rel="noopener noreferrer"><span class="nowo-md-file__badge" aria-hidden="true">PDF</span><span class="nowo-md-file__name">eng notes.pdf</span></a></div>', $html);
        self::assertStringContainsString('<div class="nowo-md-file nowo-md-file--hidden" data-directive="file"><span class="nowo-md-ref nowo-md-ref--hidden">Content not visible</span></div>', $html);
        self::assertStringNotContainsString(self::HR_FILE, $html);
        self::assertStringNotContainsString('secret', $html);
        self::assertStringNotContainsString('nowo-wiki-directive', $html);

        // The API reader view: the card reads as a link to its resolved URL, the refused one as the placeholder.
        $markdown = $this->view($renderer)->markdown($this->eng, $this->page, $this->source() . '

::file[size.csv]{href="/files/' . self::ENG_FILE . '/original" size="2048"}', $this->onlyEng());
        self::assertStringContainsString('[eng notes.pdf](/files/' . self::ENG_FILE . '/original)', $markdown);
        self::assertStringContainsString('[size.csv](/files/' . self::ENG_FILE . '/original)', $markdown);
        self::assertStringNotContainsString(self::HR_FILE, $markdown);
        self::assertStringNotContainsString('secret', $markdown);
        self::assertSame(3, substr_count($markdown, 'Content not visible'));

        // The neutral render (content_html) keeps the plain link.
        self::assertStringContainsString('nowo-wiki-directive__link', $renderer->render('::file[eng notes.pdf]{href="/files/' . self::ENG_FILE . '/original"}'));
    }

    /** 1.25 (WK-8): a card's URL also passes the wiki link policy; views and labels follow Milkdown's card. */
    public function testFileCardsKeepTheWikiLinkPolicy(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $renderer = new MilkdownWikiMarkdownRenderer([], new NullPageResolver(), new NullUserResolver(), new AllowlistIconResolver(), translator: $translator, fileCards: true);

        foreach (['//evil.example/x.pdf', 'http://plain.example/x.pdf', 'javascript:alert(1)', '/.//evil.example/x.pdf', '/%2F%2Fevil.example/x.pdf'] as $href) {
            $html = $renderer->renderForViewer('::file[x.pdf]{href="' . $href . '"}');
            self::assertStringContainsString('<span class="nowo-md-file__link">', $html, $href);
            self::assertStringNotContainsString('href=', $html, $href);
        }
        self::assertStringContainsString('<p class="nowo-md-file nowo-md-file--link-view nowo-md-file--pdf" data-directive="file" data-view="link"><a class="nowo-md-file__text" href="https://docs.example/x.pdf" rel="noopener noreferrer">x.pdf</a></p>', $renderer->renderForViewer('::file[x.pdf]{href="https://docs.example/x.pdf" view="link"}'));
        self::assertStringContainsString('<a class="nowo-md-file__link" href="/docs/x.pdf"', $renderer->renderForViewer('::file[x.pdf]{href="/docs/x.pdf" view="preview"}'));
        self::assertStringNotContainsString('<iframe', $renderer->renderForViewer('::file[x.pdf]{href="/docs/x.pdf" view="preview"}'));
    }

    public function testAHostRendererOfTheDirectiveChecksItsOwn(): void
    {
        $download = new class implements DirectiveRendererInterface {
            public function supports(string $name): bool
            {
                return $name === 'download';
            }

            public function prepare(array $nodes): void
            {
            }

            public function render(LeafDirective $node): string
            {
                return '<p>host</p>';
            }
        };
        $renderer = new MilkdownWikiMarkdownRenderer([], new NullPageResolver(), new NullUserResolver(), new AllowlistIconResolver(), [$download], hostSchemes: new HostSchemes(self::SCHEMES, [], self::PATTERNS), hostFiles: new SpaceFiles([]), renderContext: new WikiMilkdownRenderContext($this->context));

        self::assertSame('<p>host</p>', $this->context->within($this->eng, $this->page, static fn (): string => $renderer->renderForViewer('::download{href="/files/' . self::HR_FILE . '/original"}')));
    }

    public function testTheWrapperAndTheContext(): void
    {
        $inner   = new SpaceFiles([self::ENG_FILE => $this->hr]);
        $wrapper = new WikiMilkdownHostFileResolver($inner);
        $refs    = ['beacon-file:' . self::ENG_FILE];
        $frame   = new WikiRenderFrame($this->hr, null, $this->onlyEng());

        self::assertArrayHasKey($refs[0], $wrapper->resolveFiles($refs));
        self::assertSame([], $wrapper->resolveFilesInContext($refs, $frame));
        self::assertSame([null, $frame], $inner->contexts);

        $plain = new WikiMilkdownHostFileResolver(new TemplateHostFileResolver(new HostSchemes(self::SCHEMES)));
        self::assertSame([], $plain->resolveFilesInContext($refs, $frame));
        self::assertArrayHasKey($refs[0], $plain->resolveFilesInContext($refs, new WikiRenderFrame($this->eng)));
        self::assertArrayHasKey($refs[0], $plain->resolveFiles($refs));

        $provider = new WikiMilkdownRenderContext($this->context);
        self::assertNull($provider->renderContext());
        self::assertSame($this->hr, $this->context->within($this->hr, null, static fn (): ?WikiSpace => $provider->renderContext()?->space));
    }
}

/** Files by space, as a host resolver should answer: honours the frame's reach. */
final class SpaceFiles implements ContextualHostFileResolverInterface
{
    /** @var list<object|null> */
    public array $contexts = [];

    /**
     * @param array<string, WikiSpace> $files uuid => space
     */
    public function __construct(private readonly array $files)
    {
    }

    public function resolveFiles(array $references): array
    {
        return $this->resolveFilesInContext($references, null);
    }

    public function resolveFilesInContext(array $references, ?object $context): array
    {
        $this->contexts[] = $context;
        $out              = [];
        foreach ($references as $reference) {
            $uuid  = explode(':', $reference, 2)[1] ?? '';
            $space = $this->files[$uuid] ?? null;
            if ($space instanceof WikiSpace && (!$context instanceof WikiRenderFrame || $context->reaches($space))) {
                $out[$reference] = new ResolvedFile('/files/' . $uuid . '/original', '/files/' . $uuid . '/preview');
            }
        }

        return $out;
    }
}
