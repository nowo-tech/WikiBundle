<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\ValueObject;

use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Service\WikiSpaceOperationsInterface;

/**
 * 1.55 (Beacon, REQ-ARCH-001 rule 3): what {@see WikiSpaceOperationsInterface::createSpaceFrom()} creates — the slug,
 * name and owner of a new space and its settings (the icon), as plain values. Nothing is checked here: the operation
 * checks every value with the rules of the space settings (see the method), so a host builds it from its own input.
 */
final readonly class WikiNewSpace
{
    /**
     * @param string $slug the space slug, exactly (never derived from the name): unique per owner
     * @param string $name display name (trimmed when stored)
     * @param string $ownerScopeId owner id (team or user id), byte for byte
     * @param WikiSpaceOwnerScope $ownerScopeType owner kind
     * @param string|null $icon `<prefix>:<name>` icon of the space settings (`space_icon`); null for the default icon
     */
    public function __construct(
        public string $slug,
        public string $name,
        public string $ownerScopeId,
        public WikiSpaceOwnerScope $ownerScopeType = WikiSpaceOwnerScope::Team,
        public ?string $icon = null,
    ) {
    }
}
