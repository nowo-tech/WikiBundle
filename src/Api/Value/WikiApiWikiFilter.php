<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Value;

/**
 * 1.24 (spec 218): the filters of `GET /wikis`, validated by the bundle before the directory sees them.
 */
final readonly class WikiApiWikiFilter
{
    /**
     * @param bool|null $archived null: active and archived ones
     */
    public function __construct(
        public ?string $ownerType = null,
        public ?string $ownerId = null,
        public ?bool $archived = null,
    ) {
    }
}
