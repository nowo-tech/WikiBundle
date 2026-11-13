<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Attachment;

use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;

/**
 * Upload limits per space, framework-free (1.34.0): the twin of the deprecated
 * {@see WikiAttachmentLimitsProviderInterface} (alias either interface to your service).
 */
interface WikiAttachmentLimitsPolicyInterface
{
    public function limits(WikiSpaceSnapshot $space): WikiAttachmentLimits;
}
