<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Security;

use InvalidArgumentException;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WikiHtmlSanitizerTest extends TestCase
{
    public function testStripsScriptTags(): void
    {
        $sanitizer = new WikiHtmlSanitizer();
        $result    = $sanitizer->sanitize('<p>Hi</p><script>alert(1)</script>');

        self::assertStringNotContainsString('script', $result);
        self::assertStringContainsString('<p>Hi</p>', $result);
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
}
