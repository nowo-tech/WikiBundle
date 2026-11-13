<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Link;

use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * Describes linked host objects, framework-free (1.32.0): the twin of the deprecated
 * {@see WikiObjectLinkResolverInterface}; autoconfigured with the same tag (`nowo_wiki.object_link_resolver`).
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
