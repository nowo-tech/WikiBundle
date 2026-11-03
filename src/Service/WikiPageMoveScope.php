<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

/**
 * What the actor of a move saw when it was allowed (1.14, audit DI-I1 / DI-I2): the pages they may read (the index
 * of the move and the position it answers count these siblings only) and the parent they were allowed to take the
 * page out of (a page moved by someone else in the meantime is refused as `stale`).
 */
final readonly class WikiPageMoveScope
{
    /**
     * @param array<string, true> $visibleIds ids of the pages the actor may read (page and every ancestor readable)
     * @param string|null $fromParentId the page's parent id when the move was checked (null: a root page)
     */
    public function __construct(
        public array $visibleIds,
        public ?string $fromParentId,
    ) {
    }
}
