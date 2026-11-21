<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Query;

use InvalidArgumentException;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;

/**
 * 1.51: which pages {@see WikiPageQueryInterface::findPages()} and {@see WikiPageQueryInterface::countPages()} read. At
 * least one of the space, owner and page filters is required (a read port never scans every page by accident); every
 * filter given narrows the result (AND) and an empty list matches nothing; a filter takes at most 1,000 ids. Owner ids follow the owner-id rule of
 * {@see \Nowo\WikiBundle\ValueObject\WikiOwnerScopeId} and are matched byte for byte.
 */
final readonly class WikiPageCriteria
{
    /** @var list<string>|null */
    public ?array $spaceIds;

    /** @var list<string>|null */
    public ?array $ownerScopeIds;

    /** @var list<string>|null */
    public ?array $pageIds;

    /**
     * @param list<string>|null $spaceIds pages of these spaces
     * @param WikiSpaceOwnerScope|null $ownerScopeType with owners: their kind; null for both
     * @param list<string>|null $ownerScopeIds pages of these owners' spaces
     * @param list<string>|null $pageIds only these pages
     * @param WikiArchiveFilter $archived archived (and trashed) pages in or out
     * @param WikiPageSort $sort order of {@see WikiPageQueryInterface::findPages()}
     *
     * @throws InvalidArgumentException when no scope is given, an owner id is unusable or a filter has more than 1,000 ids
     */
    public function __construct(
        ?array $spaceIds = null,
        public ?WikiSpaceOwnerScope $ownerScopeType = null,
        ?array $ownerScopeIds = null,
        ?array $pageIds = null,
        public WikiArchiveFilter $archived = WikiArchiveFilter::Any,
        public WikiPageSort $sort = WikiPageSort::Position,
    ) {
        if ($spaceIds === null && $ownerScopeIds === null && $pageIds === null) {
            throw new InvalidArgumentException('A page criteria needs space ids, owner ids or page ids.');
        }
        $this->spaceIds      = $spaceIds === null ? null : WikiQueryIds::filter($spaceIds);
        $this->ownerScopeIds = $ownerScopeIds === null ? null : WikiQueryIds::ownerFilter($ownerScopeIds);
        $this->pageIds       = $pageIds === null ? null : WikiQueryIds::filter($pageIds);
    }

    /**
     * The pages of these spaces.
     *
     * @param list<string> $spaceIds the spaces
     */
    public static function inSpaces(array $spaceIds, WikiArchiveFilter $archived = WikiArchiveFilter::Any, WikiPageSort $sort = WikiPageSort::Position): self
    {
        return new self($spaceIds, null, null, null, $archived, $sort);
    }

    /**
     * The pages of the spaces of these owners of one kind.
     *
     * @param list<string> $ownerScopeIds the owners
     *
     * @throws InvalidArgumentException when an owner id is unusable
     */
    public static function ofOwners(WikiSpaceOwnerScope $ownerScopeType, array $ownerScopeIds, WikiArchiveFilter $archived = WikiArchiveFilter::Any, WikiPageSort $sort = WikiPageSort::Position): self
    {
        return new self(null, $ownerScopeType, $ownerScopeIds, null, $archived, $sort);
    }

    /**
     * Whether a filter is an empty list (nothing can match: no query is run).
     */
    public function matchesNothing(): bool
    {
        return $this->spaceIds === [] || $this->ownerScopeIds === [] || $this->pageIds === [];
    }
}
