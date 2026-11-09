<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use InvalidArgumentException;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;

/**
 * Port of the space operations a host calls (1.27; public API, SemVer). Aliased to {@see WikiSpaceService}.
 */
interface WikiSpaceServiceInterface
{
    /**
     * Creates and persists a space (slug from the name when none is given).
     *
     * @throws InvalidArgumentException when the slug is invalid or already used by this owner
     */
    public function create(string $name, WikiSpaceOwnerScope $ownerScopeType, string $ownerScopeId, ?string $slug = null): WikiSpace;

    /**
     * Finds a space by owner scope and slug.
     */
    public function findBySlug(WikiSpaceOwnerScope $scopeType, string $ownerScopeId, string $slug): ?WikiSpace;
}
