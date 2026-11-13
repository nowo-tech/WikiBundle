<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\ValueObject;

use DateTimeImmutable;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;

/**
 * A wiki space as host ports and events see it (1.33.0, REQ-ARCH-001 rules 1 and 3): copied from the bundle's
 * `WikiSpace` row when the port is called, so a host never holds the entity. Build one with
 * `Port\WikiSnapshots::space()`.
 */
final readonly class WikiSpaceSnapshot
{
    /**
     * @param string $id space id (UUID)
     * @param string $slug space slug, unique per owner
     * @param string $name display name
     * @param WikiSpaceOwnerScope $ownerScopeType owner kind (team or user)
     * @param string $ownerScopeId owner id, exactly as stored
     * @param DateTimeImmutable $createdAt creation time
     * @param DateTimeImmutable $updatedAt last change of the space row
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public WikiSpaceOwnerScope $ownerScopeType,
        public string $ownerScopeId,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
