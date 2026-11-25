<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Attachment;

use Nowo\WikiBundle\Port\Internal\WikiAttachmentLimitsProviderInterface;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;

/**
 * Upload limits per space, framework-free (1.36.0): since 2.0 the only host port; it replaced the removed 1.x
 * `WikiAttachmentLimitsProviderInterface` (alias this interface to your service).
 */
interface WikiAttachmentLimitsPolicyInterface
{
    public function limits(WikiSpaceSnapshot $space): WikiAttachmentLimits;
}
