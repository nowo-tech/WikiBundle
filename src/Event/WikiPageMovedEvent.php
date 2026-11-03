<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use DateTimeImmutable;
use Nowo\WikiBundle\Entity\WikiPage;

/**
 * Dispatched after a page was moved in the page tree (1.14, {@see WikiEvents::PAGE_MOVED}): the audit entry of a move
 * (who, from, to, when). Listeners log it or refresh caches; titles are read from the pages, never sent elsewhere by
 * the bundle.
 */
final readonly class WikiPageMovedEvent
{
    /**
     * @param WikiPage $page the moved page (its new parent and position already flushed)
     * @param WikiPage|null $fromParent previous parent (null: it was a root page)
     * @param WikiPage|null $toParent new parent (null: it is now a root page)
     * @param int $index 0-based index among its new siblings
     * @param object|null $actor user who moved it
     * @param DateTimeImmutable $movedAt when it was moved
     */
    public function __construct(
        public WikiPage $page,
        public ?WikiPage $fromParent,
        public ?WikiPage $toParent,
        public int $index,
        public ?object $actor,
        public DateTimeImmutable $movedAt,
    ) {
    }
}
