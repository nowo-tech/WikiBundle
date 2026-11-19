<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Search\SearchKit;

use Nowo\SearchKitBundle\Contract\FilterableSearchProviderInterface;
use Nowo\SearchKitBundle\Contract\IdentifierAwareSearchProviderInterface;
use Nowo\SearchKitBundle\Contract\PaginatedSearchProviderInterface;
use Nowo\SearchKitBundle\Contract\SearchReferenceResolverInterface;
use Nowo\SearchKitBundle\Model\SearchGroup;
use Nowo\SearchKitBundle\Model\SearchItem;
use Nowo\SearchKitBundle\Model\SearchQuery;
use Nowo\SearchKitBundle\Model\SearchReference;
use Nowo\SearchKitBundle\Provider\Entity\EntitySearchProvider;

/**
 * 1.44 (Beacon KIT-85), 1.46 on SearchKit's provider base: the wiki's SearchKit group — the header search, the palette,
 * the results page and the recents. Registered only with `nowo_wiki.search_kit_provider` on and SearchKitBundle >= 1.9
 * installed and registered ({@see WikiSearchKitAvailability}); off by default.
 *
 * Every search runs SearchKit's own EntitySearchProvider flow over {@see WikiPageSearchDefinition} (the wiki's rules).
 * This class only adds what that flow does not declare, and nothing that widens it:
 *
 * - the typed filter `space:<slug>` / `-space:<slug>` ({@see FilterableSearchProviderInterface}; the definition applies
 *   it before the SQL, and any other filter key finds nothing);
 * - searches by user identifier ({@see IdentifierAwareSearchProviderInterface}: the definition decides for SearchKit's
 *   user identifier only, never for the session);
 * - the recents: a stored reference names a page by its space and page slugs, so the page id is looked up for the
 *   viewer first ({@see WikiPageSearchDefinition::referencedPageIds()}), then the row is read again through the whole
 *   flow (spaces, page rules, host events) and kept only with the same URL and kind.
 *
 * Stateless (FrankenPHP worker mode with `kernel.reset` off): nothing is kept between searches.
 */
final readonly class WikiPageSearchKitProvider implements PaginatedSearchProviderInterface, IdentifierAwareSearchProviderInterface, FilterableSearchProviderInterface, SearchReferenceResolverInterface
{
    /** Group key and row kind. */
    public const GROUP = WikiPageSearchDefinition::GROUP;

    /** Typed filter `space:<slug>` (and `-space:<slug>`). */
    public const FILTER_SPACE = WikiPageSearchDefinition::FILTER_SPACE;

    /**
     * @param EntitySearchProvider $provider SearchKit's provider of {@see WikiPageSearchDefinition} (built by SearchKit)
     * @param WikiPageSearchDefinition $definition the wiki's rules (the recents' slug lookup)
     */
    public function __construct(
        private EntitySearchProvider $provider,
        private WikiPageSearchDefinition $definition,
    ) {
    }

    public function groupKey(): string
    {
        return $this->provider->groupKey();
    }

    public function supportedFilters(): array
    {
        return [self::FILTER_SPACE];
    }

    public function search(SearchQuery $query): ?SearchGroup
    {
        return $this->provider->search($query);
    }

    public function searchPage(SearchQuery $query, int $page, int $perPage): ?SearchGroup
    {
        return $this->provider->searchPage($query, $page, $perPage);
    }

    public function resolve(SearchReference $reference, string $userIdentifier, ?string $locale): ?SearchItem
    {
        foreach ($this->definition->referencedPageIds($reference, $userIdentifier, $locale) as $id) {
            // The id found for this viewer wins over any parameter of that name (never part of a wiki URL).
            $item = $this->provider->resolve(
                new SearchReference($reference->url, $reference->kind, $reference->route, [WikiPageSearchDefinition::REFERENCE_ID => $id] + $reference->routeParameters),
                $userIdentifier,
                $locale,
            );
            if ($item instanceof SearchItem) {
                return $item;
            }
        }

        return null;
    }
}
