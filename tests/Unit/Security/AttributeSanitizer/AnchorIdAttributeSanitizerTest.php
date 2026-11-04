<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Security\AttributeSanitizer;

use Nowo\WikiBundle\Security\AttributeSanitizer\AnchorIdAttributeSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

use function str_repeat;

#[CoversClass(AnchorIdAttributeSanitizer::class)]
final class AnchorIdAttributeSanitizerTest extends TestCase
{
    public function testSupportsIdOnAnyElement(): void
    {
        $sanitizer = new AnchorIdAttributeSanitizer();

        self::assertNull($sanitizer->getSupportedElements());
        self::assertSame(['id'], $sanitizer->getSupportedAttributes());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function keptIds(): iterable
    {
        yield 'heading' => ['h2', 'nowo-h-getting-started'];
        yield 'heading duplicate' => ['h1', 'nowo-h-intro-2'];
        yield 'heading unicode' => ['h3', 'nowo-h-configuración-año'];
        yield 'heading fallback' => ['h6', 'nowo-h-section'];
        yield 'heading max length' => ['h4', 'nowo-h-' . str_repeat('a', 90)];
        yield 'footnote' => ['li', 'nowo-fn-0a1b2c3d-1'];
        yield 'footnote ref' => ['sup', 'nowo-fn-ref-0a1b2c3d-12'];
        yield 'repeated footnote ref' => ['sup', 'nowo-fn-ref-0a1b2c3d-1__2'];
    }

    #[DataProvider('keptIds')]
    public function testKeepsTheRendererAnchors(string $element, string $id): void
    {
        self::assertSame($id, (new AnchorIdAttributeSanitizer())->sanitizeAttribute($element, 'id', $id, new HtmlSanitizerConfig()));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function droppedIds(): iterable
    {
        // DOM clobbering: globals, prototype and host ids.
        yield 'proto' => ['h2', '__proto__'];
        yield 'location' => ['h2', 'location'];
        yield 'document forms' => ['li', 'forms'];
        yield 'constructor' => ['sup', 'constructor'];
        yield 'host id' => ['h1', 'app'];
        yield 'empty' => ['h2', ''];
        yield 'bare prefix' => ['h2', 'nowo-h-'];
        yield 'prefix only fn' => ['li', 'nowo-fn-'];
        // Prefix on the wrong element, or other prefixes.
        yield 'heading id on div' => ['div', 'nowo-h-intro'];
        yield 'heading id on a' => ['a', 'nowo-h-intro'];
        yield 'heading id on li' => ['li', 'nowo-h-intro'];
        yield 'footnote id on heading' => ['h2', 'nowo-fn-0a1b2c3d-1'];
        yield 'ref id on li' => ['li', 'nowo-fn-ref-0a1b2c3d-1'];
        yield 'footnote id on sup' => ['sup', 'nowo-fn-0a1b2c3d-1'];
        yield 'milkdown prefix' => ['li', 'nowo-md-fn-1'];
        yield 'upper-case prefix' => ['h2', 'NOWO-H-intro'];
        // Charset and shape.
        yield 'space' => ['h2', 'nowo-h-a b'];
        yield 'quote' => ['h2', 'nowo-h-a"onmouseover="x'];
        yield 'angle' => ['h2', 'nowo-h-a<b'];
        yield 'double hyphen' => ['h2', 'nowo-h-a--b'];
        yield 'trailing hyphen' => ['h2', 'nowo-h-a-'];
        yield 'trailing newline' => ['h2', "nowo-h-a\n"];
        yield 'dot' => ['h2', 'nowo-h-a.b'];
        yield 'too long' => ['h2', 'nowo-h-' . str_repeat('a', 91)];
        yield 'invalid utf-8' => ['h2', "nowo-h-\xC3"];
        yield 'footnote label text' => ['li', 'nowo-fn-my note'];
        yield 'footnote not hex salt' => ['li', 'nowo-fn-zzzzzzzz-1'];
        yield 'footnote zero' => ['li', 'nowo-fn-0a1b2c3d-0'];
        yield 'footnote suffix on li' => ['li', 'nowo-fn-0a1b2c3d-1__2'];
        yield 'footnote ref unsalted' => ['sup', 'nowo-fn-ref-1'];
    }

    #[DataProvider('droppedIds')]
    public function testDropsEveryOtherId(string $element, string $id): void
    {
        self::assertNull((new AnchorIdAttributeSanitizer())->sanitizeAttribute($element, 'id', $id, new HtmlSanitizerConfig()));
    }
}
