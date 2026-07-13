<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Interchange;

use InvalidArgumentException;
use Nowo\WikiBundle\Interchange\WikiArchiveHelper;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

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
}
