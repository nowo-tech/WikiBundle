<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Query;

/**
 * 1.51: the outcome of {@see WikiPageQueryInterface::searchPages()}.
 */
final readonly class WikiPageSearchResult
{
    /**
     * @param list<WikiPageSearchHit> $hits title matches first, then text matches, each in page id order
     * @param bool $complete whether every matching row was read (false: the candidate budget ran out first)
     */
    public function __construct(
        public array $hits,
        public bool $complete,
    ) {
    }
}
