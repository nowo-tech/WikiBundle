<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Attachment;

/**
 * Scans an uploaded file before it is stored (an antivirus adapter, a content policy) (1.9).
 */
interface WikiAttachmentScannerInterface
{
    /**
     * Returns null when the file may be stored, or a short reason when it is refused (nothing is stored then).
     *
     * @param string $path local path of the uploaded file
     * @param string $mimeType MIME type sniffed on the server
     * @param string $originalName sanitized original name
     *
     * @return string|null rejection reason, or null
     */
    public function scan(string $path, string $mimeType, string $originalName): ?string;
}
