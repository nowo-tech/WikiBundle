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
    public function __construct(
        public WikiPage $page,
        public WikiPageRevision $revision,
    ) {
    }
}
