<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use InvalidArgumentException;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;

/**
 * The space operations a host calls, returning snapshots (1.34.0, REQ-ARCH-001 rule 1): the framework-free twin of
 * {@see WikiSpaceServiceInterface} (public alias). Owner ids and slugs are matched byte for byte.
 */
interface WikiSpaceOperationsInterface
{
    /** The space with exactly that id, or null. */
    public function findSpace(string $spaceId): ?WikiSpaceSnapshot;

    /**
     * @throws InvalidArgumentException when the owner id or slug is invalid, or the slug is taken by this owner
     */
    public function createSpace(string $name, WikiSpaceOwnerScope $ownerScopeType, string $ownerScopeId, ?string $slug = null): WikiSpaceSnapshot;

    public function findSpaceBySlug(WikiSpaceOwnerScope $scopeType, string $ownerScopeId, string $slug): ?WikiSpaceSnapshot;
}
