<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Nowo\WikiBundle\Entity\WikiPage;

/**
 * Outcome of {@see WikiPageMover::createAt()} and {@see WikiPageMover::createNextTo()} (1.35): the new page and its
 * place among the siblings the actor sees (with a scope; every sibling without one), never the stored sort key, which
 * counts hidden siblings too (audit PT-L1).
 */
final readonly class WikiPageCreateResult
{
    /**
     * @param WikiPage $page the new page, placed
     * @param int $index 0-based index of the page among the siblings the actor sees
     * @param int $count number of those siblings, the page included
     */
    public function __construct(
        public WikiPage $page,
        public int $index,
        public int $count,
    ) {
    }
}
