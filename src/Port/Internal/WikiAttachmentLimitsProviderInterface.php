<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port\Internal;

use Nowo\WikiBundle\Attachment\WikiAttachmentLimits;
use Nowo\WikiBundle\Attachment\WikiAttachmentLimitsPolicyInterface;
use Nowo\WikiBundle\Entity\WikiSpace;

/**
 * Gives the upload limits of a space; override the alias to read them from host settings (1.9).
 *
 * @internal Bundle side of a host port since 2.0 (the 1.x host interface of this name was removed): a host implements
 *           {@see WikiAttachmentLimitsPolicyInterface} instead.
 */
interface WikiAttachmentLimitsProviderInterface
{
    /** Returns the limits that apply to uploads into the space. */
    public function limits(WikiSpace $space): WikiAttachmentLimits;
}
