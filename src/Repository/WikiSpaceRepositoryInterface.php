<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;

interface WikiSpaceRepositoryInterface
{
    public function save(WikiSpace $space): void;

    public function findById(string $id): ?WikiSpace;

    public function findBySlug(WikiSpaceOwnerScope $scopeType, string $ownerScopeId, string $slug): ?WikiSpace;

    public function findFirstBySlug(string $slug): ?WikiSpace;

    /**
     * @return list<WikiSpace>
     */
    public function findAccessible(string $ownerScopeType, array $ownerScopeIds): array;
}
