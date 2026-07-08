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
     * @return list<WikiSpace>
     */
    public function listSpacesForUser(UserInterface $user): array;

    public function canAccessSpace(UserInterface $user, WikiSpace $space): bool;
}
