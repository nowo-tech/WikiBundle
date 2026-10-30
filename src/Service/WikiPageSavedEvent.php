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
     * @param WikiPageRevision|null $previousRevision revision that was current before (null for a new page; 1.9)
     * @param object|null $actor user who saved it (1.9)
     * @param bool $conversion true when the revision comes from the bulk HTML → Markdown conversion
     *                         (`wiki:convert-markdown`, 1.11): nobody edited the page, so hosts should not notify
     */
    public function __construct(
        public WikiPage $page,
        public WikiPageRevision $revision,
        public ?WikiPageRevision $previousRevision = null,
        public ?object $actor = null,
        public bool $conversion = false,
    ) {
    }
}
