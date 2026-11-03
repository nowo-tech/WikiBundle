<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

/**
 * Outcome of {@see WikiPageMover::move()} (1.14).
 */
final readonly class WikiPageMoveResult
{
    /**
     * @param int $index 0-based index of the page among its siblings
     * @param int $count number of siblings, the page included
     * @param bool $moved false when the page already was there (nothing written, no event)
     */
    public function __construct(
        public int $index,
        public int $count,
        public bool $moved,
    ) {
    }
}
