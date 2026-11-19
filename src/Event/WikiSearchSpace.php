<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Nowo\WikiBundle\LinkPicker\WikiLinkTenantResolverInterface;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;

/**
 * 1.46: one space a SearchKit search of the wiki would read, as {@see WikiSearchSpacesEvent} shows it to the host: the
 * space (a framework-free snapshot, never the entity) and its tenant key (the same key the link picker and the
 * favourites / recents lists use, {@see WikiLinkTenantResolverInterface}; null: none).
 */
final readonly class WikiSearchSpace
{
    /**
     * @param WikiSpaceSnapshot $space the space
     * @param string|null $tenant its tenant key, or null
     */
    public function __construct(
        public WikiSpaceSnapshot $space,
        public ?string $tenant,
    ) {
    }
}
