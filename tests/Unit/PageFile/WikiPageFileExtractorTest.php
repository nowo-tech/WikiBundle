<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\PageFile;

use Nowo\WikiBundle\PageFile\WikiPageFile;
use Nowo\WikiBundle\PageFile\WikiPageFileExtractor;
use PHPUnit\Framework\TestCase;

use function array_map;
use function str_repeat;

/**
 * 1.20 (`page_files`): the files a page's Markdown refers to — Milkdown file cards and images, outside code, safe URLs
 * only, cleaned names, in order, once.
 */
final class WikiPageFileExtractorTest extends TestCase
{
    public function testCardsAndImagesInOrderWithoutDuplicates(): void
    {
        $markdown = <<<'MD'
            # Release

            ![Diagram](/files/1 "The flow") and ![](https://cdn.example/2.png)

            ::file[Q3 report.pdf]{href="/files/3" size="1258291" type="application/pdf"}

              ::file[data.csv]{type='text/csv' href=/files/4 size=512}

            ![again](/files/1)
            ::file[Q3 copy.pdf]{href="/files/3"}
            ![wrapped](</files/5 with space.png>)
            MD;
        $files = (new WikiPageFileExtractor())->files($markdown);

        self::assertSame(['/files/1', 'https://cdn.example/2.png', '/files/3', '/files/4', '/files/5 with space.png'], array_map(static fn (WikiPageFile $f): string => $f->url, $files));
        self::assertSame(['Diagram', '', 'Q3 report.pdf', 'data.csv', 'wrapped'], array_map(static fn (WikiPageFile $f): string => $f->name, $files));
        self::assertSame([WikiPageFile::KIND_IMAGE, WikiPageFile::KIND_IMAGE, WikiPageFile::KIND_FILE, WikiPageFile::KIND_FILE, WikiPageFile::KIND_IMAGE], array_map(static fn (WikiPageFile $f): string => $f->kind, $files));
        self::assertSame(1258291, $files[2]->size);
        self::assertSame('application/pdf', $files[2]->type);
        self::assertSame('1.2 MB', $files[2]->sizeLabel());
        self::assertSame('512 B', $files[3]->sizeLabel());
        self::assertSame('text/csv', $files[3]->type);
        self::assertNull($files[0]->sizeLabel());
    }

    public function testCodeUnsafeUrlsAndBadAttributesAreIgnored(): void
    {
        $markdown = <<<'MD'
            ```
            ::file[in code]{href="/files/code"}
            ![in code](/files/code.png)
            ```

                ::file[indented]{href="/files/indented"}

            `![span](/files/span.png)` ![js](javascript:alert(1)) ![proto](//evil.example/x.png) ![data](data:image/png;base64,AA)

            ::file[no link]{size="1"}
            ::file[bad]{href="javascript:alert(1)"}
            ::file[ent]{href="java&#115;cript:alert(1)"}
            ::file{href="/files/unnamed" size="12x" type="not a type"}
            ::file[  spaced
            MD;
        $files = (new WikiPageFileExtractor())->files($markdown);

        self::assertCount(1, $files);
        self::assertSame('/files/unnamed', $files[0]->url);
        self::assertSame('', $files[0]->name);
        self::assertNull($files[0]->size);
        self::assertNull($files[0]->type);
    }

    public function testNamesAreCleanedAndTheListIsCapped(): void
    {
        $extractor = new WikiPageFileExtractor(2);
        $files     = $extractor->files("::file[  invoice\u{202E}fdp.exe\tnow ]{href=\"/a\"}\n\n![b](/b)\n\n![c](/c)");

        self::assertSame(['invoicefdp.exe now', 'b'], array_map(static fn (WikiPageFile $f): string => $f->name, $files));
        self::assertSame(200, mb_strlen(WikiPageFileExtractor::name(str_repeat('x', 300))));
        self::assertSame([], $extractor->files(null));
        self::assertSame([], $extractor->files("  \n"));
        self::assertSame('', WikiPageFileExtractor::safeUrl('/' . str_repeat('a', 2048)));
        self::assertSame('https://x.example/a', WikiPageFileExtractor::safeUrl("\x01 https://x.example/a "));
        self::assertSame('', WikiPageFileExtractor::safeUrl('\\\\evil/x'));
        self::assertSame('a/b.pdf', WikiPageFileExtractor::safeUrl('a/b.pdf'));
    }

    public function testSizeLabels(): void
    {
        $label = static fn (?int $size): ?string => (new WikiPageFile('/f', 'f', WikiPageFile::KIND_FILE, $size))->sizeLabel();

        self::assertSame(['0 B', '1023 B', '1 KB', '1.5 KB', '10 KB', '15 MB', '5 GB', '1 TB', '1024 TB'], [
            $label(0), $label(1023), $label(1024), $label(1536), $label(10239), $label(15728640), $label(5368709120), $label(1099511627776), $label(1125899906842624),
        ]);
        self::assertNull($label(-1));
    }

    /** Audit GR-I6 / GR-I9: the renderer's attribute rules, cards in containers, comments and escaped images. */
    public function testItReadsCardsLikeTheRenderer(): void
    {
        $markdown = <<<'MD'
            ::file[dup]{href="/files/first" href="/files/last"}

            > ::file[quoted]{href="/files/quoted"}

            - ::file[listed]{href='/files/listed'}
            1. ::file[ordered]{href=/files/ordered}

            ::file[stop]{href="/files/stop" "broken" href="/files/after"}

            <!-- ![hidden](/files/comment.png) -->
            \![escaped](/files/escaped.png) ![ref][r]

            [r]: /files/ref.png
            MD;
        $files = (new WikiPageFileExtractor())->files($markdown);

        self::assertSame(['/files/last', '/files/quoted', '/files/listed', '/files/ordered', '/files/stop'], array_map(static fn (WikiPageFile $f): string => $f->url, $files));
        self::assertSame('abcdef.pdf', WikiPageFileExtractor::name("a\u{061C}b\u{200B}c\u{200D}d\u{2060}e\u{FEFF}f.pdf"));
    }
}
