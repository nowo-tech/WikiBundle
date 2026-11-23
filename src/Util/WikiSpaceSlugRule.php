<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Util;

use Nowo\WikiBundle\Routing\WikiTenantPaths;

use function preg_match;
use function strlen;

/**
 * 1.55: what a space slug may be when a host gives it (create by id, rename): at most 120 characters (the column), and
 * either the bundle's slug rule (lowercase letters and digits in segments joined by single dashes, {@see WikiSlugger})
 * or — still only lowercase letters, digits and dashes, never at either end — a slug the `slug_requirement` of a
 * configured `tenant_paths` entry matches whole: one the host's own wiki URLs serve, such as `<owner uuid>--<name>`.
 * Without tenant paths only the bundle's rule applies, as before 1.55. Stateless.
 */
final readonly class WikiSpaceSlugRule
{
    /** Longest space slug (the column). */
    public const MAX_LENGTH = 120;

    /**
     * @param WikiSlugger $slugger the bundle's slug rule
     * @param WikiTenantPaths|null $tenantPaths the configured tenant paths (null: none)
     */
    public function __construct(
        private WikiSlugger $slugger = new WikiSlugger(),
        private ?WikiTenantPaths $tenantPaths = null,
    ) {
    }

    /** Whether the slug can name a space. */
    public function isValid(string $slug): bool
    {
        if (strlen($slug) > self::MAX_LENGTH) {
            return false;
        }

        if ($this->slugger->isValid($slug)) {
            return true;
        }

        return preg_match('/\A[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\z/', $slug) === 1 && $this->tenantPaths?->matchesSlug($slug) === true;
    }
}
