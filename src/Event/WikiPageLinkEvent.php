<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Nowo\WikiBundle\Entity\WikiPage;

/**
 * Dispatched after a page was linked to, or unlinked from, a host object (1.9).
 */
final readonly class WikiPageLinkEvent
{
    /**
     * @param WikiPage $page the page
     * @param string $objectType host object type (e.g. "issue")
     * @param string $objectId host object id
     * @param object|null $actor user who acted
     */
    public function __construct(
        public WikiPage $page,
        public string $objectType,
        public string $objectId,
        public ?object $actor = null,
    ) {
    }
}
