<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Markdown\Milkdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Node\Inline\Text;
use Nowo\MilkdownEditorBundle\Markdown\Directive\DirectiveRendererInterface;
use Nowo\MilkdownEditorBundle\Markdown\Node\LeafDirective;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\AllowlistIconResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\NullPageResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\NullUserResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\PageResolverInterface;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\ResolvedPage;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\ResolvedUser;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\UserResolverInterface;
use Nowo\WikiBundle\Markdown\Milkdown\MilkdownWikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\WikiMarkdownExtensionInterface;
use PHPUnit\Framework\TestCase;

use function str_repeat;

final class MilkdownWikiMarkdownRendererTest extends TestCase
{
    private const PAGE = '0b6f4f0e-3c55-4d0a-9a43-1f7a2c9e8d10';

    public function testNeutralRenderNeverResolvesPagesMentionsOrHostBlocks(): void
    {
        $pages     = $this->createMock(PageResolverInterface::class);
        $users     = $this->createMock(UserResolverInterface::class);
        $directive = $this->directive();
        $pages->expects(self::never())->method('resolvePages');
        $users->expects(self::never())->method('resolveUsers');

        $html = $this->renderer([], $pages, $users, [$directive])->render(
            'See [[wiki:' . self::PAGE . "]] and @ana_b :tabler-home: :tabler-nope:\n\n::beacon{type=issue}\n",
        );

        self::assertStringContainsString('<span class="nowo-md-ref nowo-md-ref--hidden">Content not visible</span>', $html);
        self::assertStringContainsString('@ana_b', $html);
        self::assertStringNotContainsString('nowo-md-mention', $html);
        self::assertStringContainsString('<span class="nowo-md-icon" data-icon="home" role="img" aria-label="home"></span>', $html);
        self::assertStringContainsString(':tabler-nope:', $html);
        self::assertStringContainsString('data-directive="beacon"', $html);
        self::assertStringNotContainsString('HOST BLOCK', $html);
        self::assertStringNotContainsString('[[wiki:', $html);
    }

    public function testViewerRenderResolvesPagesMentionsAndHostBlocks(): void
    {
        $pages = new class implements PageResolverInterface {
            public function resolvePages(array $ids): array
            {
                return [MilkdownWikiMarkdownRendererTest::pageId() => new ResolvedPage('Install <guide>', '/wiki/install')];
            }
        };
        $users = new class implements UserResolverInterface {
            public function resolveUsers(array $handles): array
            {
                return ['ana' => new ResolvedUser('Ana', '/people/ana')];
            }
        };

        $html = $this->renderer([], $pages, $users, [$this->directive()])->renderForViewer(
            'See [[wiki:' . self::PAGE . "#setup]] by @ana\n\n::beacon{type=issue}\n",
        );

        self::assertStringContainsString('<a class="nowo-md-ref" href="/wiki/install#setup">Install &lt;guide&gt; › setup</a>', $html);
        self::assertStringContainsString('<a class="nowo-md-mention" href="/people/ana" title="Ana">@ana</a>', $html);
        self::assertStringContainsString('HOST BLOCK', $html);
    }

    public function testDialectGfmAndSafetySettings(): void
    {
        $html = $this->renderer()->render(
            ":::callout{type=warning}\nCareful\n:::\n\n:mark[hot]{color=green}\n\nInline \$x^2\$\n\n```mermaid\ngraph TD; A-->B\n```\n\n"
            . "- [x] done\n- [ ] todo\n\n<script>alert(1)</script>\n\n![pixel](https://evil.example/p.png)\n\n[bad](javascript:alert(1))\n\n| a | b |\n|---|---|\n| 1 | 2 |",
        );

        self::assertStringContainsString('<div class="nowo-md-callout nowo-md-callout--warning" role="note">', $html);
        self::assertStringContainsString('<mark class="nowo-md-mark nowo-md-mark--yellow">hot</mark>', $html);
        self::assertStringContainsString('<span class="nowo-md-math" data-math="inline">x^2</span>', $html);
        self::assertStringContainsString('<code class="language-mermaid">', $html);
        self::assertStringContainsString('<ul data-type="taskList">', $html);
        self::assertStringContainsString('<li data-type="taskItem" data-checked="true">done</li>', $html);
        self::assertStringNotContainsString('<input', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('<a href="https://evil.example/p.png">pixel</a>', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringContainsString('<table>', $html);
    }

    public function testOptionsAreReadFromTheMilkdownConfiguration(): void
    {
        $renderer = $this->renderer([
            'html_input'          => 'strip',
            'allow_remote_images' => true,
            'math'                => false,
            'mark_colors'         => ['green', 7],
            'max_nesting_level'   => 'x',
        ]);
        $html = $renderer->render("<b>raw</b> \$x\$ :mark[hi]{color=green}\n\n![p](https://cdn.example/p.png)");

        self::assertStringNotContainsString('raw</b>', $html);
        self::assertStringNotContainsString('&lt;b&gt;', $html);
        self::assertStringContainsString('$x$', $html);
        self::assertStringContainsString('nowo-md-mark--green', $html);
        self::assertStringContainsString('<img src="https://cdn.example/p.png" alt="p" />', $html);

        self::assertStringContainsString('&lt;b&gt;', $this->renderer(['html_input' => 'allow', 'mark_colors' => 'red'])->render('<b>x</b> :mark[y]'));
        self::assertStringContainsString('nowo-md-mark--yellow', $this->renderer(['mark_colors' => 'red'])->render(':mark[y]'));
    }

    public function testInputOverTheMilkdownLimitsIsEscapedTextWithoutParsing(): void
    {
        $renderer = $this->renderer(['max_input_length' => 20, 'max_inline_markers' => 4]);

        self::assertSame('<p>**this is longer than twenty**</p>', $renderer->render('**this is longer than twenty**'));
        self::assertSame("<p>*a* *b*<br>\n*c*</p>", $renderer->renderForViewer("*a* *b*\r\n*c*"));
        self::assertTrue($renderer->exceedsLimits('**this is longer than twenty**'));
        self::assertFalse($renderer->exceedsLimits('*ok*'));
        self::assertSame('<p><em>ok</em></p>', $renderer->render('*ok*'));
        self::assertSame(['pages' => [], 'mentions' => []], $renderer->references('@a @b @c @d @e'));

        $defaults = $this->renderer(['max_input_length' => -1, 'max_inline_markers' => 0]);
        self::assertFalse($defaults->exceedsLimits(str_repeat('a', 99999)));
        self::assertTrue($defaults->exceedsLimits(str_repeat('a', 100001)));
    }

    public function testEnvironmentValuesAsStringsAreRead(): void
    {
        self::assertTrue($this->renderer(['max_input_length' => '20'])->exceedsLimits(str_repeat('a', 21)));
        $renderer = $this->renderer(['allow_remote_images' => '1', 'math' => '0']);

        self::assertStringContainsString('<img src="https://cdn.example/p.png"', $renderer->render('![p](https://cdn.example/p.png)'));
        self::assertStringContainsString('$x$', $renderer->render('$x$'));
    }

    public function testEmptyInputRendersNothing(): void
    {
        self::assertSame('', $this->renderer()->render("  \r\n "));
        self::assertSame('', $this->renderer()->renderForViewer(''));
        self::assertSame(['pages' => [], 'mentions' => []], $this->renderer()->references(' '));
    }

    public function testReferencesComeFromTheParser(): void
    {
        $other    = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
        $markdown = '[[wiki:' . self::PAGE . ']] [[wiki:' . self::PAGE . '#a]] [[wiki:' . self::PAGE . "]] @ana mail@example.com (@bob)\n\n"
            . "`[[wiki:{$other}]] @carl`\n\n```\n[[wiki:{$other}]] @dan\n```\n\n[[wiki:" . $other . ']] @ana';

        self::assertSame([
            'pages' => [
                ['id' => self::PAGE, 'anchor' => null],
                ['id' => self::PAGE, 'anchor' => 'a'],
                ['id' => $other, 'anchor' => null],
            ],
            'mentions' => ['ana', 'bob'],
        ], $this->renderer()->references($markdown));

        self::assertSame([
            'pages'    => [['id' => self::PAGE, 'anchor' => null]],
            'mentions' => ['ana'],
        ], $this->renderer(['max_references' => 1])->references($markdown));
    }

    public function testHostExtensionsAreAdded(): void
    {
        $extension = new class implements WikiMarkdownExtensionInterface {
            public function register(EnvironmentBuilderInterface $environment): void
            {
                $environment->addEventListener(DocumentParsedEvent::class, static function (DocumentParsedEvent $event): void {
                    $event->getDocument()->firstChild()?->appendChild(new Text(' [ext]'));
                });
            }
        };

        self::assertSame('<p>Hello [ext]</p>', $this->renderer([], null, null, [], [$extension])->render('Hello'));
    }

    public static function pageId(): string
    {
        return self::PAGE;
    }

    /**
     * @param array<string, mixed> $options
     * @param list<DirectiveRendererInterface> $directives
     * @param list<WikiMarkdownExtensionInterface> $extensions
     */
    private function renderer(
        array $options = [],
        ?PageResolverInterface $pages = null,
        ?UserResolverInterface $users = null,
        array $directives = [],
        array $extensions = [],
    ): MilkdownWikiMarkdownRenderer {
        return new MilkdownWikiMarkdownRenderer(
            $options,
            $pages ?? new NullPageResolver(),
            $users ?? new NullUserResolver(),
            new AllowlistIconResolver('/icons/tabler.svg', ['home']),
            $directives,
            $extensions,
        );
    }

    private function directive(): DirectiveRendererInterface
    {
        return new class implements DirectiveRendererInterface {
            public function supports(string $name): bool
            {
                return $name === 'beacon';
            }

            public function prepare(array $nodes): void
            {
            }

            public function render(LeafDirective $node): string
            {
                return '<div>HOST BLOCK</div>';
            }
        };
    }
}
