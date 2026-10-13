<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Security\AttributeSanitizer;

use Nowo\WikiBundle\Security\AttributeSanitizer\EmbedIframeSrcAttributeSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

#[CoversClass(EmbedIframeSrcAttributeSanitizer::class)]
final class EmbedIframeSrcAttributeSanitizerTest extends TestCase
{
    public function testSupportsOnlyIframeSrc(): void
    {
        $sanitizer = new EmbedIframeSrcAttributeSanitizer(['www.youtube.com']);

        self::assertSame(['iframe'], $sanitizer->getSupportedElements());
        self::assertSame(['src'], $sanitizer->getSupportedAttributes());
    }

    public function testKeepsHttpsUrlOnAllowedHost(): void
    {
        $sanitizer = new EmbedIframeSrcAttributeSanitizer(['WWW.YouTube.com']);

        self::assertSame(
            'https://www.youtube.com/embed/x',
            $sanitizer->sanitizeAttribute('iframe', 'src', 'https://www.youtube.com/embed/x', new HtmlSanitizerConfig()),
        );
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function rejectedProvider(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'http' => ['http://www.youtube.com/embed/x'];
        yield 'other host' => ['https://evil.example/x'];
        yield 'userinfo' => ['https://www.youtube.com@evil.example/x'];
        yield 'user and password' => ['https://u:p@www.youtube.com/embed/x'];
        yield 'relative' => ['/embed/x'];
        yield 'unparsable' => ['https:///'];
    }

    #[DataProvider('rejectedProvider')]
    public function testRejects(string $value): void
    {
        $sanitizer = new EmbedIframeSrcAttributeSanitizer(['www.youtube.com']);

        self::assertNull($sanitizer->sanitizeAttribute('iframe', 'src', $value, new HtmlSanitizerConfig()));
    }
}
