<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Attachment;

use Nowo\WikiBundle\Entity\WikiSpace;

/**
 * Default limits provider: the {@code nowo_wiki.attachments} configuration, the same for every space (1.9).
 */
final readonly class ConfiguredWikiAttachmentLimitsProvider implements WikiAttachmentLimitsProviderInterface
{
    /**
     * @param int $maxFileBytes largest accepted file
     * @param int $maxSpaceBytes total bytes a space may hold
     * @param array<string, list<string>> $allowedTypes extension => accepted sniffed MIME types
     */
    public function __construct(
        private int $maxFileBytes = 10_485_760,
        private int $maxSpaceBytes = 524_288_000,
        private array $allowedTypes = WikiAttachmentLimits::DEFAULT_ALLOWED_TYPES,
    ) {
    }

    public function limits(WikiSpace $space): WikiAttachmentLimits
    {
        return new WikiAttachmentLimits($this->maxFileBytes, $this->maxSpaceBytes, $this->allowedTypes);
    }
}
