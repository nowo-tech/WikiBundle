<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Attachment;

use RuntimeException;

/**
 * An upload refused by the limits, the type allowlist or the scanner; nothing was stored (1.9).
 */
final class WikiAttachmentRejectedException extends RuntimeException
{
    public const EMPTY            = 'empty';
    public const TOO_LARGE        = 'too_large';
    public const QUOTA_EXCEEDED   = 'quota_exceeded';
    public const TYPE_NOT_ALLOWED = 'type_not_allowed';
    public const TYPE_MISMATCH    = 'type_mismatch';
    public const SCANNER          = 'scanner_rejected';

    /**
     * @param string $reason one of the class constants
     * @param string|null $detail scanner reason, when the scanner refused the file
     */
    public function __construct(
        public readonly string $reason,
        public readonly ?string $detail = null,
    ) {
        parent::__construct('Attachment rejected: ' . $reason . '.');
    }
}
