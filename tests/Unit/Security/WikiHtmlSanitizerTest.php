<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Security;

use InvalidArgumentException;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function substr_count;

final class WikiHtmlSanitizerTest extends TestCase
{
    public function testStripsScriptTags(): void
    {
        $sanitizer = new WikiHtmlSanitizer();
        $result    = $sanitizer->sanitize('<p>Hi</p><script>alert(1)</script>');

        self::assertStringNotContainsString('script', $result);
        self::assertStringContainsString('<p>Hi</p>', $result);
    }

    /**
     * 1.31 (MilkdownEditorBundle 1.17): the task checkbox of the server render is kept, rebuilt from a fixed set.
     */
    public function testKeepsTheTaskCheckboxOfMilkdown117(): void
    {
        $html = '<ul><li class="nowo-milkdown-task" data-checked="true"><input type="checkbox" class="nowo-milkdown-task__box" disabled="" checked="" aria-label="Done"><div class="nowo-milkdown-task__content"><p>Ship</p></div></li>'
            . '<li class="nowo-milkdown-task" data-checked="false"><input type="checkbox" class="nowo-milkdown-task__box" disabled="" aria-label="Hecho &quot;ya&quot;"><div class="nowo-milkdown-task__content">Test</div></li></ul>';

        self::assertSame(
            '<ul><li class="nowo-milkdown-task" data-checked="true"><input type="checkbox" class="nowo-milkdown-task__box" disabled checked aria-label="Done" /><div class="nowo-milkdown-task__content"><p>Ship</p></div></li>'
            . '<li class="nowo-milkdown-task" data-checked="false"><input type="checkbox" class="nowo-milkdown-task__box" disabled aria-label="Hecho &#34;ya&#34;" /><div class="nowo-milkdown-task__content">Test</div></li></ul>',
            (new WikiHtmlSanitizer())->sanitize($html),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function hostileInputs(): iterable
    {
        yield 'text input with a handler' => ['<li class="nowo-milkdown-task"><input type=text onfocus="alert(1)" autofocus name="n" value="v" style="x" form="f"><div>a</div></li>', '<li class="nowo-milkdown-task"><input type="checkbox" class="nowo-milkdown-task__box" disabled /><div>a</div></li>'];
        yield 'image input with src and formaction' => ['<li class="nowo-milkdown-task"><input type=image src="https://evil.test/x.png" formaction="javascript:alert(1)" checked><div>a</div></li>', '<li class="nowo-milkdown-task"><input type="checkbox" class="nowo-milkdown-task__box" disabled checked /><div>a</div></li>'];
        yield 'input outside a task item' => ['<p><input type="checkbox" class="nowo-milkdown-task__box" disabled checked>x</p>', '<p>x</p>'];
        yield 'input in a plain item' => ['<ul><li class="other"><input type="checkbox" disabled>x</li></ul>', '<ul><li class="other">x</li></ul>'];
        yield 'input in a task-like class' => ['<ul><li class="nowo-milkdown-task-x"><input type="checkbox">x</li></ul>', '<ul><li class="nowo-milkdown-task-x">x</li></ul>'];
        yield 'input after the content' => ['<ul><li class="nowo-milkdown-task"><div>a</div><input type="checkbox"></li></ul>', '<ul><li class="nowo-milkdown-task"><div>a</div></li></ul>'];
        yield 'bare input' => ['<input type="hidden" name="csrf" value="x"><input>', ''];
        yield 'attribute breaking in the label' => ['<li class="nowo-milkdown-task"><input type="checkbox" aria-label=\'a" onmouseover="alert(1)" checked x="\'><div>a</div></li>', '<li class="nowo-milkdown-task"><input type="checkbox" class="nowo-milkdown-task__box" disabled aria-label="a&#34; onmouseover&#61;&#34;alert(1)&#34; checked x&#61;&#34;" /><div>a</div></li>'];
        yield 'attribute breaking in the class' => ['<li class="nowo-milkdown-task"><input class=\'x" checked onfocus="y\' aria-label="ok"><div>a</div></li>', '<li class="nowo-milkdown-task"><input type="checkbox" class="nowo-milkdown-task__box" disabled aria-label="ok" /><div>a</div></li>'];
        yield 'tag breaking in the label' => ['<li class="nowo-milkdown-task"><input aria-label="</li><img src=x onerror=alert(1)>"><div>a</div></li>', '<li class="nowo-milkdown-task"><input type="checkbox" class="nowo-milkdown-task__box" disabled aria-label="&lt;/li&gt;&lt;img src&#61;x onerror&#61;alert(1)&gt;" /><div>a</div></li>'];
    }

    #[DataProvider('hostileInputs')]
    public function testAnInputIsOnlyEverADisabledTaskCheckbox(string $html, string $expected): void
    {
        $result = (new WikiHtmlSanitizer())->sanitize($html);

        self::assertSame($expected, $result);
        self::assertDoesNotMatchRegularExpression('#<[^>]*\s(?:on[a-z]+|name|value|form|formaction|style|src)=#i', $result);
    }

    /**
     * Audit NB-W-2: the auditor's fuzz payloads, pinned to the exact output, so a symfony/html-sanitizer upgrade that
     * changes the canonical form the task-box post-pass relies on is caught.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function fuzzedTaskBoxes(): iterable
    {
        $box = '<input type="checkbox" class="nowo-milkdown-task__box" disabled';
        yield 'upper case' => ['<LI CLASS="nowo-milkdown-task"><INPUT TYPE=TEXT FORMACTION=javascript:1 ONFOCUS=alert(1) AUTOFOCUS><div>a</div></LI>', '<li class="nowo-milkdown-task">' . $box . ' /><div>a</div></li>'];
        yield 'unquoted' => ['<li class=nowo-milkdown-task><input type=text onfocus=alert(1) autofocus formaction=x><div>a</div></li>', '<li class="nowo-milkdown-task">' . $box . ' /><div>a</div></li>'];
        yield 'two inputs in one item' => ['<ul><li class="nowo-milkdown-task"><input type="checkbox" checked><input type="text" onfocus="x"><div>a</div></li></ul>', '<ul><li class="nowo-milkdown-task">' . $box . ' checked /><div>a</div></li></ul>'];
        yield 'not the first child' => ['<ul><li class="nowo-milkdown-task"><div>a</div><input type="checkbox"></li></ul>', '<ul><li class="nowo-milkdown-task"><div>a</div></li></ul>'];
        yield 'comment before the input (the sanitizer drops it)' => ['<ul><li class="nowo-milkdown-task"><!-- c --><input type="checkbox" checked><div>a</div></li></ul>', '<ul><li class="nowo-milkdown-task">' . $box . ' checked /><div>a</div></li></ul>'];
        yield 'CDATA' => ['<ul><li class="nowo-milkdown-task"><![CDATA[<input type="checkbox">]]><div>a</div></li></ul>', '<ul><li class="nowo-milkdown-task"><div>a</div></li></ul>'];
        yield '<input in attribute values' => ['<p title="<input type=checkbox>">x</p><ul><li class="nowo-milkdown-task" title="<input checked>"><div>a</div></li></ul>', '<p title="&lt;input type&#61;checkbox&gt;">x</p><ul><li class="nowo-milkdown-task" title="&lt;input checked&gt;"><div>a</div></li></ul>'];
        yield '<input in text and pre' => ['<p>&lt;input type="checkbox"&gt; and <code>&lt;input&gt;</code></p><pre>&lt;input checked&gt;</pre>', '<p>&lt;input type&#61;&#34;checkbox&#34;&gt; and <code>&lt;input&gt;</code></p><pre>&lt;input checked&gt;</pre>'];
        yield 'task class inside a title' => ['<ul><li title=\' class="nowo-milkdown-task"\'><input type="checkbox"><div>a</div></li></ul>', '<ul><li title=" class&#61;&#34;nowo-milkdown-task&#34;"><div>a</div></li></ul>'];
        yield 'task class with a suffix' => ['<ul><li class="nowo-milkdown-task-evil"><input type="checkbox"><div>a</div></li></ul>', '<ul><li class="nowo-milkdown-task-evil"><div>a</div></li></ul>'];
        yield 'task class among others (kept)' => ['<ul><li class="a nowo-milkdown-task b"><input type="checkbox" checked aria-label="Done"><div>a</div></li></ul>', '<ul><li class="a nowo-milkdown-task b">' . $box . ' checked aria-label="Done" /><div>a</div></li></ul>'];
        yield 'nested lists' => ['<ul><li class="nowo-milkdown-task"><input type="checkbox"><div>a<ul><li class="nowo-milkdown-task"><input type="checkbox" checked><div>b</div></li><li><input type="checkbox"></li></ul></div></li></ul>', '<ul><li class="nowo-milkdown-task">' . $box . ' /><div>a<ul><li class="nowo-milkdown-task">' . $box . ' checked /><div>b</div></li><li></li></ul></div></li></ul>'];
        yield 'svg wrapper' => ['<ul><li class="nowo-milkdown-task"><svg><input type="checkbox"></svg><div>a</div></li></ul>', '<ul><li class="nowo-milkdown-task"><div>a</div></li></ul>'];
        yield 'math wrapper' => ['<ul><li class="nowo-milkdown-task"><math><input type="checkbox"></math><div>a</div></li></ul>', '<ul><li class="nowo-milkdown-task"><div>a</div></li></ul>'];
        yield 'textarea wrapper' => ['<ul><li class="nowo-milkdown-task"><textarea><input type="checkbox"></textarea><div>a</div></li></ul>', '<ul><li class="nowo-milkdown-task"><div>a</div></li></ul>'];
        yield 'label with quotes, >, entities and NUL' => ["<ul><li class=\"nowo-milkdown-task\"><input aria-label=\"a&quot;b'c>d &amp; &#x3c;e\0f\"><div>a</div></li></ul>", '<ul><li class="nowo-milkdown-task">' . $box . " aria-label=\"a&#34;b&#039;c&gt;d &amp; &lt;e\u{FFFD}f\" /><div>a</div></li></ul>"];
        yield 'checked only as a value' => ['<ul><li class="nowo-milkdown-task"><input disabled="checked" class="checked"><div>a</div></li></ul>', '<ul><li class="nowo-milkdown-task">' . $box . ' /><div>a</div></li></ul>'];
    }

    #[DataProvider('fuzzedTaskBoxes')]
    public function testTheTaskBoxFuzzPayloads(string $html, string $expected): void
    {
        $result = (new WikiHtmlSanitizer())->sanitize($html);

        self::assertSame($expected, $result);
        self::assertDoesNotMatchRegularExpression('#<input\b[^>]*\s(?:on[a-z]+|name|value|form|formaction|style|src|autofocus|title)\b#i', $result);
        self::assertSame(substr_count($result, '<input'), substr_count($result, '<input type="checkbox" class="nowo-milkdown-task__box" disabled'));
    }

    /** Audit NB-W-1: the renderers' reference classes leave authored HTML; other classes and attributes stay. */
    public function testWithoutServerClasses(): void
    {
        self::assertSame(
            '<p>a<span>hidden</span> <span class="x">b</span> <a href="/p" class="keep nowo-md-reference">c</a> <span data-class="nowo-md-ref">d</span></p>',
            WikiHtmlSanitizer::withoutServerClasses('<p>a<span class="nowo-md-ref nowo-md-ref--hidden">hidden</span> <span class="nowo-md-ref--page x">b</span> <a href="/p" class="keep nowo-md-reference">c</a> <span data-class="nowo-md-ref">d</span></p>'),
        );
        self::assertSame('<p>plain</p>', WikiHtmlSanitizer::withoutServerClasses('<p>plain</p>'));
    }

    public function testAHostCannotAllowInputs(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new WikiHtmlSanitizer(extraAllowedElements: ['input' => ['value']]);
    }

    public function testStripsEventHandlers(): void
    {
        $sanitizer = new WikiHtmlSanitizer();
        $result    = $sanitizer->sanitize('<p onclick="evil()">x</p>');

        self::assertStringNotContainsString('onclick', $result);
    }

    public function testAllowsTrustedIframeHosts(): void
    {
        $sanitizer = new WikiHtmlSanitizer();
        $iframe    = '<iframe src="https://www.youtube.com/embed/demo"></iframe>';
        $result    = $sanitizer->sanitize($iframe);

        self::assertStringContainsString('youtube.com', $result);
    }

    public function testStripsUntrustedIframeHosts(): void
    {
        $sanitizer = new WikiHtmlSanitizer();
        $result    = $sanitizer->sanitize('<iframe src="https://evil.example/embed"></iframe>');

        self::assertStringNotContainsString('iframe', $result);
    }

    public function testStripsIframeWithoutSrc(): void
    {
        $sanitizer = new WikiHtmlSanitizer();
        $result    = $sanitizer->sanitize('<iframe></iframe>');

        self::assertStringNotContainsString('iframe', $result);
    }

    /**
     * Regression payloads that bypassed the former regex/strip_tags implementation.
     *
     * @return iterable<string, array{0: string, 1: list<string>}>
     */
    public static function xssPayloadProvider(): iterable
    {
        yield 'unquoted event handler' => ['<img src=x onerror=alert(1)>', ['onerror', 'alert']];
        yield 'unquoted javascript href' => ['<a href=javascript:alert(1)>x</a>', ['javascript', 'alert']];
        yield 'entity-encoded javascript href' => ['<a href="&#106;avascript:alert(1)">x</a>', ['avascript', 'alert']];
        yield 'hex-entity javascript href' => ['<a href="&#x6A;avascript&#x3A;alert(1)">x</a>', ['avascript', 'alert']];
        yield 'javascript img src' => ['<img src="javascript:alert(1)">', ['javascript', 'alert']];
        yield 'style attribute' => ['<p style="background:url(javascript:alert(1))">x</p>', ['style', 'javascript']];
        yield 'iframe srcdoc on allowed host' => ['<iframe src="https://www.youtube.com/embed/x" srcdoc="<script>alert(1)</script>"></iframe>', ['srcdoc', 'script', 'alert']];
        yield 'unclosed iframe' => ['<iframe src="https://evil.example">', ['iframe', 'evil.example']];
        yield 'unquoted iframe src' => ['<iframe src=https://evil.example/x></iframe>', ['iframe', 'evil.example']];
        yield 'javascript iframe src' => ['<iframe src="javascript:alert(1)"></iframe>', ['iframe', 'alert']];
        yield 'http iframe on allowed host' => ['<iframe src="http://www.youtube.com/embed/x"></iframe>', ['iframe']];
        yield 'userinfo iframe host trick' => ['<iframe src="https://www.youtube.com@evil.example/x"></iframe>', ['iframe', 'evil.example']];
        yield 'svg onload' => ['<svg onload=alert(1)></svg>', ['svg', 'onload', 'alert']];
        yield 'mixed-case handler' => ['<div OnMouseOver=alert(1)>x</div>', ['onmouseover', 'alert']];
    }

    /**
     * @param list<string> $forbidden
     */
    #[DataProvider('xssPayloadProvider')]
    public function testRemovesXssPayload(string $payload, array $forbidden): void
    {
        $result = strtolower((new WikiHtmlSanitizer())->sanitize($payload));

        foreach ($forbidden as $needle) {
            self::assertStringNotContainsString(strtolower($needle), $result);
        }
    }

    public function testKeepsAllowedIframeWithoutSrcdocOrHandlers(): void
    {
        $result = (new WikiHtmlSanitizer())->sanitize(
            '<iframe src="https://player.vimeo.com/video/1" srcdoc="x" style="x" onload="x" width="100%" height="360" allowfullscreen="allowfullscreen" frameborder="0"></iframe>',
        );

        self::assertSame('<iframe src="https://player.vimeo.com/video/1" width="100%" height="360" allowfullscreen="allowfullscreen" frameborder="0"></iframe>', $result);
    }

    public function testCustomEmbedHostAllowlist(): void
    {
        $sanitizer = new WikiHtmlSanitizer(['Embed.Example.com']);

        self::assertStringContainsString('<iframe', $sanitizer->sanitize('<iframe src="https://embed.example.com/v"></iframe>'));
        self::assertStringNotContainsString('<iframe', $sanitizer->sanitize('<iframe src="https://www.youtube.com/embed/x"></iframe>'));
    }

    public function testKeepsSafeLinksAndImages(): void
    {
        $result = (new WikiHtmlSanitizer())->sanitize(
            '<p><a href="https://example.com" target="_blank" rel="noopener">a</a><a href="mailto:a@example.com">m</a><a href="/rel">r</a><img src="https://example.com/i.png" alt="i"></p>',
        );

        self::assertStringContainsString('href="https://example.com"', $result);
        self::assertStringContainsString('href="mailto:a&#64;example.com"', $result);
        self::assertStringContainsString('href="/rel"', $result);
        self::assertStringContainsString('src="https://example.com/i.png"', $result);
    }

    public function testKeepsOnlyTextAlignStyle(): void
    {
        $sanitizer = new WikiHtmlSanitizer();

        self::assertSame('<p style="text-align: center">c</p>', $sanitizer->sanitize('<p style="TEXT-ALIGN: Center;">c</p>'));
        self::assertSame('<p>c</p>', $sanitizer->sanitize('<p style="text-align: center; color: red">c</p>'));
    }

    public function testKeepsTaskListDataAttributesAndUnwrapsUnknownWrappers(): void
    {
        $result = (new WikiHtmlSanitizer())->sanitize(
            '<ul data-type="taskList"><li data-type="taskItem" data-checked="true"><label>done</label></li></ul><section>text</section>',
        );

        self::assertSame('<ul data-type="taskList"><li data-type="taskItem" data-checked="true">done</li></ul>text', $result);
    }

    public function testExtraElementsAndAttributesAreAllowedWhenConfigured(): void
    {
        $html = '<details open data-kind="tip"><summary>More</summary><p data-callout="info">Body</p></details><section>Kept</section>';

        $default = (new WikiHtmlSanitizer())->sanitize($html);
        self::assertStringNotContainsString('<details', $default);
        self::assertStringNotContainsString('data-callout', $default);

        $sanitizer = new WikiHtmlSanitizer(WikiHtmlSanitizer::DEFAULT_EMBED_HOSTS, ['details' => ['open'], 'Summary' => [], 'section' => []], ['data-callout']);
        $result    = $sanitizer->sanitize($html . '<p onclick="x()" style="color:red">Safe</p>');

        self::assertStringContainsString('<details open><summary>More</summary><p data-callout="info">Body</p></details>', $result);
        self::assertStringContainsString('<section>Kept</section>', $result);
        self::assertStringNotContainsString('data-kind', $result);
        self::assertStringNotContainsString('onclick', $result);
        self::assertStringNotContainsString('color:red', $result);
    }

    /**
     * @return iterable<string, array{mixed, mixed}>
     */
    public static function unsafeAllowlists(): iterable
    {
        yield 'script' => [['script' => []], []];
        yield 'style element' => [['STYLE' => []], []];
        yield 'iframe' => [['iframe' => []], []];
        yield 'form' => [['form' => []], []];
        yield 'invalid element name' => [['x y' => []], []];
        yield 'numeric element' => [[0 => []], []];
        yield 'style attribute' => [[], ['style']];
        yield 'event handler' => [[], ['onclick']];
        yield 'event handler upper case' => [[], ['OnLoad']];
        yield 'url attribute on element' => [['details' => ['href']], []];
        yield 'scalar element attribute' => [['details' => 'srcdoc'], []];
        yield 'not a name' => [[], ['data x']];
        yield 'not a string' => [[], [['a']]];
        yield 'not maps' => ['details', []];
        yield 'attributes not a list' => [[], 'data-x'];
        yield 'name attribute' => [[], ['name']];
        yield 'name on element' => [['img' => ['NAME']], []];
    }

    #[DataProvider('unsafeAllowlists')]
    public function testUnsafeExtraAllowlistsAreRefused(mixed $elements, mixed $attributes): void
    {
        $this->expectException(InvalidArgumentException::class);

        WikiHtmlSanitizer::assertSafeAllowlist($elements, $attributes);
    }

    public function testTheConstructorRefusesAnUnsafeAllowlist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The attribute "onerror" cannot be allowed by the wiki sanitizer.');

        new WikiHtmlSanitizer(WikiHtmlSanitizer::DEFAULT_EMBED_HOSTS, ['details' => ['onerror']]);
    }

    public function testKeepsOnlyPrefixedAnchorIds(): void
    {
        $sanitizer = new WikiHtmlSanitizer();

        self::assertSame('<h2 id="nowo-h-getting-started">G</h2>', $sanitizer->sanitize('<h2 id="nowo-h-getting-started">G</h2>'));
        self::assertSame('<p>t<sup id="nowo-fn-ref-0a1b2c3d-1"><a href="#nowo-fn-0a1b2c3d-1">1</a></sup></p>', $sanitizer->sanitize('<p>t<sup id="nowo-fn-ref-0a1b2c3d-1"><a href="#nowo-fn-0a1b2c3d-1">1</a></sup></p>'));
        self::assertSame('<ol><li id="nowo-fn-0a1b2c3d-1">n</li></ol>', $sanitizer->sanitize('<ol><li id="nowo-fn-0a1b2c3d-1">n</li></ol>'));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function clobberingIds(): iterable
    {
        yield 'proto heading' => ['<h2 id="__proto__">x</h2>', '<h2>x</h2>'];
        yield 'location heading' => ['<h1 id="location">x</h1>', '<h1>x</h1>'];
        yield 'cookie footnote' => ['<ol><li id="cookie">x</li></ol>', '<ol><li>x</li></ol>'];
        yield 'unprefixed sup' => ['<sup id="fnref:1">1</sup>', '<sup>1</sup>'];
        yield 'entity-encoded proto' => ['<h2 id="&#95;&#95;proto&#95;&#95;">x</h2>', '<h2>x</h2>'];
        yield 'entity-encoded prefix' => ['<h2 id="&#110;owo-h-a&#32;b">x</h2>', '<h2>x</h2>'];
        yield 'quote breakout' => ['<h2 id=\'nowo-h-a" onmouseover="alert(1)\'>x</h2>', '<h2>x</h2>'];
        yield 'prefixed id on div' => ['<div id="nowo-h-a">x</div>', '<div>x</div>'];
        yield 'prefixed id on a' => ['<a id="nowo-fn-ref-0a1b2c3d-1" href="/x">x</a>', '<a href="/x">x</a>'];
        yield 'prefixed id on form' => ['<form id="nowo-h-a"><input name="x"></form>', ''];
        yield 'name attribute' => ['<a name="location" href="/x">x</a>', '<a href="/x">x</a>'];
    }

    #[DataProvider('clobberingIds')]
    public function testDropsClobberingIds(string $html, string $expected): void
    {
        self::assertSame($expected, (new WikiHtmlSanitizer())->sanitize($html));
    }

    public function testAHostAllowedIdIsStillLimitedToTheAnchors(): void
    {
        $sanitizer = new WikiHtmlSanitizer(WikiHtmlSanitizer::DEFAULT_EMBED_HOSTS, ['section' => ['id']], ['id']);

        self::assertSame('<section>x</section><p>y</p>', $sanitizer->sanitize('<section id="location">x</section><p id="__proto__">y</p>'));
        self::assertSame('<h3 id="nowo-h-x">x</h3>', $sanitizer->sanitize('<h3 id="nowo-h-x">x</h3>'));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function alignInjections(): iterable
    {
        yield 'align attribute' => ['<table><tbody><tr><td align="center">x</td></tr></tbody></table>', '<table><tbody><tr><td>x</td></tr></tbody></table>'];
        yield 'align on paragraph' => ['<p align="right">x</p>', '<p>x</p>'];
        yield 'align quote breakout' => ['<table><tbody><tr><td align=\'center" onclick="alert(1)\'>x</td></tr></tbody></table>', '<table><tbody><tr><td>x</td></tr></tbody></table>'];
        yield 'align with handler' => ['<table><tbody><tr><th align="left" onmouseover="alert(1)">x</th></tr></tbody></table>', '<table><tbody><tr><th>x</th></tr></tbody></table>'];
        yield 'style beyond text-align' => ['<table><tbody><tr><td style="text-align:center;background:url(javascript:alert(1))">x</td></tr></tbody></table>', '<table><tbody><tr><td>x</td></tr></tbody></table>'];
        yield 'alignment class' => ['<table><tbody><tr><td class="nowo-wiki-align--center">x</td></tr></tbody></table>', '<table><tbody><tr><td class="nowo-wiki-align--center">x</td></tr></tbody></table>'];
    }

    #[DataProvider('alignInjections')]
    public function testAlignmentNeverCarriesMoreThanAClass(string $html, string $expected): void
    {
        self::assertSame($expected, (new WikiHtmlSanitizer())->sanitize($html));
    }
}
