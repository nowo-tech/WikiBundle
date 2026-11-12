<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use InvalidArgumentException;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Util\WikiSlugger;
use Nowo\WikiBundle\ValueObject\WikiOwnerScopeId;

/**
 * Creates and resolves wiki spaces.
 */
final readonly class WikiSpaceService implements WikiSpaceServiceInterface
{
    /**
     * Wires the space repository and the slugger.
     *
     * @param WikiSpaceRepositoryInterface $spaceRepository space persistence
     * @param WikiSlugger $slugger slug generator and validator
     */
    public function __construct(
        private WikiSpaceRepositoryInterface $spaceRepository,
        private WikiSlugger $slugger,
    ) {
    }

    /**
     * Creates and persists a space, deriving the slug from the name when none is given.
     *
     * @param string $name space display name
     * @param WikiSpaceOwnerScope $ownerScopeType owner scope (team or user)
     * @param string $ownerScopeId identifier of the owning team or user
     * @param string|null $slug explicit slug, or null to slugify the name
     *
     * @throws InvalidArgumentException when the owner id is not usable ({@see WikiOwnerScopeId}: leading or trailing
     *                                  whitespace, control or format characters, over 64 characters), or when the slug
     *                                  is invalid or already used by this owner
     *
     * @return WikiSpace the persisted space
     */
    public function create(
        string $name,
        WikiSpaceOwnerScope $ownerScopeType,
        string $ownerScopeId,
        ?string $slug = null,
    ): WikiSpace {
        // 1.31.1 (house rule LQ): an owner id that a collation could confuse with another one is refused, never trimmed.
        if (!WikiOwnerScopeId::isUsable($ownerScopeId)) {
            throw new InvalidArgumentException('Invalid space owner id: no leading or trailing whitespace, control or format characters, at most 64 characters.');
        }

        $slug ??= $this->slugger->slugify($name);
        if (!$this->slugger->isValid($slug)) {
            throw new InvalidArgumentException('Invalid space slug.');
        }

        if ($this->spaceRepository->findBySlug($ownerScopeType, $ownerScopeId, $slug) instanceof WikiSpace) {
            throw new InvalidArgumentException('Space slug already exists for this owner.');
        }

        $space = new WikiSpace($slug, $name, $ownerScopeType, $ownerScopeId);
        $this->spaceRepository->save($space);

        return $space;
    }

    /**
     * Finds a space by owner scope and slug.
     *
     * @param WikiSpaceOwnerScope $scopeType owner scope (team or user)
     * @param string $ownerScopeId identifier of the owning team or user
     * @param string $slug space slug
     *
     * @return WikiSpace|null the space, or null when not found
     */
    public function findBySlug(WikiSpaceOwnerScope $scopeType, string $ownerScopeId, string $slug): ?WikiSpace
    {
        return $this->spaceRepository->findBySlug($scopeType, $ownerScopeId, $slug);
    }
}
