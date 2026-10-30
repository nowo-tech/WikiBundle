<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Attachment;

/**
 * Upload limits of a space (1.9).
 */
final readonly class WikiAttachmentLimits
{
    /** Default allowlist: extension => accepted sniffed MIME types. SVG, HTML, XML and scripts are never listed. */
    public const DEFAULT_ALLOWED_TYPES = [
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf'  => ['application/pdf'],
        'txt'  => ['text/plain'],
        'md'   => ['text/plain', 'text/markdown', 'text/x-markdown'],
        'csv'  => ['text/csv', 'text/plain', 'application/csv'],
        'json' => ['application/json', 'text/plain'],
        'log'  => ['text/plain'],
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
    ];

    /**
     * @param int $maxFileBytes largest accepted file
     * @param int $maxSpaceBytes total bytes a space may hold
     * @param array<string, list<string>> $allowedTypes extension => accepted sniffed MIME types
     */
    public function __construct(
        public int $maxFileBytes = 10_485_760,
        public int $maxSpaceBytes = 524_288_000,
        public array $allowedTypes = self::DEFAULT_ALLOWED_TYPES,
    ) {
    }
}
