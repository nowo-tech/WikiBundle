<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Interchange;

use InvalidArgumentException;
use Nowo\WikiBundle\Enum\WikiInterchangeFormat;
use Nowo\WikiBundle\Interchange\WikiArchiveHelper;
use Nowo\WikiBundle\Interchange\WikiDocumentTreeReader;
use Nowo\WikiBundle\Interchange\WikiFormatDetector;
use Nowo\WikiBundle\Interchange\WikiFrontMatterParser;
use Nowo\WikiBundle\Interchange\WikiMarkdownFileReader;
use PHPUnit\Framework\TestCase;

use function strlen;

/**
 * Audit GV-L3: the two reads of a whole Markdown file are bounded, also for the directory sources of the CLI, which no
 * archive check sees.
 */
final class WikiMarkdownFileReaderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/wiki-reader-' . bin2hex(random_bytes(4));
        mkdir($this->root);
        file_put_contents($this->root . '/big.md', str_repeat('a', 2049));
    }

    protected function tearDown(): void
    {
        (new WikiArchiveHelper())->removeDirectory($this->root);
    }

    public function testReturnsContentAtTheLimit(): void
    {
        self::assertSame(2049, strlen((new WikiMarkdownFileReader(2049))->read($this->root . '/big.md')));
    }

    public function testRefusesContentOverTheLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"big.md" exceeds the limit of 2048 bytes per document');

        (new WikiMarkdownFileReader(2048))->read($this->root . '/big.md');
    }

    public function testNeverHoldsMoreThanTheLimitInMemory(): void
    {
        $path = $this->root . '/huge.md';
        $file = fopen($path, 'w');
        self::assertIsResource($file);
        ftruncate($file, 1024 * 1024 * 1024);
        fclose($file);
        memory_reset_peak_usage();
        $before = memory_get_peak_usage();

        try {
            (new WikiMarkdownFileReader(1024))->read($path);
            self::fail('Expected the document to be refused.');
        } catch (InvalidArgumentException) {
            self::assertLessThan(1024 * 1024, memory_get_peak_usage() - $before);
        }
    }

    /** Audit GV-I14: a failed read is not an empty document. */
    public function testRefusesAFileThatCannotBeRead(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"gone.md" cannot be read');

        (new WikiMarkdownFileReader())->read($this->root . '/gone.md');
    }

    /** Audit GV-I14: a directory named like a document is a folder, not an empty page. */
    public function testTreeReaderSkipsADirectoryNamedLikeADocument(): void
    {
        unlink($this->root . '/big.md');
        mkdir($this->root . '/folder.md');
        file_put_contents($this->root . '/folder.md/page.md', "# Page\n");

        $nodes = (new WikiDocumentTreeReader(new WikiFrontMatterParser()))->read($this->root, WikiInterchangeFormat::Outline);

        self::assertSame(['Page'], array_map(static fn ($node) => $node->title, $nodes));
    }

    public function testFormatDetectorUsesTheBoundedRead(): void
    {
        $this->expectExceptionMessage('per document');

        (new WikiFormatDetector(new WikiFrontMatterParser(), new WikiMarkdownFileReader(2048)))->detect($this->root);
    }

    public function testTreeReaderUsesTheBoundedRead(): void
    {
        $this->expectExceptionMessage('per document');

        (new WikiDocumentTreeReader(new WikiFrontMatterParser(), new WikiMarkdownFileReader(2048)))->read($this->root, WikiInterchangeFormat::Outline);
    }
}
