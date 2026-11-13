<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * Which spaces a user reaches, framework-free (1.32.0): the twin of the deprecated
 * {@see WikiSpaceAccessResolverInterface} (alias either interface to your service). Spaces are named by id; the
 * bundle loads them and keeps only ids equal byte for byte to a stored space.
 */
interface WikiSpaceReachInterface
{
    /**
     * @param WikiUserRef $user the user
     *
     * @return list<string> ids of the spaces the user may access
     */
    public function spaceIdsFor(WikiUserRef $user): array;

    /**
     * @param WikiUserRef $user the user
     * @param WikiSpaceSnapshot $space the space
     *
     * @return bool true when the user may access the space
     */
    public function canAccessSpace(WikiUserRef $user, WikiSpaceSnapshot $space): bool;
}
