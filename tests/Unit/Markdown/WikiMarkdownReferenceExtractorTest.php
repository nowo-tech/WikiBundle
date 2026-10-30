<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Markdown;

use Nowo\WikiBundle\Markdown\WikiMarkdownReferenceExtractor;
use PHPUnit\Framework\TestCase;

final class WikiMarkdownReferenceExtractorTest extends TestCase
{
    private const A = '0a1b2c3d-1111-2222-3333-444455556666';

    private const B = 'bbbbbbbb-1111-2222-3333-444455556666';

    public function testFindsDistinctPageReferencesOutsideCode(): void
    {
        $markdown = <<<'MD'
See [[wiki:0A1B2C3D-1111-2222-3333-444455556666#setup]] and [[wiki:bbbbbbbb-1111-2222-3333-444455556666]].

Inline `[[wiki:cccccccc-1111-2222-3333-444455556666]]` is code.

```
[[wiki:dddddddd-1111-2222-3333-444455556666]]
```

    [[wiki:eeeeeeee-1111-2222-3333-444455556666]]

Again [[wiki:0a1b2c3d-1111-2222-3333-444455556666#setup]], [[wiki:0a1b2c3d-1111-2222-3333-444455556666]] and [[wiki:not-a-uuid]].
MD;

        $extractor = new WikiMarkdownReferenceExtractor();

        self::assertSame([
            ['id' => self::A, 'anchor' => 'setup'],
            ['id' => self::B, 'anchor' => null],
            ['id' => self::A, 'anchor' => null],
        ], $extractor->pageReferences($markdown));
        self::assertSame([self::A, self::B], $extractor->pageIds($markdown));
    }

    public function testNothingToExtract(): void
    {
        $extractor = new WikiMarkdownReferenceExtractor();

        self::assertSame([], $extractor->pageReferences(null));
        self::assertSame([], $extractor->pageReferences(''));
        self::assertSame([], $extractor->pageReferences('No links here.'));
        self::assertSame([], $extractor->pageIds(null));
    }
}
