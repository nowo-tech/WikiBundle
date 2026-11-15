<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Entity\WikiSpace;

/**
 * 1.24 (spec 218 FR-019): the pages of a space with their current revision's metadata in one query, never the bodies,
 * so a list stays within a fixed number of queries whatever its size. The Doctrine page repository implements it.
 *
 * Persistence adapter of the bundle's own entities, not a host port (REQ-ARCH-001): it is entity-typed by design;
 * hosts reach wiki data through the services, the id operations and the events.
 */
interface WikiPageMetadataFinderInterface
{
    /**
     * @return list<WikiPageMetadata> by position, then id
     */
    public function pageMetadata(WikiSpace $space, WikiPageMetadataQuery $query): array;
}
