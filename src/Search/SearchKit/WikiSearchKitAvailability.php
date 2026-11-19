<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Search\SearchKit;

use Composer\InstalledVersions;
use OutOfBoundsException;

use function class_exists;
use function interface_exists;
use function is_string;
use function preg_match;
use function version_compare;

/**
 * 1.44, 1.46: whether SearchKitBundle >= 1.10.1 is installed — its provider base (EntitySearchDefinition,
 * EntitySearchProvider, the item visibility event and the group settings of 1.9.0), the ports
 * {@see WikiPageSearchKitProvider} implements, and (audit VN-2, a security requirement) version 1.10.1 or later, the first
 * that never marks a candidate window full of rows as an exact total (a count side channel of rows hidden in PHP). The
 * version is Composer's installed version; an unknown one counts as too old. SearchKit is optional (`suggest`, PHP >= 8.3):
 * without it, or with an older one (1.8 to 1.10.0 included), `search_kit_provider` registers nothing.
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

    /** Composer package of SearchKitBundle. */
    public const PACKAGE = 'nowo-tech/search-kit-bundle';

    /** Lowest SearchKit version the group runs on (audit VN-2). */
    public const MIN_VERSION = '1.10.1';

    /**
     * @param (callable(string): bool)|null $interfaceExists the interface check (null: interface_exists(); tests pass their own)
     * @param (callable(string): bool)|null $classExists the class check (null: class_exists(); tests pass their own)
     * @param (callable(): ?string)|null $version the installed version (null: Composer's; tests pass their own)
     */
    public static function isAvailable(?callable $interfaceExists = null, ?callable $classExists = null, ?callable $version = null): bool
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

        return self::isRecentEnough(($version ?? self::installedVersion(...))());
    }

    /**
     * Whether a Composer version (normalized `1.10.1.0`, or `1.10.1`) is at least {@see self::MIN_VERSION}; a branch
     * (`dev-main`, `9999999-dev`), null and anything unparsable count as too old (fail closed).
     */
    public static function isRecentEnough(?string $version): bool
    {
        return is_string($version)
            && preg_match('/^\d+\.\d+\.\d+/', $version) === 1
            && version_compare($version, self::MIN_VERSION, '>=');
    }

    /** Composer's installed version of a package (default: SearchKit), or null when Composer does not know it. */
    public static function installedVersion(string $package = self::PACKAGE): ?string
    {
        try {
            return InstalledVersions::getVersion($package);
        } catch (OutOfBoundsException) {
            // Not installed through Composer: unknown, so too old.
            return null;
        }
    }
}
