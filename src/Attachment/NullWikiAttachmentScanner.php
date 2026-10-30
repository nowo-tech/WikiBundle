<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Attachment;

/**
 * Default scanner: accepts every file (configure {@code attachments.scanner} with an adapter) (1.9).
 */
final class NullWikiAttachmentScanner implements WikiAttachmentScannerInterface
{
    public function scan(string $path, string $mimeType, string $originalName): ?string
    {
        return null;
    }
}
