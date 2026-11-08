<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Value;

/**
 * 1.24 (spec 218 FR-037): a file a page shows, as the host lists it (only files the reader may open).
 */
final readonly class WikiApiFile
{
    /**
     * @param string $kind `image` or `file`
     */
    public function __construct(
        public string $uuid,
        public string $name,
        public string $kind,
        public ?string $mimeType = null,
        public ?int $size = null,
    ) {
    }
}
