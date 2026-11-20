<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Attachment;

use Nowo\WikiBundle\Entity\WikiSpace;

/**
 * Gives the upload limits of a space; override the alias to read them from host settings (1.9).
 *
 * @deprecated since 1.36, removed in 2.0: implement {@see WikiAttachmentLimitsPolicyInterface} instead
 *             (1.49: a host service that still implements it triggers a deprecation notice)
 */
interface WikiAttachmentLimitsProviderInterface
{
    /** Returns the limits that apply to uploads into the space. */
    public function limits(WikiSpace $space): WikiAttachmentLimits;
}
