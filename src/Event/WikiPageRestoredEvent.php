<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;

/**
 * Dispatched after an old revision was restored as a new revision (1.9).
 */
final readonly class WikiPageRestoredEvent
{
    /**
     * @param WikiPage $page the page
     * @param WikiPageRevision $restoredFrom the old revision whose title and body were copied
     * @param WikiPageRevision $revision the new revision
     * @param object|null $actor user who restored it
     */
    public function __construct(
        public WikiPage $page,
        public WikiPageRevision $restoredFrom,
        public WikiPageRevision $revision,
        public ?object $actor = null,
    ) {
    }
}
