<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;

/**
 * Persistence port for wiki spaces.
 */
interface WikiSpaceRepositoryInterface
{
    /**
     * Persists the space and flushes it immediately.
     *
     * @param WikiSpace $space space to save
     */
    public function save(WikiSpace $space): void;

    /**
     * Finds a space by its identifier.
     *
     * @param string $id space UUID
     *
     * @return WikiSpace|null the space, or null when none exists
     */
    public function findById(string $id): ?WikiSpace;

    /**
     * Finds a space by slug within an owner scope.
     *
     * @param WikiSpaceOwnerScope $scopeType kind of owner (team or user)
     * @param string $ownerScopeId identifier of the owning team or user
     * @param string $slug space slug
     *
     * @return WikiSpace|null the space, or null when none matches
     */
    public function findBySlug(WikiSpaceOwnerScope $scopeType, string $ownerScopeId, string $slug): ?WikiSpace;

    /**
     * Finds the first space with the given slug, whatever its owner scope.
     *
     * @param string $slug space slug
     *
     * @return WikiSpace|null the first matching space, or null when none matches
     */
    public function findFirstBySlug(string $slug): ?WikiSpace;

    /**
     * Returns the spaces of the given owner scope type owned by any of the given identifiers, ordered by name.
     *
     * @param string $ownerScopeType owner scope type value (e.g. `team` or `user`)
     * @param list<string> $ownerScopeIds identifiers of the owning teams or users
     *
     * @return list<WikiSpace>
     */
    public function findAccessible(string $ownerScopeType, array $ownerScopeIds): array;
}
