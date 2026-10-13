<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Security\AttributeSanitizer;

use Nowo\WikiBundle\Security\AttributeSanitizer\TextAlignStyleAttributeSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

#[CoversClass(TextAlignStyleAttributeSanitizer::class)]
final class TextAlignStyleAttributeSanitizerTest extends TestCase
{
    public function testSupportsStyleOnAnyElement(): void
    {
        $sanitizer = new TextAlignStyleAttributeSanitizer();

        self::assertNull($sanitizer->getSupportedElements());
        self::assertSame(['style'], $sanitizer->getSupportedAttributes());
    }

    public function testNormalizesTextAlign(): void
    {
        $sanitizer = new TextAlignStyleAttributeSanitizer();

        self::assertSame('text-align: justify', $sanitizer->sanitizeAttribute('p', 'style', ' Text-Align : JUSTIFY ; ', new HtmlSanitizerConfig()));
    }

    public function testDropsAnyOtherCss(): void
    {
        $sanitizer = new TextAlignStyleAttributeSanitizer();
        $config    = new HtmlSanitizerConfig();

        self::assertNull($sanitizer->sanitizeAttribute('p', 'style', 'color: red', $config));
        self::assertNull($sanitizer->sanitizeAttribute('p', 'style', 'text-align: center; background: url(javascript:alert(1))', $config));
        self::assertNull($sanitizer->sanitizeAttribute('p', 'style', 'text-align: expression(alert(1))', $config));
    }
}
