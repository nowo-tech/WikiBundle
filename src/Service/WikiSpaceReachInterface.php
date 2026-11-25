<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Nowo\WikiBundle\Port\Internal\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * Which spaces a user reaches, framework-free (1.36.0): since 2.0 the only host port; it replaced the removed 1.x
 * `WikiSpaceAccessResolverInterface` (alias this interface to your service). Spaces are named by id; the
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
