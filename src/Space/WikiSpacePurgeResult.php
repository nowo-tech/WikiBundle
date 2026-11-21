<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Space;

/**
 * 1.50: the outcome of {@see WikiSpaceLifecycleInterface::purgeSpace()}.
 */
final readonly class WikiSpacePurgeResult
{
    /**
     * @param string $spaceId the space
     * @param bool $finished true: the space and everything in it are gone; false: the batch budget of this call ran out,
     *                       call again to go on (the space stays archived and marked as being purged)
     * @param int $pages pages deleted by this call
     * @param int $batches batches committed by this call
     */
    public function __construct(
        public string $spaceId,
        public bool $finished,
        public int $pages,
        public int $batches,
    ) {
    }
}
