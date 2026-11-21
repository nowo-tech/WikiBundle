<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Query;

use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;

/**
 * 1.51: one page found by {@see WikiPageQueryInterface::searchPages()}.
 */
final readonly class WikiPageSearchHit
{
    /**
     * @param WikiPageSnapshot $page the page
     * @param WikiSpaceSnapshot $space its space
     * @param string $excerpt plain text around a text match, never the Markdown source; '' for a page found by its title
     */
    public function __construct(
        public WikiPageSnapshot $page,
        public WikiSpaceSnapshot $space,
        public string $excerpt,
    ) {
    }
}
