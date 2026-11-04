<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Markdown\Milkdown;

use InvalidArgumentException;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use Nowo\MilkdownEditorBundle\Markdown\Directive\DirectiveRendererInterface;
use Nowo\MilkdownEditorBundle\Markdown\HeadingIds;
use Nowo\MilkdownEditorBundle\Markdown\Node\LeafDirective;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\AllowlistIconResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\NullPageResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\NullUserResolver;
use Nowo\WikiBundle\DependencyInjection\WikiExtension;
use Nowo\WikiBundle\Markdown\Milkdown\MilkdownWikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\Milkdown\WikiFootnoteIds;
use Nowo\WikiBundle\Markdown\Milkdown\WikiFootnoteStartParser;
use Nowo\WikiBundle\Markdown\Milkdown\WikiHeadingIds;
use Nowo\WikiBundle\Markdown\Milkdown\WikiLeafDirectiveRenderer;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function preg_match;
use function preg_match_all;
use function str_repeat;

/**
 * 1.16.2: what Milkdown writes, through the Milkdown bridge renderer and the wiki sanitizer (Milkdown allowlist).
 */
#[CoversClass(MilkdownWikiMarkdownRenderer::class)]
#[CoversClass(WikiFootnoteIds::class)]
#[CoversClass(WikiLeafDirectiveRenderer::class)]
#[CoversClass(WikiHeadingIds::class)]
#[CoversClass(WikiFootnoteStartParser::class)]
final class MilkdownWikiReadViewTest extends TestCase
{
    public function testFootnotesRenderWithPrefixedIdsAndBackLinksThatSurviveTheSanitizer(): void
    {
        $markdown = "Claim[^1] and again[^1], other[^Big-Note].\n\n[^1]: First *note*.\n\n[^big-note]: Second.";
        $html     = $this->read($markdown);
        self::assertSame(1, preg_match('/<sup id="nowo-fn-ref-([0-9a-f]{8})-1">/', $html, $match));
        $salt = $match[1];

        self::assertStringContainsString('<sup id="nowo-fn-ref-' . $salt . '-1"><a class="nowo-md-footnote-ref" href="#nowo-fn-' . $salt . '-1" role="doc-noteref">1</a></sup>', $html);
        self::assertStringContainsString('<sup id="nowo-fn-ref-' . $salt . '-1__2"><a class="nowo-md-footnote-ref" href="#nowo-fn-' . $salt . '-1" role="doc-noteref">1</a></sup>', $html);
        self::assertStringContainsString('<sup id="nowo-fn-ref-' . $salt . '-2"><a class="nowo-md-footnote-ref" href="#nowo-fn-' . $salt . '-2" role="doc-noteref">2</a></sup>', $html);
        self::assertStringContainsString('<div class="nowo-md-footnotes" role="doc-endnotes"><hr /><ol>', $html);
        self::assertStringContainsString('<li class="nowo-md-footnote" id="nowo-fn-' . $salt . '-1" role="doc-endnote"><p>First <em>note</em>.', $html);
        self::assertStringContainsString('<a class="nowo-md-footnote-backref" href="#nowo-fn-ref-' . $salt . '-1" role="doc-backlink">↩</a>', $html);
        self::assertStringContainsString('<a class="nowo-md-footnote-backref" href="#nowo-fn-ref-' . $salt . '-1__2" role="doc-backlink">↩</a>', $html);
        self::assertStringContainsString('<li class="nowo-md-footnote" id="nowo-fn-' . $salt . '-2" role="doc-endnote"><p>Second.', $html);
        self::assertStringNotContainsString('[^', $html);
        self::assertStringNotContainsString('big-note', $html);

        // Every id of the page is a prefixed anchor, every in-page link points at one of them.
        preg_match_all('/ id="([^"]+)"/', $html, $ids);
        preg_match_all('/ href="#([^"]+)"/', $html, $links);
        self::assertCount(5, $ids[1]);
        foreach ($links[1] as $target) {
            self::assertContains($target, $ids[1]);
        }
    }

    public function testFootnoteIdsAreStablePerDocumentAndDifferBetweenDocuments(): void
    {
        $renderer = $this->renderer();

        self::assertSame($renderer->render("a[^1]\n\n[^1]: n"), $renderer->renderForViewer("a[^1]\n\n[^1]: n"));
        preg_match('/id="(nowo-fn-[^"]+)"/', $renderer->render("a[^1]\n\n[^1]: n"), $page);
        preg_match('/id="(nowo-fn-[^"]+)"/', $renderer->render("b[^1]\n\n[^1]: n"), $comment);
        self::assertNotSame($page[1], $comment[1]);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function footnoteXss(): iterable
    {
        yield 'script in note' => ["x[^1]\n\n[^1]: <script>alert(1)</script>"];
        yield 'img onerror in note' => ["x[^1]\n\n[^1]: <img src=x onerror=alert(1)>"];
        yield 'javascript link' => ["x[^1]\n\n[^1]: [click](javascript:alert(1))"];
        yield 'quote in label' => ["x[^a\"onmouseover=alert(1)]\n\n[^a\"onmouseover=alert(1)]: n"];
        yield 'markup in label' => ["x[^<svg/onload=alert(1)>]\n\n[^<svg/onload=alert(1)>]: n"];
        yield 'proto label' => ["x[^__proto__]\n\n[^__proto__]: n"];
        yield 'entity in label' => ["x[^&#106;avascript:alert(1)]\n\n[^&#106;avascript:alert(1)]: n"];
    }

    #[DataProvider('footnoteXss')]
    public function testFootnoteContentAndLabelsGoThroughTheAllowlist(string $markdown): void
    {
        $html = $this->read($markdown);

        self::assertStringContainsString('nowo-md-footnote-ref', $html);
        self::assertDoesNotMatchRegularExpression('/<(script|img|svg)\b|<[^>]*\son[a-z]+=|href="javascript:|id="[^"]*__proto__/i', $html);
        preg_match_all('/ id="([^"]+)"/', $html, $ids);
        foreach ($ids[1] as $id) {
            self::assertMatchesRegularExpression('/^nowo-fn-(?:ref-)?[0-9a-f]{8}-\d+(?:__\d+)?$/', $id);
        }
    }

    public function testFootnoteOptionsFollowTheMilkdownConfiguration(): void
    {
        self::assertStringNotContainsString('nowo-md-footnote', $this->renderer(['footnotes' => false])->render("a[^1]\n\n[^1]: n"));
        self::assertStringNotContainsString('nowo-md-footnote', $this->renderer(['footnotes' => '0'])->render("a[^1]\n\n[^1]: n"));

        $capped = $this->renderer(['max_footnote_refs' => 1])->render("a[^1] b[^1]\n\n[^1]: n");
        self::assertStringContainsString('a[^1] b[^1]', $capped);
        self::assertStringNotContainsString('nowo-md-footnote', $capped);

        // Not GFM, never written by Milkdown: inline footnotes stay text; orphans stay text.
        self::assertStringContainsString('^[inline]', $this->renderer()->render('a ^[inline]'));
        self::assertStringContainsString('a[^missing]', $this->renderer()->render('a[^missing]'));
    }

    public function testHeadingsGetTheEditorSlugsDeduplicatedAndSanitizerSafe(): void
    {
        $html = $this->read("# Getting Started\n\n## Configuración: año 2026!\n\n## Getting started\n\n### `__proto__`\n\n#### !!!\n\n##### location\n\n###### [link](/x) **bold** :tabler-home:");

        self::assertStringContainsString('<h1 id="nowo-h-getting-started">Getting Started</h1>', $html);
        self::assertStringContainsString('<h2 id="nowo-h-configuración-año-2026">', $html);
        self::assertStringContainsString('<h2 id="nowo-h-getting-started-2">Getting started</h2>', $html);
        self::assertStringContainsString('<h3 id="nowo-h-proto"><code>__proto__</code></h3>', $html);
        self::assertStringContainsString('<h4 id="nowo-h-section">!!!</h4>', $html);
        self::assertStringContainsString('<h5 id="nowo-h-location">location</h5>', $html);
        self::assertStringContainsString('<h6 id="nowo-h-link-bold">', $html);
    }

    public function testLongHeadingsAreCutLikeTheEditor(): void
    {
        $html = $this->read('## ' . str_repeat('word ', 40) . "\n\n## " . str_repeat('word ', 40));

        self::assertSame(1, preg_match('/<h2 id="(nowo-h-[^"]+)">/', $html, $first));
        self::assertLessThanOrEqual(7 + 80, mb_strlen($first[1]));
        self::assertStringContainsString('id="' . $first[1] . '-2"', $html);
    }

    public function testTableAlignmentIsAClassTheSanitizerKeeps(): void
    {
        $html = $this->read("| L | C | R | N |\n|:--|:-:|--:|---|\n| 1 | 2 | 3 | 4 |");

        self::assertStringContainsString('<th class="nowo-wiki-align--left">L</th>', $html);
        self::assertStringContainsString('<th class="nowo-wiki-align--center">C</th>', $html);
        self::assertStringContainsString('<td class="nowo-wiki-align--right">3</td>', $html);
        self::assertStringContainsString('<td>4</td>', $html);
        self::assertStringNotContainsString('align=', $html);
        self::assertStringNotContainsString('style=', $html);
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function directiveFallbacks(): iterable
    {
        yield 'beacon card' => [
            '::beacon{type=issue id=42 ref="BEA-7"}',
            '<div class="nowo-md-directive nowo-md-directive--leaf nowo-wiki-directive" data-directive="beacon"><span class="nowo-wiki-directive__text">beacon · type: issue · ref: BEA-7</span></div>',
        ];
        yield 'label wins' => [
            '::video[Demo video]{src=x}',
            '<div class="nowo-md-directive nowo-md-directive--leaf nowo-wiki-directive" data-directive="video"><span class="nowo-wiki-directive__text">Demo video</span></div>',
        ];
        yield 'bare attribute' => [
            '::toc{open .wide #main}',
            '<div class="nowo-md-directive nowo-md-directive--leaf nowo-wiki-directive" data-directive="toc"><span class="nowo-wiki-directive__text">toc · open</span></div>',
        ];
        yield 'https link' => [
            '::beacon[Issue BEA-7]{url="https://beacon.example/issues/7?x=1&y=2"}',
            '<div class="nowo-md-directive nowo-md-directive--leaf nowo-wiki-directive" data-directive="beacon"><a class="nowo-wiki-directive__link" href="https://beacon.example/issues/7?x&#61;1&amp;y&#61;2" rel="nofollow noopener">Issue BEA-7</a></div>',
        ];
        yield 'same-origin link' => [
            '::card{href=/wiki/page}',
            '<div class="nowo-md-directive nowo-md-directive--leaf nowo-wiki-directive" data-directive="card"><a class="nowo-wiki-directive__link" href="/wiki/page" rel="nofollow noopener">card</a></div>',
        ];
        yield 'second safe url attribute' => [
            '::card{url="javascript:alert(1)" link=/ok}',
            '<div class="nowo-md-directive nowo-md-directive--leaf nowo-wiki-directive" data-directive="card"><a class="nowo-wiki-directive__link" href="/ok" rel="nofollow noopener">card</a></div>',
        ];
    }

    #[DataProvider('directiveFallbacks')]
    public function testDirectivesWithoutARendererFallBackToTextOrASafeLink(string $markdown, string $expected): void
    {
        self::assertSame($expected, $this->read($markdown));
        self::assertSame($expected, $this->read($markdown, true));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function directiveXss(): iterable
    {
        yield 'javascript url' => ['::beacon{url="javascript:alert(1)"}'];
        yield 'mixed-case javascript' => ['::beacon{href="JaVaScRiPt:alert(1)"}'];
        yield 'entity javascript' => ['::beacon{url="&#106;avascript:alert(1)"}'];
        yield 'named entity colon' => ['::beacon{url="javascript&colon;alert(1)"}'];
        yield 'tab in scheme' => ["::beacon{url=\"java\tscript:alert(1)\"}"];
        yield 'leading space' => ['::beacon{url=" javascript:alert(1)"}'];
        yield 'data url' => ['::beacon{url="data:text/html,<script>alert(1)</script>"}'];
        yield 'protocol relative' => ['::beacon{url="//evil.example/x"}'];
        yield 'backslash host' => ['::beacon{url="/\\evil.example/x"}'];
        yield 'plain http' => ['::beacon{url="http://evil.example/x"}'];
        yield 'userinfo' => ['::beacon{url="https://user@evil.example/"}'];
        yield 'quote in url' => ["::beacon{url='https://a.example/\"onmouseover=\"alert(1)'}"];
        yield 'quote in value' => ["::beacon{title='\"><img src=x onerror=alert(1)>'}"];
        yield 'markup in label' => ['::beacon[<script>alert(1)</script>]{type=issue}'];
        yield 'entity in value' => ['::beacon{title="&lt;script&gt;alert(1)&lt;/script&gt;"}'];
        yield 'handler as attribute' => ['::beacon{onclick="alert(1)" style="color:red"}'];
    }

    #[DataProvider('directiveXss')]
    public function testDirectiveAttributesAreEscapedAndUnsafeUrlsDropped(string $markdown): void
    {
        foreach ([$this->renderer()->render($markdown), $this->read($markdown)] as $html) {
            self::assertStringContainsString('data-directive="beacon"', $html);
            self::assertStringNotContainsString('<a ', $html);
            self::assertStringNotContainsString('href=', $html);
            self::assertDoesNotMatchRegularExpression('/<(script|img)\b|<[^>]*\s(on[a-z]+|style)=/i', $html);
            // Text only: the only element inside the card is the text span.
            self::assertSame(1, preg_match('#^<div [^>]+><span class="nowo-wiki-directive__text">[^<]*</span></div>$#', $html), $html);
        }
    }

    public function testAHostRendererStillRendersItsDirectivesForTheViewer(): void
    {
        $host = new class implements DirectiveRendererInterface {
            public function supports(string $name): bool
            {
                return $name === 'beacon';
            }

            public function prepare(array $nodes): void
            {
            }

            public function render(LeafDirective $node): string
            {
                return '<div class="host">' . $node->attributes['id'] . '</div>';
            }
        };
        $renderer = $this->renderer([], [$host]);

        self::assertSame("<div class=\"host\">42</div>\n<div class=\"nowo-md-directive nowo-md-directive--leaf nowo-wiki-directive\" data-directive=\"other\"><span class=\"nowo-wiki-directive__text\">other</span></div>", $renderer->renderForViewer("::beacon{id=42}\n\n::other"));
        self::assertStringContainsString('beacon · ', $renderer->render('::beacon{id=42 type=issue}'));
    }

    public function testTheDirectiveRendererRefusesOtherNodes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new WikiLeafDirectiveRenderer())->render(new Paragraph(), $this->createStub(ChildNodeRendererInterface::class));
    }

    public function testTheDirectiveUrlPolicy(): void
    {
        self::assertSame('https://a.example/x', WikiLeafDirectiveRenderer::safeUrl('https://a.example/x'));
        self::assertSame('/x', WikiLeafDirectiveRenderer::safeUrl('/x'));
        self::assertNull(WikiLeafDirectiveRenderer::safeUrl('x'));
        self::assertNull(WikiLeafDirectiveRenderer::safeUrl("https://a.example/x\n"));
        self::assertNull(WikiLeafDirectiveRenderer::safeUrl('mailto:a@example.com'));
    }

    public function testManyIdenticalHeadingsRenderInLinearTimeAndIdsAreCapped(): void
    {
        // Audit EP-L1 PoC: 9 999 identical headings (90 KB, under both Milkdown limits) took ~4 s per view.
        $markdown = str_repeat("# abcdef\n", 9_999);
        $renderer = $this->renderer();
        self::assertFalse($renderer->exceedsLimits($markdown));

        $start = hrtime(true);
        $html  = $renderer->renderForViewer($markdown);
        $ms    = (hrtime(true) - $start) / 1e6;

        self::assertLessThan(1500, $ms, 'heading ids must stay linear');
        self::assertSame(WikiHeadingIds::MAX_IDS, substr_count($html, ' id="nowo-h-abcdef'));
        self::assertSame(9_999, substr_count($html, '<h1'));
        self::assertStringContainsString('<h1 id="nowo-h-abcdef">abcdef</h1>', $html);
        self::assertStringContainsString('<h1 id="nowo-h-abcdef-1000">abcdef</h1>', $html);
        self::assertStringNotContainsString('nowo-h-abcdef-1001', $html);
    }

    public function testHeadingSlugsMatchMilkdown(): void
    {
        $texts = ['Intro', 'Intro', 'Intro-2', 'Intro', 'intro 3', 'Intro', '', '!!!', 'Año', 'año', 'A-2', 'A', 'A', 'A'];

        self::assertSame(HeadingIds::slugs($texts), WikiHeadingIds::slugs($texts));
        self::assertSame(['intro', 'intro-2', 'intro-2-2', 'intro-3', 'intro-3-2', 'intro-4'], WikiHeadingIds::slugs(['Intro', 'Intro', 'Intro-2', 'Intro', 'intro 3', 'Intro']));
    }

    public function testFootnotesWithNonAsciiLabelsKeepTheirText(): void
    {
        $html = $this->read("x[^Ä] y[^ä] z[^日本]\n\n[^ä]: Note text\n\n[^日本]: Zweite Notiz");

        self::assertStringContainsString('<p>Note text', $html);
        self::assertStringContainsString('<p>Zweite Notiz', $html);
        self::assertSame(3, substr_count($html, 'role="doc-noteref"'));
        self::assertStringNotContainsString('[^', $html);

        // Not a definition: a lazy paragraph continuation, or indented code (as in league/commonmark).
        $html = $this->read("a[^ü]\nb[^ü]: lazy\n\n    [^ü]: code");
        self::assertStringContainsString('b[^ü]: lazy', $html);
        self::assertStringContainsString('<pre><code>[^ü]: code', $html);
        self::assertStringNotContainsString('nowo-md-footnote', $html);
    }

    public function testAmbiguousSameOriginPathsAreNotLinked(): void
    {
        foreach (['/.//evil.example', '/%2F%2Fevil.example', '/%2f%2fevil.example', '/./x', '/a/../b', '/..', '/x/.', '/a//b', '/x%5Cy', '/a/.?q'] as $url) {
            self::assertNull(WikiLeafDirectiveRenderer::safeUrl($url), $url);
        }
        foreach (['/wiki/page', '/wiki/v1.2/x', '/a.b/.c', '/wiki/p?a=//x&b=%2F', '/wiki/p#/./x', 'https://a.example//x'] as $url) {
            self::assertSame($url, WikiLeafDirectiveRenderer::safeUrl($url), $url);
        }
    }

    /**
     * Renders, then sanitizes with the allowlist the bundle uses for `editor.type: milkdown`.
     */
    private function read(string $markdown, bool $viewer = false): string
    {
        $renderer  = $this->renderer();
        $sanitizer = new WikiHtmlSanitizer(WikiHtmlSanitizer::DEFAULT_EMBED_HOSTS, WikiExtension::MILKDOWN_SANITIZER_ELEMENTS, WikiExtension::MILKDOWN_SANITIZER_ATTRIBUTES);

        return $sanitizer->sanitize($viewer ? $renderer->renderForViewer($markdown) : $renderer->render($markdown));
    }

    /**
     * @param array<string, mixed> $options
     * @param list<DirectiveRendererInterface> $directives
     */
    private function renderer(array $options = [], array $directives = []): MilkdownWikiMarkdownRenderer
    {
        return new MilkdownWikiMarkdownRenderer($options, new NullPageResolver(), new NullUserResolver(), new AllowlistIconResolver('/icons/tabler.svg', ['home']), $directives);
    }
}
