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

use function strlen;

/**
 * ZIP helpers for Notion exports and wiki download bundles.
 */
final class WikiArchiveHelper
{
    public function isZipPath(string $path): bool
    {
        return is_file($path) && str_ends_with(strtolower($path), '.zip');
    }

    public function extractZip(string $zipPath): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required for archive import/export.');
        }

        if (!is_file($zipPath)) {
            throw new InvalidArgumentException('Archive file not found.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new InvalidArgumentException('Unable to open ZIP archive.');
        }

        $targetDir = sys_get_temp_dir() . '/wiki-import-' . bin2hex(random_bytes(8));
        if (!mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            $zip->close();
            throw new RuntimeException('Unable to create temporary directory.');
        }

        if (!$zip->extractTo($targetDir)) {
            $zip->close();
            $this->removeDirectory($targetDir);
            throw new RuntimeException('Unable to extract ZIP archive.');
        }

        $zip->close();

        return $targetDir;
    }

    public function createZipFromDirectory(string $sourceDir, string $zipPath): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required for archive import/export.');
        }

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
            if (!$file instanceof SplFileInfo) {
                continue;
            }

            $path     = $file->getPathname();
            $relative = ltrim(substr($path, strlen($sourceDir)), '/\\');
            if ($relative === '') {
                continue;
            }

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
            if (!$file instanceof SplFileInfo) {
                continue;
            }

            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($directory);
    }
}
