<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use Nowo\WikiBundle\Attachment\WikiAttachmentLimits;
use Nowo\WikiBundle\Attachment\WikiAttachmentLimitsPolicyInterface;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Port\Internal\WikiAttachmentLimitsProviderInterface;

/**
 * Asks a framework-free {@see WikiAttachmentLimitsPolicyInterface} (1.36.0) with the space's snapshot.
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
