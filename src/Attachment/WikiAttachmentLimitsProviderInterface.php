<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Attachment;

use Nowo\WikiBundle\Entity\WikiSpace;

/**
 * Gives the upload limits of a space; override the alias to read them from host settings (1.9).
 */
interface WikiAttachmentLimitsProviderInterface
{
    /** Returns the limits that apply to uploads into the space. */
    public function limits(WikiSpace $space): WikiAttachmentLimits;
}
