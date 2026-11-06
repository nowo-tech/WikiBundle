<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\PageFile;

use function floor;
use function round;

/**
 * A file a page refers to (1.20): a Milkdown file card `::file[name]{href size type}` or an image `![alt](url)`.
 * Values come from the page's Markdown (anyone with edit rights writes them): the URL is relative or http(s) only, the
 * name is cleaned and cut, and they are escaped where they are shown.
 */
final readonly class WikiPageFile
{
    public const KIND_FILE  = 'file';
    public const KIND_IMAGE = 'image';

    /**
     * @param string $url relative or http(s) URL of the stored file
     * @param string $name file name (the card label, or the image alt text)
     * @param string $kind {@see self::KIND_FILE} or {@see self::KIND_IMAGE}
     * @param int|null $size size in bytes from the card (null when unknown)
     * @param string|null $type MIME type from the card (null when unknown)
     */
    public function __construct(
        public string $url,
        public string $name,
        public string $kind = self::KIND_FILE,
        public ?int $size = null,
        public ?string $type = null,
    ) {
    }

    /**
     * The size as `512 B`, `1.5 KB`, `12 MB` (1024 steps, one decimal under 10, as Milkdown's file cards), or null.
     */
    public function sizeLabel(): ?string
    {
        if ($this->size === null || $this->size < 0) {
            return null;
        }
        if ($this->size < 1024) {
            return $this->size . ' B';
        }
        $size  = (float) $this->size;
        $units = ['KB', 'MB', 'GB', 'TB'];
        $unit  = -1;
        while ($size >= 1024 && $unit < 3) {
            $size /= 1024;
            ++$unit;
        }
        $rounded = $size < 10 ? round($size, 1) : round($size);

        return ($rounded === floor($rounded) ? (string) (int) $rounded : (string) $rounded) . ' ' . $units[$unit];
    }
}
