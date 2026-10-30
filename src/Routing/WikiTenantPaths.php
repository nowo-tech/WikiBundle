<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Routing;

use function preg_match;
use function str_contains;
use function strlen;
use function strpos;
use function substr;

/**
 * The {@code nowo_wiki.tenant_paths} configuration (1.9): extra copies of every space-scoped route under host paths
 * such as {@code /projects/{spaceSlug}/wiki}, each with a name prefix and a slug requirement.
 *
 * A space slug is matched against the requirements in order; the first tenant whose requirement matches the whole
 * slug owns its URLs ({@see WikiTenantUrlGenerator}).
 */
final readonly class WikiTenantPaths
{
    /** The space parameter every space-scoped route carries. */
    public const SPACE_PARAMETER = '{spaceSlug}';

    /**
     * @param list<array{path: string, name_prefix: string, slug_requirement: string}> $tenants configured tenant paths
     * @param array<string, array{path: string, name: string}> $routes configured bundle routes
     * @param bool $tenantOnly whether the tenant-less copies of the space-scoped routes are left out
     */
    public function __construct(
        private array $tenants,
        private array $routes,
        private bool $tenantOnly = false,
    ) {
    }

    /**
     * @return list<array{path: string, name_prefix: string, slug_requirement: string}>
     */
    public function tenants(): array
    {
        return $this->tenants;
    }

    /** Whether the tenant-less space-scoped routes are disabled. */
    public function isTenantOnly(): bool
    {
        return $this->tenantOnly && $this->tenants !== [];
    }

    /** Tells whether a configured route path is space-scoped (carries {@code {spaceSlug}}). */
    public static function isSpaceScoped(string $path): bool
    {
        return str_contains($path, self::SPACE_PARAMETER);
    }

    /**
     * The tenant copy of a configured route path: the tenant path followed by what comes after {@code {spaceSlug}}.
     *
     * @param string $tenantPath tenant path (contains {@code {spaceSlug}})
     * @param string $routePath configured bundle route path (contains {@code {spaceSlug}})
     */
    public static function tenantPath(string $tenantPath, string $routePath): string
    {
        $offset = (int) strpos($routePath, self::SPACE_PARAMETER);

        return $tenantPath . substr($routePath, $offset + strlen(self::SPACE_PARAMETER));
    }

    /**
     * The route name to generate for a bundle route and a space slug: the first matching tenant's copy, or the
     * name unchanged when no tenant matches or the route is not space-scoped.
     */
    public function routeFor(string $name, string $spaceSlug): string
    {
        $path = null;
        foreach ($this->routes as $route) {
            if ($route['name'] === $name) {
                $path = $route['path'];
                break;
            }
        }

        if ($path === null || !self::isSpaceScoped($path)) {
            return $name;
        }

        foreach ($this->tenants as $tenant) {
            if (preg_match('#^(?:' . $tenant['slug_requirement'] . ')$#', $spaceSlug) === 1) {
                return $tenant['name_prefix'] . $name;
            }
        }

        return $name;
    }
}
