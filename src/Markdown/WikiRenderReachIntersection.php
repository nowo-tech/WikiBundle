<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown;

use Nowo\WikiBundle\Entity\WikiSpace;

/**
 * 1.24 (spec 218, audit IF-I7): the reach of a render nested in another that has its own: a space counts only when both
 * reach it.
 */
final readonly class WikiRenderReachIntersection implements WikiRenderReach
{
    public function __construct(
        private WikiRenderReach $outer,
        private WikiRenderReach $inner,
    ) {
    }

    public function reachesSpace(WikiSpace $space): bool
    {
        return $this->outer->reachesSpace($space) && $this->inner->reachesSpace($space);
    }
}
