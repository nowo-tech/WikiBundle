<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Search\SearchKit;

use Nowo\SearchKitBundle\Contract\FilterableSearchProviderInterface;
use Nowo\SearchKitBundle\Contract\IdentifierAwareSearchProviderInterface;
use Nowo\SearchKitBundle\Contract\PaginatedSearchProviderInterface;
use Nowo\SearchKitBundle\Contract\SearchReferenceResolverInterface;
use Nowo\SearchKitBundle\Model\SearchGroup;

use function interface_exists;
use function property_exists;

/**
 * 1.44: whether SearchKitBundle >= 1.8 is installed — the ports {@see WikiPageSearchKitProvider} implements (paging,
 * recents and typed filters are 1.8.0) and the exact-total flag of its groups. SearchKit is optional (`suggest`):
 * without it, or with an older one, `search_kit_provider` registers nothing.
 */
final class WikiSearchKitAvailability
{
    /**
     * @param (callable(string): bool)|null $interfaceExists the check (null: interface_exists(); tests pass their own)
     */
    public static function isAvailable(?callable $interfaceExists = null): bool
    {
        $interfaceExists ??= interface_exists(...);
        foreach ([PaginatedSearchProviderInterface::class, IdentifierAwareSearchProviderInterface::class, FilterableSearchProviderInterface::class, SearchReferenceResolverInterface::class] as $interface) {
            if (!$interfaceExists($interface)) {
                return false;
            }
        }

        // 1.8.0: SearchGroup gained $exactTotal; without it a 1.7 engine would show totals it cannot vouch for.
        return property_exists(SearchGroup::class, 'exactTotal');
    }
}
