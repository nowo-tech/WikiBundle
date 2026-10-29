<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;

/**
 * Dispatched when a page revision is saved.
 */
final readonly class WikiPageSavedEvent
{
    /**
     * Creates the event for a saved page revision.
     *
     * @param WikiPage $page page that was saved
     * @param WikiPageRevision $revision revision that was created
     */
    public function __construct(
        public WikiPage $page,
        public WikiPageRevision $revision,
    ) {
    }
}
