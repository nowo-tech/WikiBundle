<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Value;

/**
 * 1.24 (spec 218 FR-033): `POST /wikis`, validated by the bundle; what an owner type and id mean is the host's.
 */
final readonly class WikiApiNewWiki
{
    public function __construct(
        public string $ownerType,
        public string $ownerId,
        public string $name,
        public ?string $description = null,
    ) {
    }
}
