<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Finds the space a route slug names (1.9). A host replaces the alias to resolve tenant slugs and to create a space
 * on its first visit; access is decided afterwards by {@see WikiSpaceAccessCheckerInterface}.
 */
interface WikiSpaceLocatorInterface
{
    /**
     * Returns the space of the slug, or null when there is none (the route answers 404).
     *
     * @param string $slug space slug from the route
     * @param UserInterface $user signed-in user
     *
     * @return WikiSpace|null the space, or null
     */
    public function locate(string $slug, UserInterface $user): ?WikiSpace;
}
