<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Search\SearchKit;

use function class_exists;
use function interface_exists;

/**
 * 1.44, 1.46: whether SearchKitBundle >= 1.9 is installed — its provider base (EntitySearchDefinition,
 * EntitySearchProvider, the item visibility event and the group settings of 1.9.0) and the ports
 * {@see WikiPageSearchKitProvider} implements. SearchKit is optional (`suggest`, PHP >= 8.3): without it, or with an
 * older one (1.8 included), `search_kit_provider` registers nothing.
 */
final class WikiSearchKitAvailability
{
    /** SearchKit's tag of entity search definitions (EntitySearchDefinition::TAG, kept here so loading needs no SearchKit class). */
    public const DEFINITION_TAG = 'nowo_search_kit.entity_definition';

    /**
     * SearchKit's ports {@see WikiPageSearchKitProvider} implements (names, not `::class`: SearchKit is optional and the
     * PHP 8.2 analysis runs without it).
     */
    public const INTERFACES = [
        'Nowo\\SearchKitBundle\\Contract\\PaginatedSearchProviderInterface',
        'Nowo\\SearchKitBundle\\Contract\\IdentifierAwareSearchProviderInterface',
        'Nowo\\SearchKitBundle\\Contract\\FilterableSearchProviderInterface',
        'Nowo\\SearchKitBundle\\Contract\\SearchReferenceResolverInterface',
    ];

    /** SearchKit 1.9.0 provider base; SensitiveGroupNotice is the post-audit (VF) constructor of EntitySearchProvider. */
    public const CLASSES = [
        'Nowo\\SearchKitBundle\\Provider\\Entity\\EntitySearchDefinition',
        'Nowo\\SearchKitBundle\\Provider\\Entity\\EntitySearchProvider',
        'Nowo\\SearchKitBundle\\Event\\SearchItemVisibilityEvent',
        'Nowo\\SearchKitBundle\\Search\\Group\\SensitiveGroupNotice',
    ];

    /**
     * @param (callable(string): bool)|null $interfaceExists the interface check (null: interface_exists(); tests pass their own)
     * @param (callable(string): bool)|null $classExists the class check (null: class_exists(); tests pass their own)
     */
    public static function isAvailable(?callable $interfaceExists = null, ?callable $classExists = null): bool
    {
        $interfaceExists ??= interface_exists(...);
        $classExists ??= class_exists(...);
        foreach (self::INTERFACES as $interface) {
            if (!$interfaceExists($interface)) {
                return false;
            }
        }
        foreach (self::CLASSES as $class) {
            if (!$classExists($class)) {
                return false;
            }
        }

        return true;
    }
}
