<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Link;

use Nowo\WikiBundle\Port\Internal\WikiObjectLinkResolverInterface;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * Describes linked host objects, framework-free (1.36.0): since 2.0 the only host port; it replaced the removed 1.x
 * `WikiObjectLinkResolverInterface`; autoconfigured with the same tag (`nowo_wiki.object_link_resolver`).
 * Objects keyed by an id that was not asked for are dropped.
 */
interface WikiObjectLinkSourceInterface
{
    /** Tells whether this source handles the host object type. */
    public function supports(string $objectType): bool;

    /**
     * @param WikiUserRef $user viewer
     * @param string $objectType host object type
     * @param list<string> $objectIds host object ids
     *
     * @return array<string, WikiLinkedObject> visible objects keyed by id
     */
    public function resolveVisible(WikiUserRef $user, string $objectType, array $objectIds): array;
}
