<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use Nowo\WikiBundle\Attachment\WikiAttachmentLimits;
use Nowo\WikiBundle\Attachment\WikiAttachmentLimitsPolicyInterface;
use Nowo\WikiBundle\Attachment\WikiAttachmentLimitsProviderInterface;
use Nowo\WikiBundle\Entity\WikiSpace;

/**
 * Asks a framework-free {@see WikiAttachmentLimitsPolicyInterface} (1.32.0) with the space's snapshot.
 *
 * @internal
 */
final readonly class WikiAttachmentLimitsPolicyGate implements WikiAttachmentLimitsProviderInterface
{
    public function __construct(private WikiAttachmentLimitsPolicyInterface $policy)
    {
    }

    public function limits(WikiSpace $space): WikiAttachmentLimits
    {
        return $this->policy->limits(WikiSnapshots::space($space));
    }
}
