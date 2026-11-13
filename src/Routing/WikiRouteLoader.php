<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Routing;

use Nowo\WikiBundle\Api\Security\WikiApiRequestListener;
use Nowo\WikiBundle\Api\WikiApiRoutes;
use Nowo\WikiBundle\Controller\WikiAttachmentController;
use Nowo\WikiBundle\Controller\WikiCollabController;
use Nowo\WikiBundle\Controller\WikiCommentController;
use Nowo\WikiBundle\Controller\WikiHistoryController;
use Nowo\WikiBundle\Controller\WikiManageController;
use Nowo\WikiBundle\Controller\WikiTrashController;
use Nowo\WikiBundle\Controller\WikiTreeController;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Attribute\Route as RouteAttribute;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route loader for the {@code nowo_wiki} resource type (REQ-SF-004).
 *
 * The routes are declared with #[Route] on {@see WikiManageController} and {@see WikiAttachmentController} (methods,
 * requirements, default path / name)
 * and carry their configuration key in {@see self::ROUTE_KEY_OPTION}. The loader keeps the public configuration
 * contract: path and name come from `nowo_wiki.routes.<key>` and `route_prefix` is prepended. Do not import the
 * controller with `type: attribute`: that would bypass the configured paths, names and prefix.
 */
final class WikiRouteLoader extends Loader
{
    private bool $loaded = false;

    /** Route option carrying the `routes.<key>` configuration key of an action. */
    public const ROUTE_KEY_OPTION = 'nowo_wiki_route';

    /** Space slug pattern. */
    public const SPACE_SLUG = '[a-z0-9]+(?:-[a-z0-9]+)*';

    /** Slug pattern excluding reserved segment "new" (create-page route). */
    public const PAGE_SLUG = '(?!new$)[a-z0-9]+(?:-[a-z0-9]+)*';

    /**
     * Stores the configured routes and the prefix prepended to every path.
     *
     * @param array<string, array{path: string, name: string}> $routes route name/path per action key
     * @param string $routePrefix path prefix for all wiki routes
     * @param WikiTenantPaths|null $tenantPaths tenant copies of the space-scoped routes (1.9)
     * @param bool $enabled web_ui.enabled: false registers no route (1.10)
     * @param string|null $apiPrefix 1.24: `api.prefix` with `api.enabled`; null adds no API route
     */
    public function __construct(
        private readonly array $routes,
        private readonly string $routePrefix,
        private readonly ?WikiTenantPaths $tenantPaths = null,
        private readonly bool $enabled = true,
        private readonly ?string $apiPrefix = null,
    ) {
    }

    /**
     * Builds the configured wiki routes (prefixed with the route prefix) mapped to {@see WikiManageController} actions.
     *
     * Route keys missing from the configuration are skipped.
     *
     * @param mixed $resource resource being loaded (unused)
     * @param string|null $type resource type
     *
     * @throws RuntimeException when the routes have already been loaded
     *
     * @return RouteCollection collection of wiki routes
     */
    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new RuntimeException('Wiki routes already loaded.');
        }

        // @igor-ignore - Route loader builds routes at compile/cache warm; not per-request mutation.
        $this->loaded = true;
        $collection   = new RouteCollection();
        $this->addApiRoutes($collection);
        if (!$this->enabled) {
            return $collection;
        }

        foreach ([WikiManageController::class, WikiAttachmentController::class, WikiCollabController::class, WikiTreeController::class, WikiTrashController::class, WikiHistoryController::class, WikiCommentController::class] as $controller) {
            foreach ((new ReflectionClass($controller))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(RouteAttribute::class) as $attribute) {
                    $declared = $attribute->newInstance();
                    $key      = (string) ($declared->options[self::ROUTE_KEY_OPTION] ?? '');
                    if (!isset($this->routes[$key])) {
                        continue;
                    }

                    $this->addRoutes($collection, $this->routes[$key], $controller . '::' . $method->getName(), $declared->methods, $declared->requirements, $declared->defaults, $key);
                }
            }
        }

        return $collection;
    }

    /**
     * 1.24 (spec 218): the REST API, stateless, under its own prefix (not `route_prefix`, not the tenant paths), whatever
     * `web_ui.enabled` says.
     */
    private function addApiRoutes(RouteCollection $collection): void
    {
        if ($this->apiPrefix === null) {
            return;
        }
        foreach (WikiApiRoutes::ROUTES as $name => [$path, $controller, $method, $httpMethod]) {
            $collection->add(WikiApiRoutes::NAME_PREFIX . $name, new Route(
                $this->apiPrefix . $path,
                ['_controller' => $controller . '::' . $method, WikiApiRequestListener::ROUTE_FLAG => true, '_stateless' => true],
                WikiApiRoutes::REQUIREMENTS,
                [],
                '',
                [],
                [$httpMethod],
            ));
        }
    }

    /**
     * Tells whether this loader handles the given resource type.
     *
     * @param mixed $resource resource being loaded (unused)
     * @param string|null $type resource type
     *
     * @return bool true for the {@code nowo_wiki} type
     */
    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === 'nowo_wiki';
    }

    /**
     * Adds the tenant-less route (unless disabled) and one copy per tenant path for space-scoped routes.
     *
     * @param array{path: string, name: string} $route configured path and name
     * @param array<string> $methods
     * @param array<string, mixed> $requirements
     * @param array<string, mixed> $defaults placeholder defaults of the attribute (1.16: the optional `{path}` of `page_comments`)
     * @param string $key 1.33: the `routes.<key>` of the action, kept as the {@see self::ROUTE_KEY_OPTION} option of
     *                    every copy (bundle and tenant routes), so that the action is known whatever the route name
     */
    private function addRoutes(RouteCollection $collection, array $route, string $controller, array $methods, array $requirements, array $defaults, string $key): void
    {
        $defaults    = ['_controller' => $controller] + $defaults;
        $options     = [self::ROUTE_KEY_OPTION => $key];
        $spaceScoped = WikiTenantPaths::isSpaceScoped($route['path']);

        foreach ($this->tenantPaths?->tenants() ?? [] as $tenant) {
            if (!$spaceScoped) {
                continue;
            }

            $collection->add(
                $tenant['name_prefix'] . $route['name'],
                new Route(
                    WikiTenantPaths::tenantPath($tenant['path'], $route['path']),
                    $defaults,
                    ['spaceSlug' => $tenant['slug_requirement']] + $requirements,
                    $options,
                    '',
                    [],
                    $methods,
                ),
            );
        }

        if ($spaceScoped && $this->tenantPaths?->isTenantOnly() === true) {
            return;
        }

        $collection->add($route['name'], $this->createRoute($route['path'], $defaults, $methods, $requirements, $options));
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<string> $methods
     * @param array<string, mixed> $requirements
     * @param array<string, string> $options
     */
    private function createRoute(string $path, array $defaults, array $methods, array $requirements, array $options): Route
    {
        $prefix = rtrim($this->routePrefix, '/');
        if ($prefix !== '') {
            $path = $prefix . (str_starts_with($path, '/') ? $path : '/' . $path);
        }

        return new Route($path, $defaults, $requirements, $options, '', [], $methods);
    }
}
