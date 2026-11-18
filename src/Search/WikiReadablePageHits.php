<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Search;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;

/**
 * The pages of a {@see WikiReadablePageFinder} search the viewer may read (1.44), in page-id order, and whether the
 * candidate scan reached the end of the matches (`$complete`: then `count($hits)` is the exact number of readable
 * matches; otherwise the scan stopped at `search_kit_provider.candidates` rows and the number is a lower bound).
 */
final readonly class WikiReadablePageHits
{
    /**
     * @param list<array{page: WikiPage, space: WikiSpace, excerpt: string}> $hits readable hits only
     * @param bool $complete every matching row was read
     */
    public function __construct(
        public array $hits,
        public bool $complete,
    ) {
    }
}
