<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Query;

use InvalidArgumentException;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\ValueObject\WikiOwnerScopeId;

/**
 * 1.51: which spaces {@see WikiSpaceQueryInterface::findSpaces()} and {@see WikiSpaceQueryInterface::countSpaces()} read.
 * Every filter given narrows the result (AND); an empty list matches nothing. Owner ids follow the owner-id rule of
 * {@see WikiOwnerScopeId} (refused here when unusable); a filter takes at most 1,000 ids and are matched byte for byte.
 */
final readonly class WikiSpaceCriteria
{
    /** @var list<string>|null */
    public ?array $ownerScopeIds;

    /** @var list<string>|null */
    public ?array $spaceIds;

    /**
     * @param WikiSpaceOwnerScope|null $ownerScopeType owner kind; null for both
     * @param list<string>|null $ownerScopeIds owners (a tenant is the owner ids the host maps it to); null for every owner
     * @param list<string>|null $spaceIds only these spaces; null for no restriction
     * @param WikiArchiveFilter $archived archived spaces in or out
     * @param WikiSpaceSort $sort order of {@see WikiSpaceQueryInterface::findSpaces()}
     *
     * @throws InvalidArgumentException when an owner id is unusable or a filter has more than 1,000 ids
     */
    public function __construct(
        public ?WikiSpaceOwnerScope $ownerScopeType = null,
        ?array $ownerScopeIds = null,
        ?array $spaceIds = null,
        public WikiArchiveFilter $archived = WikiArchiveFilter::Any,
        public WikiSpaceSort $sort = WikiSpaceSort::NameAsc,
    ) {
        $this->ownerScopeIds = $ownerScopeIds === null ? null : WikiQueryIds::ownerFilter($ownerScopeIds);
        $this->spaceIds      = $spaceIds === null ? null : WikiQueryIds::filter($spaceIds);
    }

    /**
     * The spaces of these owners of one kind.
     *
     * @param list<string> $ownerScopeIds the owners
     *
     * @throws InvalidArgumentException when an owner id is unusable
     */
    public static function forOwners(WikiSpaceOwnerScope $ownerScopeType, array $ownerScopeIds, WikiArchiveFilter $archived = WikiArchiveFilter::Any, WikiSpaceSort $sort = WikiSpaceSort::NameAsc): self
    {
        return new self($ownerScopeType, $ownerScopeIds, null, $archived, $sort);
    }

    /**
     * Whether a filter is an empty list (nothing can match: no query is run).
     */
    public function matchesNothing(): bool
    {
        return $this->ownerScopeIds === [] || $this->spaceIds === [];
    }
}
