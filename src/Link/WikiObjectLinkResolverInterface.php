<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Link;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Describes and authorizes the host objects of one or more types that pages may link to (1.9).
 *
 * Tag the service {@code nowo_wiki.object_link_resolver} (autoconfigured). {@see self::resolveVisible()} is asked once
 * per type with every id at hand, so a host answers in one query; objects the viewer may not open are left out of
 * the result and never shown.
 */
interface WikiObjectLinkResolverInterface
{
    /** Tells whether this resolver handles the host object type. */
    public function supports(string $objectType): bool;

    /**
     * Describes the objects among the ids the user may open, keyed by id; the others are left out.
     *
     * @param UserInterface $user viewer
     * @param string $objectType host object type
     * @param list<string> $objectIds host object ids
     *
     * @return array<string, WikiLinkedObject> visible objects keyed by id
     */
    public function resolveVisible(UserInterface $user, string $objectType, array $objectIds): array;
}
