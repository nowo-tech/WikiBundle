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

use function array_filter;
use function array_slice;
use function array_values;
use function basename;
use function chmod;
use function clearstatcache;
use function count;
use function dirname;
use function explode;
use function fclose;
use function feof;
use function fileowner;
use function fopen;
use function fread;
use function fwrite;
use function glob;
use function hash;
use function implode;
use function in_array;
use function ini_get;
use function ini_parse_quantity;
use function is_dir;
use function is_link;
use function memory_get_usage;
use function mkdir;
use function preg_match;
use function register_shutdown_function;
use function sprintf;
use function str_contains;
use function str_ends_with;
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

    /** What an import or export leaves in the temp dir when the request dies: extraction, upload copy, export files. */
    private const TEMP_NAME_PATTERN = '/^wiki-(?:import-[0-9a-f]{16}|upload-[0-9a-f]{16}\.zip|export-[0-9a-f]{16}|export-[a-z0-9-]+-[0-9a-f]{8}\.zip)$/D';

    /** What {@see self::writeEntry()} reads at a time: an entry is never held in memory. */
    private const COPY_CHUNK_BYTES = 65536;

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
     * Extracts the Markdown documents of a ZIP archive into a new private temporary directory, after checking the
     * limits. Only {@code .md} files are written, the only files an import reads, and every directory an entry names
     * is created, since the reader's layout rules look at them. The limits are applied again to the bytes really
     * written: a ZIP can declare less than it holds (audit GV-M2).
     *
     * @param string $zipPath path of the ZIP archive
     *
     * @throws InvalidArgumentException when the archive is missing, unreadable or exceeds the limits
     * @throws RuntimeException when the temporary directory cannot be created or the disk cannot be written
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

            $targetDir = sys_get_temp_dir() . '/wiki-import-' . bin2hex(random_bytes(8));
            // @codeCoverageIgnoreStart
            if (!mkdir($targetDir, 0700, true) && !is_dir($targetDir)) {
                throw new RuntimeException('Unable to create temporary directory.');
            }
            // @codeCoverageIgnoreEnd

            $this->removeAtShutdown($targetDir);
            $this->removeStaleTempFiles($targetDir);

            try {
                $this->extractDocuments($zip, $targetDir);
            } catch (Throwable $e) {
                $this->removeDirectory($targetDir);

                throw $e;
            }
        } finally {
            $zip->close();
        }

        return $targetDir;
    }

    /**
     * Removes a temp file or directory when the process ends, for a fatal error (memory, time limit) that skips the
     * callers' finally (audit GV-L3). Does nothing if the caller has removed it by then. In worker mode this runs when
     * the worker script ends, which a fatal error does: there each call keeps one small callback until the worker
     * restarts, and imports are rare.
     *
     * @param string $path temp file or directory
     */
    public function removeAtShutdown(string $path): void
    {
        // @phpstan-ignore frankenphp.worker.noRegisterShutdownFunction (intentional: only a shutdown function runs after a fatal error)
        register_shutdown_function($this->removePath(...), $path);
    }

    /**
     * Writes the Markdown entries, one bounded chunk at a time, counting the bytes that really come out of each.
     *
     * @throws InvalidArgumentException when an entry cannot be read or written, or goes over a limit
     */
    private function extractDocuments(ZipArchive $zip, string $targetDir): void
    {
        $written = 0;
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $stat = $zip->statIndex($i);
            // @codeCoverageIgnoreStart
            if ($stat === false) {
                throw new InvalidArgumentException('Unable to read ZIP archive entry.');
            }
            // @codeCoverageIgnoreEnd

            $name     = $stat['name'];
            $document = !str_ends_with($name, '/') && $this->isMarkdown($name);
            $this->createDirectory($targetDir . '/' . (str_ends_with($name, '/') ? $name : dirname($name)), $name);
            if ($document) {
                $written += $this->writeEntry($zip, $i, $name, $targetDir . '/' . $name, $written);
            }
        }

        $this->assertFitsInMemory($written);
    }

    /**
     * @throws InvalidArgumentException when the directory cannot be created (a file holds its name)
     */
    private function createDirectory(string $directory, string $entry): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new InvalidArgumentException(sprintf('ZIP archive entry "%s" cannot be extracted.', $entry));
        }
    }

    /**
     * Copies one entry to the disk without holding it in memory, stopping before a limit is passed.
     *
     * @param int $writtenBefore bytes of the archive written so far
     *
     * @throws InvalidArgumentException when the entry cannot be read (encrypted, corrupt) or goes over a limit
     * @throws RuntimeException when the disk cannot be written
     *
     * @return int bytes written
     */
    private function writeEntry(ZipArchive $zip, int $index, string $name, string $path, int $writtenBefore): int
    {
        $source = @$zip->getStreamIndex($index);
        $target = @fopen($path, 'w');
        if ($source === false || $target === false) {
            throw new InvalidArgumentException(sprintf('ZIP archive entry "%s" cannot be extracted.', $name));
        }

        $bytes = 0;

        try {
            while (!feof($source)) {
                $chunk = @fread($source, self::COPY_CHUNK_BYTES);
                if ($chunk === false) {
                    throw new InvalidArgumentException(sprintf('ZIP archive entry "%s" cannot be extracted.', $name));
                }

                $bytes += strlen($chunk);
                if ($bytes > $this->maxDocumentBytes) {
                    throw new InvalidArgumentException(sprintf('ZIP archive entry "%s" exceeds the limit of %d bytes per document.', $name, $this->maxDocumentBytes));
                }
                if ($writtenBefore + $bytes > $this->maxUncompressedBytes) {
                    throw new InvalidArgumentException(sprintf('ZIP archive uncompressed size exceeds the limit of %d bytes.', $this->maxUncompressedBytes));
                }
                // @codeCoverageIgnoreStart
                if (fwrite($target, $chunk) !== strlen($chunk)) {
                    throw new RuntimeException('Unable to extract ZIP archive.');
                }
                // @codeCoverageIgnoreEnd
            }
        } finally {
            fclose($source);
            fclose($target);
        }

        return $bytes;
    }

    private function isMarkdown(string $name): bool
    {
        return str_ends_with(strtolower($name), '.md');
    }

    private function removePath(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            $this->removeDirectory($path);
        } elseif (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * Rejects ZIP bombs and path traversal before anything is written: caps the entry count, the distinct directories
     * and the declared total
     * uncompressed size, caps the declared size of each Markdown document and of all of them against
     * {@code memory_limit}, since the import reads them whole (audit GV-L3), and refuses absolute, {@code ..} and NUL
     * entry names. Declared sizes can lie, so {@see self::writeEntry()} applies the same limits to the real bytes.
     */
    private function assertWithinLimits(ZipArchive $zip): void
    {
        if ($zip->numFiles > $this->maxEntries) {
            throw new InvalidArgumentException(sprintf('ZIP archive has too many entries (%d, max %d).', $zip->numFiles, $this->maxEntries));
        }

        $total       = 0;
        $markdown    = 0;
        $directories = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $stat = $zip->statIndex($i);
            // @codeCoverageIgnoreStart
            if ($stat === false) {
                throw new InvalidArgumentException('Unable to read ZIP archive entry.');
            }
            // @codeCoverageIgnoreEnd

            $name = str_replace('\\', '/', $stat['name']);
            if (str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('#^[A-Za-z]:#', $name) === 1 || in_array('..', explode('/', $name), true)) {
                throw new InvalidArgumentException(sprintf('ZIP archive entry "%s" escapes the extraction directory.', $stat['name']));
            }

            $this->countDirectories($name, $directories);

            $total += $stat['size'];
            if ($total > $this->maxUncompressedBytes) {
                throw new InvalidArgumentException(sprintf('ZIP archive uncompressed size exceeds the limit of %d bytes.', $this->maxUncompressedBytes));
            }

            if (!$this->isMarkdown($name)) {
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
     * Adds the directories an entry names to the set, and refuses more than the entry limit (audit GV-L4): one short
     * name can hold thousands of nested directories, at a block of disk each. From the deepest up, stopping at one
     * already seen, whose ancestors are too. Hashed, so the set stays small however long the names are.
     *
     * @param string $name entry name, with forward slashes
     * @param array<string, true> $directories hashes of the directories seen
     *
     * @throws InvalidArgumentException over the limit
     */
    private function countDirectories(string $name, array &$directories): void
    {
        $parts = array_values(array_filter(explode('/', $name), static fn (string $part): bool => $part !== '' && $part !== '.'));
        $depth = str_ends_with($name, '/') || str_ends_with($name, '/.') ? count($parts) : count($parts) - 1;

        for (; $depth > 0; --$depth) {
            $key = hash('sha256', implode('/', array_slice($parts, 0, $depth)), true);
            if (isset($directories[$key])) {
                return;
            }

            $directories[$key] = true;
            if (count($directories) > $this->maxEntries) {
                throw new InvalidArgumentException(sprintf('ZIP archive names too many directories (max %d).', $this->maxEntries));
            }
        }
    }

    /**
     * Refuses documents that do not fit in {@code memory_limit}, as a clean refusal instead of a fatal error part way
     * through. The reader keeps every body in memory and holds one file twice while parsing it; twice the sum is a
     * margin above that. An import that writes these documents needs more still (measured under 256M: 25 MB finished,
     * 50 MB did not, and this refuses from about 85 MB). It can refuse an archive that would have finished writing
     * little or nothing, such as a re-import where every slug exists, or Notion {@code .md} files that are never read;
     * raising {@code memory_limit} is the way out.
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
     * Removes the temp files an import or export left behind when its request was killed (audit GV-L3, GV-I12): only this process
     * owner's, by exact name, older than {@see self::STALE_TEMP_SECONDS}, never through a link. Best effort: a
     * concurrent sweep may get there first, which must not fail the import.
     *
     * @param string $ownDir the directory just created for this import, which tells the owner without ext-posix
     */
    private function removeStaleTempFiles(string $ownDir): void
    {
        $owner  = fileowner($ownDir);
        $cutoff = time() - self::STALE_TEMP_SECONDS;

        foreach ([...(glob(sys_get_temp_dir() . '/wiki-import-*') ?: []), ...(glob(sys_get_temp_dir() . '/wiki-upload-*') ?: []), ...(glob(sys_get_temp_dir() . '/wiki-export-*') ?: [])] as $path) {
            if (preg_match(self::TEMP_NAME_PATTERN, basename($path)) !== 1 || is_link($path) || @fileowner($path) !== $owner || @filemtime($path) >= $cutoff) {
                continue;
            }

            try {
                $this->removePath($path);
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
