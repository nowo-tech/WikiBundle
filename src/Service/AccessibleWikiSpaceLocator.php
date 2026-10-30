<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Nowo\WikiBundle\Entity\WikiSpace;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Default locator: the 1.x lookup among the spaces the user may access.
 */
final readonly class AccessibleWikiSpaceLocator implements WikiSpaceLocatorInterface
{
    /**
     * @param WikiSpaceAccessResolverInterface $spaceAccessResolver spaces the user belongs to
     */
    public function __construct(
        private WikiSpaceAccessResolverInterface $spaceAccessResolver,
    ) {
    }

    /**
     * Returns the accessible space with the slug, or null.
     *
     * @param string $slug space slug
     * @param UserInterface $user signed-in user
     *
     * @return WikiSpace|null the space, or null
     */
    public function locate(string $slug, UserInterface $user): ?WikiSpace
    {
        foreach ($this->spaceAccessResolver->listSpacesForUser($user) as $space) {
            if ($space->getSlug() === $slug) {
                return $space;
            }
        }

        return null;
    }
}
