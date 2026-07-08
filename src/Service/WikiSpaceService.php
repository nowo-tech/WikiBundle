<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use InvalidArgumentException;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Util\WikiSlugger;

/**
 * Creates and resolves wiki spaces.
 */
final readonly class WikiSpaceService
{
    public function __construct(
        private WikiSpaceRepositoryInterface $spaceRepository,
        private WikiSlugger $slugger,
    ) {
    }

    public function create(
        string $name,
        WikiSpaceOwnerScope $ownerScopeType,
        string $ownerScopeId,
        ?string $slug = null,
    ): WikiSpace {
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

    public function findBySlug(WikiSpaceOwnerScope $scopeType, string $ownerScopeId, string $slug): ?WikiSpace
    {
        return $this->spaceRepository->findBySlug($scopeType, $ownerScopeId, $slug);
    }
}
