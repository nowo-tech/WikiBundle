<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Nowo\WikiBundle\Entity\WikiPage;

/**
 * Dispatched when a page is archived.
 */
final readonly class WikiPageArchivedEvent
{
    public function __construct(
        public WikiPage $page,
    ) {
    }
}
