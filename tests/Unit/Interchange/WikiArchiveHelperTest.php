<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Interchange;

use InvalidArgumentException;
use Nowo\WikiBundle\Interchange\WikiArchiveHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use ZipArchive;

use function count;
use function dirname;
use function ini_get;
use function sprintf;
use function strlen;

use const PHP_BINARY;

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

    public function testDocumentLimitIsInclusive(): void
    {
        $zipPath = $this->createZip(['exact.md' => str_repeat('a', 2048)]);
        $helper  = new WikiArchiveHelper(maxDocumentBytes: 2048);

        try {
            $extracted = $helper->extractZip($zipPath);
            self::assertSame(2048, filesize($extracted . '/exact.md'));
            $helper->removeDirectory($extracted);
        } finally {
            unlink($zipPath);
        }
    }

    /** Audit GV-M2: an entry that declares 100 bytes and holds 4 MiB is stopped by the bytes that really come out. */
    public function testEntryThatLiesAboutItsSizeIsStoppedAtTheDocumentLimit(): void
    {
        $zipPath = $this->lyingZip(['big.md' => [str_repeat('a', 4 * 1024 * 1024), 100]]);
        $helper  = new WikiArchiveHelper(maxDocumentBytes: 1024 * 1024);
        $before  = glob(sys_get_temp_dir() . '/wiki-import-*') ?: [];

        try {
            $helper->extractZip($zipPath);
            self::fail('Expected the entry to be refused.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('exceeds the limit of 1048576 bytes per document', $e->getMessage());
            self::assertSame($before, glob(sys_get_temp_dir() . '/wiki-import-*') ?: []);
        } finally {
            unlink($zipPath);
        }
    }

    public function testEntriesThatLieAboutTheirSizeAreStoppedAtTheTotalLimit(): void
    {
        $body    = str_repeat('a', 800 * 1024);
        $zipPath = $this->lyingZip(['a.md' => [$body, 100], 'b.md' => [$body, 100]]);
        $helper  = new WikiArchiveHelper(maxUncompressedBytes: 1024 * 1024);
        $before  = glob(sys_get_temp_dir() . '/wiki-import-*') ?: [];

        try {
            $helper->extractZip($zipPath);
            self::fail('Expected the archive to be refused.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('uncompressed size exceeds', $e->getMessage());
            self::assertSame($before, glob(sys_get_temp_dir() . '/wiki-import-*') ?: []);
        } finally {
            unlink($zipPath);
        }
    }

    public function testEntriesThatLieAreCheckedAgainstMemoryAfterExtraction(): void
    {
        $size    = (int) ((memory_get_usage(true) - memory_get_usage()) / 2) + 4 * 1024 * 1024;
        $zipPath = $this->lyingZip(['a.md' => [str_repeat('a', $size), 100], 'b.md' => [str_repeat('a', $size), 100]]);
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

    /** Audit GV-M2: nothing but Markdown reaches the disk, whatever it declares; the directories stay for the reader. */
    public function testOnlyMarkdownIsWrittenAndEveryDirectoryIsKept(): void
    {
        $zipPath = $this->lyingZip([
            'junk.bin'         => [str_repeat('a', 4 * 1024 * 1024), 100],
            'images/pic.png'   => ['png', 3],
            'empty/'           => ['', 0],
            'Export-1/page.MD' => ['# Page', 6],
        ]);

        try {
            $extracted = $this->helper->extractZip($zipPath);
            self::assertFileDoesNotExist($extracted . '/junk.bin');
            self::assertFileDoesNotExist($extracted . '/images/pic.png');
            self::assertDirectoryExists($extracted . '/images');
            self::assertDirectoryExists($extracted . '/empty');
            self::assertSame('# Page', file_get_contents($extracted . '/Export-1/page.MD'));
            $this->helper->removeDirectory($extracted);
        } finally {
            unlink($zipPath);
        }
    }

    /** The copy holds one chunk at a time, however large the entry. */
    public function testExtractionDoesNotBufferAnEntryInMemory(): void
    {
        $size    = 32 * 1024 * 1024;
        $zipPath = $this->lyingZip(['big.md' => [str_repeat('a', $size), 100]]);
        $helper  = new WikiArchiveHelper(maxDocumentBytes: $size);
        memory_reset_peak_usage();
        $before = memory_get_peak_usage();

        try {
            $extracted = $helper->extractZip($zipPath);
            self::assertLessThan(2 * 1024 * 1024, memory_get_peak_usage() - $before);
            self::assertSame($size, filesize($extracted . '/big.md'));
            $helper->removeDirectory($extracted);
        } finally {
            unlink($zipPath);
        }
    }

    /**
     * @param array<string, array{string, int}> $entries
     */
    #[DataProvider('conflictingEntries')]
    public function testEntryThatCannotBeWrittenIsRefused(array $entries): void
    {
        $zipPath = $this->lyingZip($entries);
        $before  = glob(sys_get_temp_dir() . '/wiki-import-*') ?: [];

        try {
            $this->helper->extractZip($zipPath);
            self::fail('Expected the entry to be refused.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('cannot be extracted', $e->getMessage());
            self::assertSame($before, glob(sys_get_temp_dir() . '/wiki-import-*') ?: []);
        } finally {
            unlink($zipPath);
        }
    }

    /**
     * @return iterable<string, array{array<string, array{string, int}>}>
     */
    public static function conflictingEntries(): iterable
    {
        yield 'a file holds the directory name' => [['a.md' => ['x', 1], 'a.md/b.md' => ['y', 1]]];
        yield 'a directory holds the file name' => [['a.md/' => ['', 0], 'a.md' => ['y', 1]]];
    }

    public function testCorruptEntryIsRefusedNotTruncated(): void
    {
        $zipPath = $this->lyingZip(['a.md' => [str_repeat('a', 4096), 4096]], garbage: true);
        $before  = glob(sys_get_temp_dir() . '/wiki-import-*') ?: [];

        try {
            $this->helper->extractZip($zipPath);
            self::fail('Expected the entry to be refused.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('cannot be extracted', $e->getMessage());
            self::assertSame($before, glob(sys_get_temp_dir() . '/wiki-import-*') ?: []);
        } finally {
            unlink($zipPath);
        }
    }

    public function testNulInAnEntryNameIsNotABypass(): void
    {
        $zipPath = $this->lyingZip(["a.md\0.txt" => ['x', 1]]);

        try {
            $extracted = $this->helper->extractZip($zipPath);
            self::assertSame([], glob($extracted . '/*.txt') ?: []);
            $this->helper->removeDirectory($extracted);
        } catch (InvalidArgumentException $e) {
            self::assertNotSame('', $e->getMessage());
        } finally {
            unlink($zipPath);
        }
    }

    public function testSymlinkEntryIsWrittenAsAPlainFile(): void
    {
        $zipPath = $this->createZip(['link.md' => '/etc/passwd']);
        $zip     = new ZipArchive();
        $zip->open($zipPath);
        $zip->setExternalAttributesName('link.md', ZipArchive::OPSYS_UNIX, 0120777 << 16);
        $zip->close();

        try {
            $extracted = $this->helper->extractZip($zipPath);
            self::assertFalse(is_link($extracted . '/link.md'));
            self::assertSame('/etc/passwd', file_get_contents($extracted . '/link.md'));
            $this->helper->removeDirectory($extracted);
        } finally {
            unlink($zipPath);
        }
    }

    /**
     * Audit GV-L3: a real fatal error skips every finally; the extraction and the upload's copy still go. Its own PHP
     * process, since the fatal ends it.
     */
    public function testFatalErrorLeavesNoTempFiles(): void
    {
        $zipPath = $this->createZip(['a.md' => 'a']);
        $script  = $zipPath . '.php';
        $upload  = sys_get_temp_dir() . '/wiki-upload-' . bin2hex(random_bytes(8)) . '.zip';
        copy($zipPath, $upload);
        file_put_contents($script, sprintf(<<<'PHP'
            <?php
            require %s;
            $helper = new Nowo\WikiBundle\Interchange\WikiArchiveHelper();
            $helper->removeAtShutdown(%s);
            echo $helper->extractZip(%s), "\n";
            ini_set('memory_limit', '32M');
            str_repeat('x', 256 * 1024 * 1024);
            PHP, var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true), var_export($upload, true), var_export($zipPath, true)));

        try {
            $output = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=1 ' . escapeshellarg($script) . ' 2>&1');
            $dir    = strtok($output, "\n");
            self::assertIsString($dir);
            self::assertStringContainsString('/wiki-import-', $dir);
            self::assertStringContainsString('Allowed memory size', $output);
            clearstatcache();
            self::assertDirectoryDoesNotExist($dir);
            self::assertFileDoesNotExist($upload);
        } finally {
            unlink($zipPath);
            unlink($script);
            if (is_file($upload)) {
                unlink($upload);
            }
        }
    }

    /** What the shutdown callback does: runs after PHPUnit stops measuring, so it is also called directly. */
    public function testShutdownCleanupRemovesFilesAndDirectoriesAndIgnoresTheRest(): void
    {
        $remove = new ReflectionMethod(WikiArchiveHelper::class, 'removePath');
        $base   = sys_get_temp_dir() . '/wiki-shutdown-' . bin2hex(random_bytes(4));
        mkdir($base . '/dir/sub', 0700, true);
        file_put_contents($base . '/file', 'x');
        file_put_contents($base . '/target', 'x');
        symlink($base . '/dir/sub', $base . '/link');

        try {
            $remove->invoke($this->helper, $base . '/file');
            self::assertFileDoesNotExist($base . '/file');
            $remove->invoke($this->helper, $base . '/link');
            self::assertDirectoryExists($base . '/dir/sub', 'A link is never followed.');
            $remove->invoke($this->helper, $base . '/dir');
            self::assertDirectoryDoesNotExist($base . '/dir');
            $remove->invoke($this->helper, $base . '/missing');
            $this->helper->removeAtShutdown($base . '/missing');
        } finally {
            unlink($base . '/link');
            unlink($base . '/target');
            rmdir($base);
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
        $tmp            = sys_get_temp_dir();
        $staleDir       = $tmp . '/wiki-import-' . bin2hex(random_bytes(8));
        $staleUpload    = $tmp . '/wiki-upload-' . bin2hex(random_bytes(8)) . '.zip';
        $freshDir       = $tmp . '/wiki-import-' . bin2hex(random_bytes(8));
        $foreignDir     = $tmp . '/wiki-import-keep-' . bin2hex(random_bytes(8));
        $victim         = $tmp . '/wiki-victim-' . bin2hex(random_bytes(8));
        $staleLink      = $tmp . '/wiki-import-' . bin2hex(random_bytes(8));
        $staleExport    = $tmp . '/wiki-export-' . bin2hex(random_bytes(8));
        $staleExportZip = $tmp . '/wiki-export-my-space-' . bin2hex(random_bytes(4)) . '.zip';
        $newlineName    = $tmp . '/wiki-upload-' . bin2hex(random_bytes(8)) . ".zip\n";
        file_put_contents($staleExportZip, 'x');
        file_put_contents($newlineName, 'x');
        foreach ([$staleDir . '/sub', $freshDir, $foreignDir, $victim, $staleExport] as $dir) {
            mkdir($dir, 0700, true);
        }
        file_put_contents($staleDir . '/sub/page.md', 'x');
        file_put_contents($staleUpload, 'x');
        file_put_contents($victim . '/keep.txt', 'x');
        symlink($victim, $staleLink);
        $old = time() - 3700;
        foreach ([$staleDir, $staleUpload, $foreignDir, $victim, $staleExport, $staleExportZip, $newlineName] as $path) {
            touch($path, $old);
        }
        clearstatcache();

        $zipPath = $this->createZip(['a.md' => 'a']);

        try {
            $extracted = $this->helper->extractZip($zipPath);
            clearstatcache();
            self::assertDirectoryDoesNotExist($staleDir);
            self::assertFileDoesNotExist($staleUpload);
            self::assertDirectoryDoesNotExist($staleExport);
            self::assertFileDoesNotExist($staleExportZip);
            self::assertFileExists($newlineName, 'The name must match to its very end.');
            self::assertDirectoryExists($freshDir, 'A recent directory may be a live import.');
            self::assertDirectoryExists($foreignDir, 'Only the exact names of the bundle are removed.');
            self::assertFileExists($victim . '/keep.txt', 'A link is never followed.');
            self::assertTrue(is_link($staleLink));
            self::assertFileExists($extracted . '/a.md');
            $this->helper->removeDirectory($extracted);
        } finally {
            unlink($zipPath);
            unlink($staleLink);
            unlink($newlineName);
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

    /** Audit GV-L4: one short name can hold thousands of directories; they are refused before anything is written. */
    public function testDirectoriesOverTheEntryLimitAreRefusedBeforeWriting(): void
    {
        $zipPath = $this->createZip(['a/b/c/d/page.md' => 'x']);
        $helper  = new WikiArchiveHelper(maxEntries: 3);
        $before  = glob(sys_get_temp_dir() . '/wiki-import-*') ?: [];

        try {
            $helper->extractZip($zipPath);
            self::fail('Expected the archive to be refused.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('too many directories (max 3)', $e->getMessage());
            self::assertSame($before, glob(sys_get_temp_dir() . '/wiki-import-*') ?: []);
        } finally {
            unlink($zipPath);
        }
    }

    /** The default limit against what the audit ran: nested directories under one short name, in a few entries. */
    public function testDefaultLimitRefusesNestedDirectoriesInOneEntry(): void
    {
        $zipPath = $this->createZip([str_repeat('d/', WikiArchiveHelper::DEFAULT_MAX_ENTRIES + 1) . 'page.md' => 'x']);

        try {
            $this->expectExceptionMessage('too many directories (max 10000)');
            $this->helper->extractZip($zipPath);
        } finally {
            unlink($zipPath);
        }
    }

    /** A shared ancestor counts once, spelling does not matter, and a file is not a directory. */
    public function testDirectoriesExactlyAtTheLimitPassAndEachCountsOnce(): void
    {
        $zipPath = $this->createZip(['a/b/one.md' => '1', 'a/b/two.md' => '2', './a//b/three.md' => '3', 'a/c/' => '', 'root.md' => 'r', 'a/b/skip.png' => 'p']);
        $helper  = new WikiArchiveHelper(maxEntries: 6);

        try {
            $extracted = $helper->extractZip($zipPath);
            self::assertFileExists($extracted . '/a/b/three.md');
            self::assertDirectoryExists($extracted . '/a/c');
            $helper->removeDirectory($extracted);
        } finally {
            unlink($zipPath);
        }

        $zipPath = $this->createZip(['a/b/c/page.md' => 'x', 'a/b/other.md' => 'y']);
        try {
            $extracted = (new WikiArchiveHelper(maxEntries: 3))->extractZip($zipPath);
            self::assertFileExists($extracted . '/a/b/c/page.md');
            $helper->removeDirectory($extracted);
        } finally {
            unlink($zipPath);
        }
    }

    /** A legitimate archive with a deep tree passes under the default limit. */
    public function testDeepTreeWithinTheDefaultLimitPasses(): void
    {
        $entries = [];
        for ($i = 0; $i < 200; ++$i) {
            $entries['Export-1/section-' . $i . '/' . str_repeat('level/', 30) . 'page.md'] = '# Page ' . $i;
        }
        $zipPath = $this->createZip($entries);

        try {
            $extracted = $this->helper->extractZip($zipPath);
            self::assertFileExists($extracted . '/Export-1/section-199/' . str_repeat('level/', 30) . 'page.md');
            $this->helper->removeDirectory($extracted);
        } finally {
            unlink($zipPath);
        }
    }

    /**
     * A ZIP whose local header and central directory say what the test wants: libzip writes the real bytes anyway.
     *
     * @param array<string, array{string, int}> $entries name => [real content, declared size]
     */
    private function lyingZip(array $entries, bool $garbage = false): string
    {
        $zipPath = sys_get_temp_dir() . '/wiki-archive-' . bin2hex(random_bytes(4)) . '.zip';
        $body    = '';
        $central = '';
        foreach ($entries as $name => [$content, $declared]) {
            $name       = (string) $name;
            $sum        = crc32($content);
            $compressed = $garbage ? str_repeat("\xFF", 64) : (string) gzdeflate($content, 6);
            $offset     = strlen($body);
            $body .= "PK\x03\x04" . pack('vvvvvVVVvv', 20, 0, 8, 0, 0x21, $sum, strlen($compressed), $declared, strlen($name), 0) . $name . $compressed;
            $central .= "PK\x01\x02" . pack('vvvvvvVVVvvvvvVV', 20, 20, 0, 8, 0, 0x21, $sum, strlen($compressed), $declared, strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
        }
        file_put_contents($zipPath, $body . $central . "PK\x05\x06" . pack('vvvvVVv', 0, 0, count($entries), count($entries), strlen($central), strlen($body), 0));

        return $zipPath;
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
