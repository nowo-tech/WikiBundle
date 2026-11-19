<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Search\SearchKit;

use Nowo\SearchKitBundle\Contract\FilterableSearchProviderInterface;
use Nowo\SearchKitBundle\Contract\IdentifierAwareSearchProviderInterface;
use Nowo\SearchKitBundle\Contract\PaginatedSearchProviderInterface;
use Nowo\SearchKitBundle\Contract\SearchReferenceResolverInterface;
use Nowo\SearchKitBundle\Event\SearchItemVisibilityEvent;
use Nowo\SearchKitBundle\Provider\Entity\EntitySearchDefinition;
use Nowo\SearchKitBundle\Provider\Entity\EntitySearchProvider;
use Nowo\SearchKitBundle\Search\Group\SensitiveGroupNotice;

use function class_exists;
use function interface_exists;

/**
 * 1.44, 1.46: whether SearchKitBundle >= 1.9 is installed — its provider base ({@see EntitySearchDefinition},
 * {@see EntitySearchProvider}, the item visibility event and the group settings of 1.9.0) and the ports
 * {@see WikiPageSearchKitProvider} implements. SearchKit is optional (`suggest`, PHP >= 8.3): without it, or with an
 * older one (1.8 included), `search_kit_provider` registers nothing.
 */
final class WikiSearchKitAvailability
{
    /** SearchKit's tag of entity search definitions (EntitySearchDefinition::TAG, kept here so loading needs no SearchKit class). */
    public const DEFINITION_TAG = 'nowo_search_kit.entity_definition';

    /**
     * @param (callable(string): bool)|null $interfaceExists the interface check (null: interface_exists(); tests pass their own)
     * @param (callable(string): bool)|null $classExists the class check (null: class_exists(); tests pass their own)
     */
    public static function isAvailable(?callable $interfaceExists = null, ?callable $classExists = null): bool
    {
        $interfaceExists ??= interface_exists(...);
        $classExists ??= class_exists(...);
        foreach ([PaginatedSearchProviderInterface::class, IdentifierAwareSearchProviderInterface::class, FilterableSearchProviderInterface::class, SearchReferenceResolverInterface::class] as $interface) {
            if (!$interfaceExists($interface)) {
                return false;
            }
        }
        // 1.9.0: the provider base; SensitiveGroupNotice is the post-audit (VF) constructor of EntitySearchProvider.
        foreach ([EntitySearchDefinition::class, EntitySearchProvider::class, SearchItemVisibilityEvent::class, SensitiveGroupNotice::class] as $class) {
            if (!$classExists($class)) {
                return false;
            }
        }

        return true;
    }
}
