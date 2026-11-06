<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange;

use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;
use ZipArchive;

use function basename;
use function chmod;
use function clearstatcache;
use function fileowner;
use function glob;
use function in_array;
use function ini_get;
use function ini_parse_quantity;
use function is_dir;
use function is_link;
use function memory_get_usage;
use function preg_match;
use function sprintf;
use function strlen;
use function strtolower;
use function time;

/**
 * ZIP helpers for Notion exports and wiki download bundles.
 */
final readonly class WikiArchiveHelper
{
    /** Maximum number of entries accepted in an imported ZIP archive. */
    public const DEFAULT_MAX_ENTRIES = 10000;

    /** Maximum total uncompressed size (bytes) accepted in an imported ZIP archive (512 MiB). */
    public const DEFAULT_MAX_UNCOMPRESSED_BYTES = 536870912;

    /** Maximum uncompressed size (bytes) of one Markdown document of an imported ZIP archive (8 MiB, audit GV-L3). */
    public const DEFAULT_MAX_DOCUMENT_BYTES = 8388608;

    /** Temp files of an import older than this are leftovers of a killed request: a live one reads them in seconds. */
    private const STALE_TEMP_SECONDS = 3600;

    /** Names of what an import leaves in the temp dir when the request dies: the extraction and the upload's copy. */
    private const TEMP_NAME_PATTERN = '/^wiki-(?:import-[0-9a-f]{16}|upload-[0-9a-f]{16}\.zip)$/';

    /**
     * Stores the ZIP-bomb limits applied when extracting imported archives.
     *
     * @param int $maxEntries maximum number of entries in an imported archive
     * @param int $maxUncompressedBytes maximum declared total uncompressed size in bytes
     * @param int $maxDocumentBytes maximum declared uncompressed size of one Markdown document in bytes
     */
    public function __construct(
        private int $maxEntries = self::DEFAULT_MAX_ENTRIES,
        private int $maxUncompressedBytes = self::DEFAULT_MAX_UNCOMPRESSED_BYTES,
        private int $maxDocumentBytes = self::DEFAULT_MAX_DOCUMENT_BYTES,
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

        $this->removeStaleTempFiles($targetDir);

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
     * uncompressed size (libzip never writes more than the declared size of an entry), caps the declared size
     * of each Markdown document and of all of them against {@code memory_limit}, since the import reads them whole
     * (audit GV-L3), and refuses absolute or {@code ..} entry names.
     */
    private function assertWithinLimits(ZipArchive $zip): void
    {
        if ($zip->numFiles > $this->maxEntries) {
            throw new InvalidArgumentException(sprintf('ZIP archive has too many entries (%d, max %d).', $zip->numFiles, $this->maxEntries));
        }

        $total    = 0;
        $markdown = 0;
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

            if (!str_ends_with(strtolower($name), '.md')) {
                continue;
            }

            if ($stat['size'] > $this->maxDocumentBytes) {
                throw new InvalidArgumentException(sprintf('ZIP archive entry "%s" exceeds the limit of %d bytes per document.', $stat['name'], $this->maxDocumentBytes));
            }
            $markdown += $stat['size'];
        }

        $this->assertFitsInMemory($markdown);
    }

    /**
     * Refuses documents that cannot be read within {@code memory_limit}. The reader keeps every body in memory and
     * briefly holds a file and its parsed copy, so at worst it needs twice their size. The import itself needs several
     * times that, so this refuses nothing that could have finished; it ends a request cleanly that would die on a fatal
     * error, which skips the callers' cleanup, with the extraction still in the temp dir.
     *
     * @param int $markdownBytes declared size of the archive's Markdown documents
     */
    private function assertFitsInMemory(int $markdownBytes): void
    {
        $limit = ini_parse_quantity((string) ini_get('memory_limit'));
        if ($limit > 0 && $markdownBytes * 2 > $limit - memory_get_usage()) {
            throw new InvalidArgumentException(sprintf('ZIP archive Markdown documents (%d bytes) do not fit in the memory limit of this server.', $markdownBytes));
        }
    }

    /**
     * Removes the temp files an import left behind when its request was killed (audit GV-L3): only this process
     * owner's, by exact name, older than {@see self::STALE_TEMP_SECONDS}, never through a link. Best effort: a
     * concurrent sweep may get there first, which must not fail the import.
     *
     * @param string $ownDir the directory just created for this import, which tells the owner without ext-posix
     */
    private function removeStaleTempFiles(string $ownDir): void
    {
        $owner  = fileowner($ownDir);
        $cutoff = time() - self::STALE_TEMP_SECONDS;

        foreach ([...(glob(sys_get_temp_dir() . '/wiki-import-*') ?: []), ...(glob(sys_get_temp_dir() . '/wiki-upload-*') ?: [])] as $path) {
            if (preg_match(self::TEMP_NAME_PATTERN, basename($path)) !== 1 || is_link($path) || @fileowner($path) !== $owner || @filemtime($path) >= $cutoff) {
                continue;
            }

            try {
                is_dir($path) ? $this->removeDirectory($path) : unlink($path);
                // @codeCoverageIgnoreStart
            } catch (Throwable) {
                continue;
            }
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Writes the directory tree into a ZIP archive (overwriting it) readable by the owner only.
     *
     * @param string $sourceDir directory to archive
     * @param string $zipPath path of the ZIP archive to create
     *
     * @throws InvalidArgumentException when the source directory does not exist
     * @throws RuntimeException when the archive cannot be created, or was not written (1.15.1: ZipArchive writes no
     *                          file for an empty directory, so callers would otherwise get a path that does not exist)
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

        if (!$zip->close() || !is_file($zipPath)) {
            throw new RuntimeException('ZIP archive was not written (empty export directory?).');
        }
        // Exported spaces may be private: owner-only, whatever the umask (the archive sits in the shared temp dir).
        chmod($zipPath, 0o600);
        // is_file() above cached the pre-chmod stat; later stat calls (fileperms, BinaryFileResponse) must see 0600.
        clearstatcache(true, $zipPath);
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
