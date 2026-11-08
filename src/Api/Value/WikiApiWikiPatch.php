<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Value;

/**
 * 1.24 (spec 218 FR-033): `PATCH /wikis/{wiki}`; a null field is left as it is.
 */
final readonly class WikiApiWikiPatch
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
    ) {
    }
}
