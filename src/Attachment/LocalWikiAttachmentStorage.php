<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Attachment;

use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

use function dirname;
use function fclose;
use function fopen;
use function is_dir;
use function is_file;
use function is_resource;
use function mkdir;
use function preg_match;
use function rename;
use function rtrim;
use function stream_copy_to_stream;
use function strlen;
use function substr;
use function unlink;

/**
 * Default {@see WikiAttachmentStorageInterface}: a private local directory (outside {@code public/}) (1.9).
 */
final readonly class LocalWikiAttachmentStorage implements WikiAttachmentStorageInterface
{
    /** Allowed key shape: lower-case segments of letters, digits and dashes. */
    private const KEY_PATTERN = '#^[a-z0-9-]+(?:/[a-z0-9-]+)*$#';

    /**
     * @param string $directory absolute directory holding the files
     */
    public function __construct(
        private string $directory,
    ) {
    }

    public function write(string $key, mixed $stream): void
    {
        $path = $this->path($key);
        $dir  = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0o700, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create the attachment directory.');
        }

        $temp   = $path . '.part';
        $target = @fopen($temp, 'x');
        if (!is_resource($target)) {
            throw new RuntimeException('Unable to open the attachment file for writing.');
        }

        try {
            $copied = stream_copy_to_stream($stream, $target);
        } finally {
            fclose($target);
        }

        if ($copied === false || !rename($temp, $path)) {
            // @codeCoverageIgnoreStart
            @unlink($temp);

            throw new RuntimeException('Unable to write the attachment file.');
            // @codeCoverageIgnoreEnd
        }
    }

    public function readStream(string $key): mixed
    {
        $path   = $this->path($key);
        $handle = is_file($path) ? @fopen($path, 'r') : false;
        if (!is_resource($handle)) {
            throw new RuntimeException('Attachment file not found.');
        }

        return $handle;
    }

    public function delete(string $key): void
    {
        $path = $this->path($key);
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function keys(): iterable
    {
        $root = rtrim($this->directory, '/');
        if (!is_dir($root)) {
            return;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $key = substr($file->getPathname(), strlen($root) + 1);
            if ($file->isFile() && preg_match(self::KEY_PATTERN, $key) === 1) {
                yield $key;
            }
        }
    }

    private function path(string $key): string
    {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException('Invalid attachment key.');
        }

        return rtrim($this->directory, '/') . '/' . $key;
    }
}
