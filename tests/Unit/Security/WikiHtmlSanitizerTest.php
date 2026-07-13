<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Security;

use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
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
}
