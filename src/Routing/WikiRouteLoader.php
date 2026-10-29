<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Routing;

use Nowo\WikiBundle\Controller\WikiManageController;
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
 * The routes are declared with #[Route] on {@see WikiManageController} (methods, requirements, default path / name)
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
     */
    public function __construct(
        private readonly array $routes,
        private readonly string $routePrefix,
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
        $controller   = WikiManageController::class;

        foreach ((new ReflectionClass($controller))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(RouteAttribute::class) as $attribute) {
                $declared = $attribute->newInstance();
                $key      = (string) ($declared->options[self::ROUTE_KEY_OPTION] ?? '');
                if (!isset($this->routes[$key])) {
                    continue;
                }

                $collection->add(
                    $this->routes[$key]['name'],
                    $this->createRoute(
                        $this->routes[$key]['path'],
                        ['_controller' => $controller . '::' . $method->getName()],
                        $declared->methods,
                        $declared->requirements,
                    ),
                );
            }
        }

        return $collection;
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
     * @param array<string, mixed> $defaults
     * @param array<string> $methods
     * @param array<string, mixed> $requirements
     */
    private function createRoute(string $path, array $defaults, array $methods, array $requirements): Route
    {
        $prefix = rtrim($this->routePrefix, '/');
        if ($prefix !== '') {
            $path = $prefix . (str_starts_with($path, '/') ? $path : '/' . $path);
        }

        return new Route($path, $defaults, $requirements, [], '', [], $methods);
    }
}
