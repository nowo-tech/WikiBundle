<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange;

use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

use function sprintf;
use function strlen;

/**
 * ZIP helpers for Notion exports and wiki download bundles.
 */
final class WikiArchiveHelper
{
    /** Maximum number of entries accepted in an imported ZIP archive. */
    public const DEFAULT_MAX_ENTRIES = 10000;

    /** Maximum total uncompressed size (bytes) accepted in an imported ZIP archive (512 MiB). */
    public const DEFAULT_MAX_UNCOMPRESSED_BYTES = 536870912;

    public function __construct(
        private readonly int $maxEntries = self::DEFAULT_MAX_ENTRIES,
        private readonly int $maxUncompressedBytes = self::DEFAULT_MAX_UNCOMPRESSED_BYTES,
    ) {
    }

    public function isZipPath(string $path): bool
    {
        return is_file($path) && str_ends_with(strtolower($path), '.zip');
    }

    public function extractZip(string $zipPath): string
    {
        if (!is_file($zipPath)) {
            throw new InvalidArgumentException('Archive file not found.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new InvalidArgumentException('Unable to open ZIP archive.');
        }

        try {
            $this->assertWithinLimits($zip);
        } catch (InvalidArgumentException $e) {
            $zip->close();

            throw $e;
        }

        $targetDir = sys_get_temp_dir() . '/wiki-import-' . bin2hex(random_bytes(8));
        // @codeCoverageIgnoreStart
        if (!mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            $zip->close();
            throw new RuntimeException('Unable to create temporary directory.');
        }
        // @codeCoverageIgnoreEnd

        if (!$zip->extractTo($targetDir)) {
            // @codeCoverageIgnoreStart
            $zip->close();
            $this->removeDirectory($targetDir);

            throw new RuntimeException('Unable to extract ZIP archive.');
            // @codeCoverageIgnoreEnd
        }

        $zip->close();

        return $targetDir;
    }

    /**
     * Rejects ZIP bombs before extraction: caps the entry count and the declared total uncompressed size
     * (libzip never writes more than the declared size of an entry).
     */
    private function assertWithinLimits(ZipArchive $zip): void
    {
        if ($zip->numFiles > $this->maxEntries) {
            throw new InvalidArgumentException(sprintf('ZIP archive has too many entries (%d, max %d).', $zip->numFiles, $this->maxEntries));
        }

        $total = 0;
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $stat = $zip->statIndex($i);
            // @codeCoverageIgnoreStart
            if ($stat === false) {
                throw new InvalidArgumentException('Unable to read ZIP archive entry.');
            }
            // @codeCoverageIgnoreEnd

            $total += $stat['size'];
            if ($total > $this->maxUncompressedBytes) {
                throw new InvalidArgumentException(sprintf('ZIP archive uncompressed size exceeds the limit of %d bytes.', $this->maxUncompressedBytes));
            }
        }
    }

    public function createZipFromDirectory(string $sourceDir, string $zipPath): void
    {
        if (!is_dir($sourceDir)) {
            throw new InvalidArgumentException('Export directory not found.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create ZIP archive.');
        }

        $sourceDir = rtrim($sourceDir, '/\\');
        $iterator  = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $file) {
            // @codeCoverageIgnoreStart
            if (!$file instanceof SplFileInfo) {
                continue;
            }
            // @codeCoverageIgnoreEnd

            $path     = $file->getPathname();
            $relative = ltrim(substr($path, strlen($sourceDir)), '/\\');
            // @codeCoverageIgnoreStart
            if ($relative === '') {
                continue;
            }
            // @codeCoverageIgnoreEnd

            if ($file->isDir()) {
                $zip->addEmptyDir($relative);
            } else {
                $zip->addFile($path, $relative);
            }
        }

        $zip->close();
    }

    public function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            // @codeCoverageIgnoreStart
            if (!$file instanceof SplFileInfo) {
                continue;
            }
            // @codeCoverageIgnoreEnd

            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($directory);
    }
}
