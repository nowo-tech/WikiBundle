<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Port\WikiSnapshots;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;

/**
 * {@see WikiSpaceOperationsInterface} over {@see WikiSpaceServiceInterface} (1.32.0).
 */
final readonly class WikiSpaceOperations implements WikiSpaceOperationsInterface
{
    public function __construct(
        private WikiSpaceServiceInterface $spaceService,
        private WikiSpaceRepositoryInterface $spaces,
    ) {
    }

    public function findSpace(string $spaceId): ?WikiSpaceSnapshot
    {
        $space = $this->spaces->findById($spaceId);

        return $space instanceof WikiSpace && $space->getId() === $spaceId ? WikiSnapshots::space($space) : null;
    }

    public function createSpace(string $name, WikiSpaceOwnerScope $ownerScopeType, string $ownerScopeId, ?string $slug = null): WikiSpaceSnapshot
    {
        return WikiSnapshots::space($this->spaceService->create($name, $ownerScopeType, $ownerScopeId, $slug));
    }

    public function findSpaceBySlug(WikiSpaceOwnerScope $scopeType, string $ownerScopeId, string $slug): ?WikiSpaceSnapshot
    {
        $space = $this->spaceService->findBySlug($scopeType, $ownerScopeId, $slug);

        // The repository already re-checks owner, scope and slug (1.31.1); checked again here, byte for byte.
        return $space instanceof WikiSpace && $space->getOwnerScopeType() === $scopeType && $space->getOwnerScopeId() === $ownerScopeId && $space->getSlug() === $slug
            ? WikiSnapshots::space($space)
            : null;
    }
}
