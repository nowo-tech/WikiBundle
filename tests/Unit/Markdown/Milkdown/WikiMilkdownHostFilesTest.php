<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Markdown\Milkdown;

use Nowo\MilkdownEditorBundle\Markdown\HostSchemes;
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
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownHostFileResolver;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownRenderContext;
use Nowo\WikiBundle\Markdown\WikiRenderContext;
use Nowo\WikiBundle\Markdown\WikiRenderFrame;
use Nowo\WikiBundle\Markdown\WikiRenderReach;
use Nowo\WikiBundle\Markdown\WikiViewerMarkdownView;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
    private function renderer(?HostFileResolverInterface $files, array $patterns = self::PATTERNS, ?TranslatorInterface $translator = null): MilkdownWikiMarkdownRenderer
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
