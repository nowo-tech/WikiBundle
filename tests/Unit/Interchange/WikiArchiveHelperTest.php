<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Interchange;

use InvalidArgumentException;
use Nowo\WikiBundle\Interchange\WikiArchiveHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

use function ini_get;

final class WikiArchiveHelperTest extends TestCase
{
    private WikiArchiveHelper $helper;

    protected function setUp(): void
    {
        $this->helper = new WikiArchiveHelper();
    }

    public function testIsZipPath(): void
    {
        $zip = sys_get_temp_dir() . '/wiki-archive-' . bin2hex(random_bytes(4)) . '.zip';
        file_put_contents($zip, '');

        try {
            self::assertTrue($this->helper->isZipPath($zip));
            self::assertFalse($this->helper->isZipPath(__FILE__));
        } finally {
            unlink($zip);
        }
    }

    public function testExtractZipCreatesDirectoryWithMarkdown(): void
    {
        $sourceDir = sys_get_temp_dir() . '/wiki-archive-src-' . bin2hex(random_bytes(4));
        mkdir($sourceDir);
        file_put_contents($sourceDir . '/page.md', '# Page');

        $zipPath = sys_get_temp_dir() . '/wiki-archive-' . bin2hex(random_bytes(4)) . '.zip';
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($sourceDir . '/page.md', 'page.md');
        $zip->close();

        try {
            $extracted = $this->helper->extractZip($zipPath);

            self::assertDirectoryExists($extracted);
            self::assertFileExists($extracted . '/page.md');
            $this->helper->removeDirectory($extracted);
        } finally {
            unlink($sourceDir . '/page.md');
            rmdir($sourceDir);
            unlink($zipPath);
        }
    }

    public function testExtractZipThrowsWhenFileMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->helper->extractZip('/tmp/missing-' . bin2hex(random_bytes(4)) . '.zip');
    }

    public function testCreateZipFromDirectory(): void
    {
        $sourceDir = sys_get_temp_dir() . '/wiki-archive-src-' . bin2hex(random_bytes(4));
        mkdir($sourceDir);
        file_put_contents($sourceDir . '/intro.md', '# Intro');

        $zipPath = sys_get_temp_dir() . '/wiki-archive-' . bin2hex(random_bytes(4)) . '.zip';

        try {
            $this->helper->createZipFromDirectory($sourceDir, $zipPath);

            self::assertFileExists($zipPath);
            self::assertSame(0o600, fileperms($zipPath) & 0o777, 'exported spaces are owner-only');
            $zip = new ZipArchive();
            $zip->open($zipPath);
            self::assertNotFalse($zip->locateName('intro.md'));
            $zip->close();
        } finally {
            unlink($sourceDir . '/intro.md');
            rmdir($sourceDir);
            if (is_file($zipPath)) {
                unlink($zipPath);
            }
        }
    }

    public function testCreateZipThrowsWhenDirectoryMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->helper->createZipFromDirectory('/tmp/missing-' . bin2hex(random_bytes(4)), '/tmp/out.zip');
    }

    public function testRemoveDirectoryDeletesTree(): void
    {
        $root = sys_get_temp_dir() . '/wiki-archive-rm-' . bin2hex(random_bytes(4));
        mkdir($root);
        mkdir($root . '/nested');
        file_put_contents($root . '/nested/file.txt', 'x');

        $this->helper->removeDirectory($root);

        self::assertDirectoryDoesNotExist($root);
    }

    public function testRemoveDirectoryIgnoresMissingPath(): void
    {
        $this->helper->removeDirectory('/tmp/missing-' . bin2hex(random_bytes(4)));
        self::assertTrue(true);
    }

    public function testExtractZipThrowsWhenArchiveCannotOpen(): void
    {
        $zipPath = sys_get_temp_dir() . '/wiki-archive-' . bin2hex(random_bytes(4)) . '.zip';
        file_put_contents($zipPath, 'not-a-zip');

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->helper->extractZip($zipPath);
        } finally {
            unlink($zipPath);
        }
    }

    public function testCreateZipAddsSubdirectories(): void
    {
        $sourceDir = sys_get_temp_dir() . '/wiki-archive-src-' . bin2hex(random_bytes(4));
        $nestedDir = $sourceDir . '/nested';
        mkdir($nestedDir, 0777, true);
        file_put_contents($nestedDir . '/intro.md', '# Intro');

        $zipPath = sys_get_temp_dir() . '/wiki-archive-' . bin2hex(random_bytes(4)) . '.zip';

        try {
            $this->helper->createZipFromDirectory($sourceDir, $zipPath);

            $zip = new ZipArchive();
            $zip->open($zipPath);
            self::assertNotFalse($zip->locateName('nested/intro.md'));
            $zip->close();
        } finally {
            unlink($nestedDir . '/intro.md');
            rmdir($nestedDir);
            rmdir($sourceDir);
            if (is_file($zipPath)) {
                unlink($zipPath);
            }
        }
    }

    public function testCreateZipAddsEmptySubdirectory(): void
    {
        $sourceDir = sys_get_temp_dir() . '/wiki-archive-src-' . bin2hex(random_bytes(4));
        $nestedDir = $sourceDir . '/nested';
        mkdir($nestedDir, 0777, true);

        $zipPath = sys_get_temp_dir() . '/wiki-archive-' . bin2hex(random_bytes(4)) . '.zip';

        try {
            $this->helper->createZipFromDirectory($sourceDir, $zipPath);

            $zip = new ZipArchive();
            $zip->open($zipPath);
            self::assertNotFalse($zip->locateName('nested/'));
            $zip->close();
        } finally {
            rmdir($nestedDir);
            rmdir($sourceDir);
            if (is_file($zipPath)) {
                unlink($zipPath);
            }
        }
    }

    public function testCreateZipThrowsWhenZipPathIsDirectory(): void
    {
        $sourceDir = sys_get_temp_dir() . '/wiki-archive-src-' . bin2hex(random_bytes(4));
        mkdir($sourceDir);
        file_put_contents($sourceDir . '/intro.md', '# Intro');

        try {
            $this->expectException(RuntimeException::class);
            $this->helper->createZipFromDirectory($sourceDir, $sourceDir);
        } finally {
            unlink($sourceDir . '/intro.md');
            rmdir($sourceDir);
        }
    }

    public function testCreateZipThrowsWhenNothingWasWritten(): void
    {
        // ZipArchive writes no file for an archive without entries: callers must not get a missing path back.
        $sourceDir = sys_get_temp_dir() . '/wiki-archive-src-' . bin2hex(random_bytes(4));
        mkdir($sourceDir);
        $zipPath = sys_get_temp_dir() . '/wiki-archive-' . bin2hex(random_bytes(4)) . '.zip';

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('ZIP archive was not written');
            $this->helper->createZipFromDirectory($sourceDir, $zipPath);
        } finally {
            rmdir($sourceDir);
            self::assertFileDoesNotExist($zipPath);
        }
    }

    public function testCreateZipSkipsNonSplFileInfoEntries(): void
    {
        $sourceDir = sys_get_temp_dir() . '/wiki-archive-src-' . bin2hex(random_bytes(4));
        mkdir($sourceDir);
        file_put_contents($sourceDir . '/intro.md', '# Intro');

        $zipPath = sys_get_temp_dir() . '/wiki-archive-' . bin2hex(random_bytes(4)) . '.zip';

        try {
            $this->helper->createZipFromDirectory($sourceDir, $zipPath);
            self::assertFileExists($zipPath);
        } finally {
            unlink($sourceDir . '/intro.md');
            rmdir($sourceDir);
            if (is_file($zipPath)) {
                unlink($zipPath);
            }
        }
    }

    public function testExtractZipRejectsPathTraversalEntries(): void
    {
        foreach (['../evil.md', 'docs/../../evil.md', '/etc/evil.md', 'C:/evil.md', '..\\evil.md'] as $entry) {
            $zipPath = $this->createZip(['ok.md' => 'ok', $entry => 'x']);
            $before  = glob(sys_get_temp_dir() . '/wiki-import-*') ?: [];

            try {
                $this->helper->extractZip($zipPath);
                self::fail('Expected entry ' . $entry . ' to be rejected.');
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString('escapes the extraction directory', $e->getMessage());
                self::assertSame($before, glob(sys_get_temp_dir() . '/wiki-import-*') ?: []);
            } finally {
                unlink($zipPath);
            }
        }
    }

    public function testExtractZipRejectsTooManyEntries(): void
    {
        $zipPath = $this->createZip(['a.md' => 'a', 'b.md' => 'b', 'c.md' => 'c']);
        $helper  = new WikiArchiveHelper(maxEntries: 2);

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('too many entries');
            $helper->extractZip($zipPath);
        } finally {
            unlink($zipPath);
        }
    }

    public function testExtractZipRejectsZipBombBeforeExtracting(): void
    {
        // 2 MiB of zeros compresses to a few KiB: a miniature ZIP bomb.
        $zipPath = $this->createZip(['bomb.md' => str_repeat("\0", 2 * 1024 * 1024), 'ok.md' => 'ok']);
        $helper  = new WikiArchiveHelper(maxUncompressedBytes: 1024 * 1024);
        $before  = glob(sys_get_temp_dir() . '/wiki-import-*') ?: [];

        try {
            self::assertLessThan(1024 * 1024, (int) filesize($zipPath));
            $helper->extractZip($zipPath);
            self::fail('Expected the ZIP bomb to be rejected.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('uncompressed size exceeds', $e->getMessage());
            self::assertSame($before, glob(sys_get_temp_dir() . '/wiki-import-*') ?: []);
        } finally {
            unlink($zipPath);
        }
    }

    public function testExtractZipAcceptsArchiveWithinLimits(): void
    {
        $zipPath = $this->createZip(['a.md' => 'a', 'b.md' => 'b']);
        $helper  = new WikiArchiveHelper(maxEntries: 2, maxUncompressedBytes: 2);

        try {
            $extracted = $helper->extractZip($zipPath);
            self::assertFileExists($extracted . '/b.md');
            $helper->removeDirectory($extracted);
        } finally {
            unlink($zipPath);
        }
    }

    /**
     * Audit GV-L3: a tiny archive declaring one huge document is refused before anything reaches the temp dir.
     *
     * @param non-empty-string $name
     */
    #[DataProvider('markdownNames')]
    public function testExtractZipRefusesMarkdownOverTheDocumentLimitBeforeExtracting(string $name): void
    {
        $zipPath = $this->createZip([$name => str_repeat('a', 2048), 'ok.md' => 'ok']);
        $helper  = new WikiArchiveHelper(maxDocumentBytes: 2047);
        $before  = glob(sys_get_temp_dir() . '/wiki-import-*') ?: [];

        try {
            $helper->extractZip($zipPath);
            self::fail('Expected the document to be refused.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('exceeds the limit of 2047 bytes per document', $e->getMessage());
            self::assertSame($before, glob(sys_get_temp_dir() . '/wiki-import-*') ?: []);
        } finally {
            unlink($zipPath);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function markdownNames(): iterable
    {
        yield 'root' => ['big.md'];
        yield 'nested' => ['a/b/big.md'];
        yield 'upper case: the format detector reads it too' => ['BIG.MD'];
        yield 'backslash path' => ['a\\big.md'];
    }

    public function testDocumentLimitIsInclusiveAndLeavesOtherFilesAlone(): void
    {
        $zipPath = $this->createZip(['exact.md' => str_repeat('a', 2048), 'image.png' => str_repeat('b', 4096)]);
        $helper  = new WikiArchiveHelper(maxDocumentBytes: 2048);

        try {
            $extracted = $helper->extractZip($zipPath);
            self::assertFileExists($extracted . '/exact.md');
            self::assertFileExists($extracted . '/image.png');
            $helper->removeDirectory($extracted);
        } finally {
            unlink($zipPath);
        }
    }

    public function testDefaultDocumentLimitIsEightMebibytes(): void
    {
        self::assertSame(8 * 1024 * 1024, WikiArchiveHelper::DEFAULT_MAX_DOCUMENT_BYTES);

        $zipPath = $this->createZip(['big.md' => str_repeat("\0", WikiArchiveHelper::DEFAULT_MAX_DOCUMENT_BYTES + 1)]);

        try {
            $this->expectExceptionMessage('per document');
            $this->helper->extractZip($zipPath);
        } finally {
            unlink($zipPath);
        }
    }

    /** Audit GV-L3: what a killed request left is removed by the next import, and nothing else is. */
    public function testExtractZipSweepsStaleTempFilesOnly(): void
    {
        $tmp         = sys_get_temp_dir();
        $staleDir    = $tmp . '/wiki-import-' . bin2hex(random_bytes(8));
        $staleUpload = $tmp . '/wiki-upload-' . bin2hex(random_bytes(8)) . '.zip';
        $freshDir    = $tmp . '/wiki-import-' . bin2hex(random_bytes(8));
        $foreignDir  = $tmp . '/wiki-import-keep-' . bin2hex(random_bytes(8));
        $victim      = $tmp . '/wiki-victim-' . bin2hex(random_bytes(8));
        $staleLink   = $tmp . '/wiki-import-' . bin2hex(random_bytes(8));
        foreach ([$staleDir . '/sub', $freshDir, $foreignDir, $victim] as $dir) {
            mkdir($dir, 0700, true);
        }
        file_put_contents($staleDir . '/sub/page.md', 'x');
        file_put_contents($staleUpload, 'x');
        file_put_contents($victim . '/keep.txt', 'x');
        symlink($victim, $staleLink);
        $old = time() - 3700;
        foreach ([$staleDir, $staleUpload, $foreignDir, $victim] as $path) {
            touch($path, $old);
        }
        clearstatcache();

        $zipPath = $this->createZip(['a.md' => 'a']);

        try {
            $extracted = $this->helper->extractZip($zipPath);
            clearstatcache();
            self::assertDirectoryDoesNotExist($staleDir);
            self::assertFileDoesNotExist($staleUpload);
            self::assertDirectoryExists($freshDir, 'A recent directory may be a live import.');
            self::assertDirectoryExists($foreignDir, 'Only the exact names of the bundle are removed.');
            self::assertFileExists($victim . '/keep.txt', 'A link is never followed.');
            self::assertTrue(is_link($staleLink));
            self::assertFileExists($extracted . '/a.md');
            $this->helper->removeDirectory($extracted);
        } finally {
            unlink($zipPath);
            unlink($staleLink);
            foreach ([$freshDir, $foreignDir, $victim, $staleDir] as $dir) {
                $this->helper->removeDirectory($dir);
            }
        }
    }

    /** Audit GV-L3: documents that cannot be read within memory_limit are refused; each one alone is within its limit. */
    public function testExtractZipRefusesDocumentsThatDoNotFitInMemory(): void
    {
        // Big enough for any gap between the real and the used memory, which depends on the tests that ran before.
        $size    = (int) ((memory_get_usage(true) - memory_get_usage()) / 2) + 4 * 1024 * 1024;
        $zipPath = $this->createZip(['a.md' => str_repeat('a', $size), 'b.md' => str_repeat('a', $size)]);
        $helper  = new WikiArchiveHelper(maxDocumentBytes: $size);
        $before  = glob(sys_get_temp_dir() . '/wiki-import-*') ?: [];
        $limit   = (string) ini_get('memory_limit');
        ini_set('memory_limit', (string) (memory_get_usage(true) + 3 * 1024 * 1024));

        try {
            $helper->extractZip($zipPath);
            self::fail('Expected the documents to be refused.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('do not fit in the memory limit', $e->getMessage());
            self::assertSame($before, glob(sys_get_temp_dir() . '/wiki-import-*') ?: []);
        } finally {
            ini_set('memory_limit', $limit);
            unlink($zipPath);
        }
    }

    public function testNoMemoryLimitSkipsTheMemoryCheck(): void
    {
        $zipPath = $this->createZip(['a.md' => 'a']);
        $limit   = (string) ini_get('memory_limit');
        ini_set('memory_limit', '-1');

        try {
            $extracted = $this->helper->extractZip($zipPath);
            self::assertFileExists($extracted . '/a.md');
            $this->helper->removeDirectory($extracted);
        } finally {
            ini_set('memory_limit', $limit);
            unlink($zipPath);
        }
    }

    /**
     * @param array<string, string> $entries
     */
    private function createZip(array $entries): string
    {
        $zipPath = sys_get_temp_dir() . '/wiki-archive-' . bin2hex(random_bytes(4)) . '.zip';
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $zipPath;
    }
}
