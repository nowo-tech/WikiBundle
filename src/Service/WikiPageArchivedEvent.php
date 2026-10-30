<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Nowo\WikiBundle\Entity\WikiPage;

/**
 * Dispatched when a page is archived.
 */
final readonly class WikiPageArchivedEvent
{
    /**
     * Creates the event for an archived page.
     *
     * @param WikiPage $page page that was archived
     * @param object|null $actor user who archived it (1.9)
     */
    public function __construct(
        public WikiPage $page,
        public ?object $actor = null,
    ) {
    }
}
