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

use function chmod;
use function in_array;
use function sprintf;
use function strlen;

/**
 * ZIP helpers for Notion exports and wiki download bundles.
 */
final readonly class WikiArchiveHelper
{
    /** Maximum number of entries accepted in an imported ZIP archive. */
    public const DEFAULT_MAX_ENTRIES = 10000;

    /** Maximum total uncompressed size (bytes) accepted in an imported ZIP archive (512 MiB). */
    public const DEFAULT_MAX_UNCOMPRESSED_BYTES = 536870912;

    /**
     * Stores the ZIP-bomb limits applied when extracting imported archives.
     *
     * @param int $maxEntries maximum number of entries in an imported archive
     * @param int $maxUncompressedBytes maximum declared total uncompressed size in bytes
     */
    public function __construct(
        private int $maxEntries = self::DEFAULT_MAX_ENTRIES,
        private int $maxUncompressedBytes = self::DEFAULT_MAX_UNCOMPRESSED_BYTES,
    ) {
    }

    /**
     * Tells whether the path is an existing file with a {@code .zip} extension.
     *
     * @param string $path filesystem path
     *
     * @return bool true for an existing ZIP file path
     */
    public function isZipPath(string $path): bool
    {
        return is_file($path) && str_ends_with(strtolower($path), '.zip');
    }

    /**
     * Extracts a ZIP archive into a new private temporary directory after checking the entry count and size limits.
     *
     * @param string $zipPath path of the ZIP archive
     *
     * @throws InvalidArgumentException when the archive is missing, unreadable or exceeds the limits
     * @throws RuntimeException when the temporary directory cannot be created or extraction fails
     *
     * @return string path of the temporary directory holding the extracted files
     */
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
        if (!mkdir($targetDir, 0700, true) && !is_dir($targetDir)) {
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
     * Rejects ZIP bombs and path traversal before extraction: caps the entry count and the declared total
     * uncompressed size (libzip never writes more than the declared size of an entry) and refuses absolute
     * or {@code ..} entry names.
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

            $name = str_replace('\\', '/', $stat['name']);
            if (str_starts_with($name, '/') || preg_match('#^[A-Za-z]:#', $name) === 1 || in_array('..', explode('/', $name), true)) {
                throw new InvalidArgumentException(sprintf('ZIP archive entry "%s" escapes the extraction directory.', $stat['name']));
            }

            $total += $stat['size'];
            if ($total > $this->maxUncompressedBytes) {
                throw new InvalidArgumentException(sprintf('ZIP archive uncompressed size exceeds the limit of %d bytes.', $this->maxUncompressedBytes));
            }
        }
    }

    /**
     * Writes the directory tree into a ZIP archive (overwriting it) readable by the owner only.
     *
     * @param string $sourceDir directory to archive
     * @param string $zipPath path of the ZIP archive to create
     *
     * @throws InvalidArgumentException when the source directory does not exist
     * @throws RuntimeException when the archive cannot be created
     */
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

        // SKIP_DOTS: every entry is a real child of $sourceDir, so $relative is never empty.
        /** @var SplFileInfo $file RecursiveDirectoryIterator yields SplFileInfo (default flags) */
        foreach ($iterator as $file) {
            $path     = $file->getPathname();
            $relative = ltrim(substr($path, strlen($sourceDir)), '/\\');

            if ($file->isDir()) {
                $zip->addEmptyDir($relative);
            } else {
                $zip->addFile($path, $relative);
            }
        }

        $zip->close();
        // Exported spaces may be private: owner-only, whatever the umask (the archive sits in the shared temp dir).
        chmod($zipPath, 0o600);
    }

    /**
     * Recursively deletes a directory and its contents; no-op when it does not exist.
     *
     * @param string $directory directory to remove
     */
    public function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $file RecursiveDirectoryIterator yields SplFileInfo (default flags) */
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($directory);
    }
}
