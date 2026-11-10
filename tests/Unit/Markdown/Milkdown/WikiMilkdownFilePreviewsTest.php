<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Markdown\Milkdown;

use Nowo\MilkdownEditorBundle\Markdown\HostSchemes;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\AllowlistIconResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\ContextualHostFileResolverInterface;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\FilePreviewResolverInterface;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\FileReference;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\NullPageResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\NullUserResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\ResolvedFile;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\TemplateFilePreviewResolver;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Markdown\Milkdown\MilkdownWikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\Milkdown\WikiFilePreviewFrames;
use Nowo\WikiBundle\Markdown\Milkdown\WikiLeafDirectiveRenderer;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownFilePreviewResolver;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownRenderContext;
use Nowo\WikiBundle\Markdown\WikiRenderContext;
use Nowo\WikiBundle\Markdown\WikiRenderFrame;
use Nowo\WikiBundle\Markdown\WikiRenderReach;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Twig\WikiHtmlTwigExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_map;
use function explode;
use function range;
use function str_repeat;
use function str_replace;
use function str_starts_with;
use function substr_count;

/**
 * 1.28 (Symfony Beacon spec 212 block B): with `file_cards: card`, a `view="preview"` card of the per-viewer render
 * frames the preview the host's `FilePreviewResolverInterface` approved, sandboxed, lazy and without referrer. The frame
 * passes the wiki sanitizer as a placeholder only this request can fill; nothing else is ever framed.
 */
#[CoversClass(MilkdownWikiMarkdownRenderer::class)]
#[CoversClass(WikiLeafDirectiveRenderer::class)]
#[CoversClass(WikiMilkdownFilePreviewResolver::class)]
#[CoversClass(WikiFilePreviewFrames::class)]
#[CoversClass(WikiHtmlTwigExtension::class)]
final class WikiMilkdownFilePreviewsTest extends TestCase
{
    private const ENG_FILE = '0f8fad5b-d9cb-469f-a165-70867728950e';

    private const HR_FILE = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

    private const SCHEMES = ['beacon-file' => ['file' => '/files/{id}/original', 'image' => '/files/{id}/preview']];

    private const PATTERNS = [['pattern' => '^/files/(?<id>[0-9a-f-]{36})/(?:original|preview)$', 'scheme' => 'beacon-file']];

    private WikiSpace $eng;

    private WikiSpace $hr;

    private WikiPage $page;

    private WikiRenderContext $context;

    private WikiFilePreviewFrames $frames;

    protected function setUp(): void
    {
        $this->eng     = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->hr      = new WikiSpace('hr', 'People', WikiSpaceOwnerScope::Team, 'team-1');
        $this->page    = new WikiPage($this->eng, 'runbook', 'Runbook');
        $this->context = new WikiRenderContext();
        $this->frames  = new WikiFilePreviewFrames();
    }

    /**
     * @param array<string, mixed> $options
     */
    private function renderer(?FilePreviewResolverInterface $previews, array $options = [], bool $fileCards = true, bool $frames = true): MilkdownWikiMarkdownRenderer
    {
        return new MilkdownWikiMarkdownRenderer(
            $options,
            new NullPageResolver(),
            new NullUserResolver(),
            new AllowlistIconResolver(),
            hostSchemes: new HostSchemes(self::SCHEMES, [], self::PATTERNS),
            hostFiles: new VisibleSpaceFiles([self::ENG_FILE => $this->eng, self::HR_FILE => $this->hr]),
            renderContext: new WikiMilkdownRenderContext($this->context),
            fileCards: $fileCards,
            filePreviews: $previews,
            previewFrames: $frames ? $this->frames : null,
        );
    }

    private function card(string $uuid, string $name = 'data.csv'): string
    {
        return '::file[' . $name . ']{href="/files/' . $uuid . '/original" view="preview"}';
    }

    /** The page view: render for the viewer inside the page's frame, then `nowo_wiki_safe_html`. */
    private function page(MilkdownWikiMarkdownRenderer $renderer, string $markdown, ?WikiRenderReach $reach = null): string
    {
        $html = $this->context->within($this->eng, $this->page, static fn (): string => $renderer->renderForViewer($markdown), $reach);

        return (new WikiHtmlTwigExtension(new WikiHtmlSanitizer(), previewFrames: $this->frames))->sanitize((new WikiHtmlSanitizer())->sanitize($html));
    }

    public function testAnApprovedPreviewIsFramedAfterTheSanitizer(): void
    {
        $previews = new BeaconLikePreviews($this->context);
        $renderer = $this->renderer($previews, ['file_preview_sandbox' => ['allow-downloads']]);

        $raw = $this->context->within($this->eng, $this->page, fn (): string => $renderer->renderForViewer($this->card(self::ENG_FILE)));
        self::assertStringNotContainsString('<iframe', $raw);
        self::assertSame(1, substr_count($raw, 'class="nowo-wiki-file-frame nowo-wiki-file-frame--'));

        $html = $this->page($renderer, $this->card(self::ENG_FILE));
        self::assertStringContainsString('<iframe class="nowo-md-file__frame" src="/files/' . self::ENG_FILE . '/preview" title="data.csv" sandbox="allow-downloads" loading="lazy" referrerpolicy="no-referrer" height="480"></iframe>', $html);
        self::assertStringContainsString('class="nowo-md-file__open" href="/files/' . self::ENG_FILE . '/original"', $html);
        self::assertStringNotContainsString('nowo-wiki-file-frame', $html);
        // The resolver saw the card inside the page's frame.
        self::assertInstanceOf(WikiRenderFrame::class, $previews->frames[0]);
        self::assertSame($this->page, $previews->frames[0]->page);
    }

    public function testScriptsNeverGetTheSameOrigin(): void
    {
        $html = $this->page($this->renderer(new BeaconLikePreviews(), ['file_preview_sandbox' => ['allow-scripts', 'allow-same-origin', 'allow-popups-to-escape-sandbox', 'bogus']]), $this->card(self::ENG_FILE));
        self::assertStringContainsString('sandbox="allow-scripts"', $html);
        self::assertStringContainsString('<iframe', $this->page($this->renderer(new BeaconLikePreviews(), ['file_preview_sandbox' => 'allow-scripts']), $this->card(self::ENG_FILE)));
        self::assertStringContainsString('sandbox=""', $this->page($this->renderer(new BeaconLikePreviews(), ['file_preview_sandbox' => 'allow-scripts']), $this->card(self::ENG_FILE)));
    }

    public function testOnlyResolverApprovedSafeUrlsAreFramed(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html,x', '//evil.example/x', 'http://plain.example/x', '/.//evil.example/x', ''] as $url) {
            $html = $this->page($this->renderer(new FixedPreviews($url)), $this->card(self::ENG_FILE));
            self::assertStringNotContainsString('<iframe', $html, $url);
            self::assertStringContainsString('nowo-md-file__link', $html, $url);
        }
        // A card the viewer may not see is never asked about.
        $previews = new BeaconLikePreviews();
        $html     = $this->page($this->renderer($previews), $this->card(self::HR_FILE, 'secret.csv'), $this->onlyEng());
        self::assertStringNotContainsString('<iframe', $html);
        self::assertStringNotContainsString(self::HR_FILE, $html);
        self::assertSame([], $previews->asked);
    }

    public function testUnderAReachOnlyHostReferenceCardsAreAsked(): void
    {
        $previews = new BeaconLikePreviews();
        $html     = $this->page($this->renderer($previews), $this->card(self::ENG_FILE) . "\n\n::file[x.csv]{href=\"/docs/x.csv\" view=\"preview\"}", $this->onlyEng());
        self::assertSame(['/files/' . self::ENG_FILE . '/original'], $previews->asked);
        self::assertSame(1, substr_count($html, '<iframe'));

        // Without a reach (the UI), plain URL cards are asked too.
        $previews = new BeaconLikePreviews();
        $this->page($this->renderer($previews), '::file[x.csv]{href="/docs/x.csv" view="preview"}');
        self::assertSame(['/docs/x.csv'], $previews->asked);

        // Under a reach, nothing left to ask means no call at all.
        $previews = new BeaconLikePreviews();
        $this->page($this->renderer($previews), '::file[x.csv]{href="/docs/x.csv" view="preview"}', $this->onlyEng());
        self::assertSame([], $previews->asked);
    }

    public function testDefaultsDoNotChange(): void
    {
        $card = $this->card(self::ENG_FILE);
        // No frames service (file_previews off) or file_cards: link: the resolver is never asked, the card is the 1.27 card.
        $previews = new BeaconLikePreviews();
        $plain    = $this->page($this->renderer(null), $card);
        self::assertSame($plain, $this->page($this->renderer($previews, frames: false), $card));
        self::assertSame([], $previews->asked);
        self::assertStringNotContainsString('<iframe', $plain);
        $this->page($this->renderer($previews, fileCards: false), $card);
        self::assertSame([], $previews->asked);
        // Milkdown's default resolver without a preview template answers nothing: the same card.
        self::assertSame($plain, $this->page($this->renderer(new TemplateFilePreviewResolver(new HostSchemes(self::SCHEMES, [], self::PATTERNS))), $card));
        // The neutral render (content_html) never asks.
        self::assertStringNotContainsString('nowo-md-file', $this->renderer($previews)->render($card));
        self::assertSame([], $previews->asked);
        // A failing resolver leaves the card.
        self::assertStringNotContainsString('<iframe', $this->page($this->renderer(new FixedPreviews(null)), $card));
    }

    public function testForgedPlaceholdersAreRemoved(): void
    {
        $twig = new WikiHtmlTwigExtension(new WikiHtmlSanitizer(), previewFrames: $this->frames);
        $fake = '<p>a</p><span class="nowo-wiki-file-frame nowo-wiki-file-frame--' . str_repeat('a', 32) . '"></span><iframe src="https://evil.example"></iframe>';
        self::assertSame('<p>a</p>', $twig->sanitize($fake));
        self::assertSame('<p>b</p>', $twig->sanitize('<p>b</p>'));
        self::assertSame('', $twig->sanitize(null));
    }

    public function testTheFramesStore(): void
    {
        $frame = '<iframe class="nowo-md-file__frame" src="/p?a=1&amp;b=2" title="a &quot;b&quot;" sandbox="" loading="lazy" referrerpolicy="no-referrer" height="320"></iframe>';
        $place = $this->frames->placeholder($frame);
        self::assertNotNull($place);
        self::assertSame('<div>' . $frame . '</div>', $this->frames->hydrate('<div>' . $place . '</div>'));
        // Only Milkdown's frame markup is kept.
        foreach ([
            '<iframe class="nowo-md-file__frame" src="/p" title="a" sandbox="" loading="lazy" referrerpolicy="no-referrer" height="320" onload="x()"></iframe>',
            '<iframe class="nowo-md-file__frame" src="/p" title="a" sandbox="allow-scripts allow-same-origin" loading="lazy" height="320"></iframe>',
            '<script>x()</script>',
        ] as $bad) {
            self::assertNull($this->frames->placeholder($bad));
        }
        // Bounded, and emptied between requests.
        $frames = new WikiFilePreviewFrames();
        $tokens = array_map(static fn (): ?string => $frames->placeholder($frame), range(1, WikiFilePreviewFrames::MAX_FRAMES));
        self::assertNotContains(null, $tokens);
        self::assertNull($frames->placeholder($frame));
        $frames->reset();
        self::assertSame('', $frames->hydrate((string) $tokens[0]));
        self::assertNotNull($frames->placeholder($frame));
    }

    public function testAFullStoreLeavesThePlainCard(): void
    {
        $frame = '<iframe class="nowo-md-file__frame" src="/p" title="a" sandbox="" loading="lazy" referrerpolicy="no-referrer" height="320"></iframe>';
        for ($i = 0; $i < WikiFilePreviewFrames::MAX_FRAMES; ++$i) {
            $this->frames->placeholder($frame);
        }
        $html = $this->page($this->renderer(new BeaconLikePreviews()), $this->card(self::ENG_FILE));
        self::assertStringNotContainsString('<iframe', $html);
        self::assertStringContainsString('<div class="nowo-md-file nowo-md-file--sheet"><a class="nowo-md-file__link"', $html);
    }

    public function testTheWrapperKeepsOnlyAnswersAboutAskedCards(): void
    {
        $wrapper = new WikiMilkdownFilePreviewResolver(new FixedPreviews('/p', ['/other' => '/q', '/files/x' => 42]));
        self::assertSame(['/files/x/original' => '/p'], $wrapper->resolvePreviews([new FileReference('/files/x/original', '/files/x/original', 'a')]));
        self::assertSame([], $wrapper->resolvePreviews([]));
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
}

/** A host file resolver that honours the frame's reach (as Beacon's StoredHostFileResolver does). */
final readonly class VisibleSpaceFiles implements ContextualHostFileResolverInterface
{
    /**
     * @param array<string, WikiSpace> $files uuid => space
     */
    public function __construct(private array $files)
    {
    }

    public function resolveFiles(array $references): array
    {
        return $this->resolveFilesInContext($references, null);
    }

    public function resolveFilesInContext(array $references, ?object $context): array
    {
        $out = [];
        foreach ($references as $reference) {
            $uuid  = explode(':', $reference, 2)[1] ?? '';
            $space = $this->files[$uuid] ?? null;
            if ($space instanceof WikiSpace && (!$context instanceof WikiRenderFrame || $context->reaches($space))) {
                $out[$reference] = new ResolvedFile('/files/' . $uuid . '/original');
            }
        }

        return $out;
    }
}

/** Like Beacon's BeaconFilePreviewResolver: `/files/<uuid>/original` cards preview `/files/<uuid>/preview`. */
final class BeaconLikePreviews implements FilePreviewResolverInterface
{
    /** @var list<string> */
    public array $asked = [];

    /** @var list<WikiRenderFrame|null> */
    public array $frames = [];

    public function __construct(private readonly ?WikiRenderContext $context = null)
    {
    }

    public function resolvePreviews(array $files): array
    {
        $out = [];
        foreach ($files as $file) {
            $this->asked[]    = $file->href;
            $out[$file->href] = str_starts_with($file->href, '/files/') ? str_replace('/original', '/preview', $file->href) : '/docs/preview';
        }
        $this->frames[] = $this->context?->current();

        return $out;
    }
}

/** Answers one URL for every card (null: throws), plus extra answers. */
final readonly class FixedPreviews implements FilePreviewResolverInterface
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(private ?string $url, private array $extra = [])
    {
    }

    public function resolvePreviews(array $files): array
    {
        if ($this->url === null) {
            throw new RuntimeException('preview store down');
        }
        $out = $this->extra;
        foreach ($files as $file) {
            $out[$file->href] = $this->url;
        }

        /* @var array<string, string> $out */
        return $out;
    }
}
