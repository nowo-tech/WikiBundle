<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Nowo\WikiBundle\Entity\WikiSpace;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Resolves wiki spaces visible to a user.
 */
interface WikiSpaceAccessResolverInterface
{
    /**
     * Lists the spaces visible to the user.
     *
     * @param UserInterface $user current user
     *
     * @return list<WikiSpace>
     */
    public function listSpacesForUser(UserInterface $user): array;

    /**
     * Tells whether the user may access the given space.
     *
     * @param UserInterface $user current user
     * @param WikiSpace $space space to check
     *
     * @return bool true when the space is visible to the user
     */
    public function canAccessSpace(UserInterface $user, WikiSpace $space): bool;
}
