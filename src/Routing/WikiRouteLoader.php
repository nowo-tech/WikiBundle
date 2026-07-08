<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Routing;

use Nowo\WikiBundle\Controller\WikiManageController;
use RuntimeException;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class WikiRouteLoader extends Loader
{
    private bool $loaded = false;

    /** Slug pattern excluding reserved segment "new" (create-page route). */
    private const PAGE_SLUG = '(?!new$)[a-z0-9]+(?:-[a-z0-9]+)*';

    /**
     * @param array<string, array{path: string, name: string}> $routes
     */
    public function __construct(
        private readonly array $routes,
        private readonly string $routePrefix,
    ) {
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new RuntimeException('Wiki routes already loaded.');
        }

        $this->loaded = true;
        $collection   = new RouteCollection();
        $controller   = WikiManageController::class;

        /** @var array<string, array{0: string, 1: list<string>, 2: array<string, string>}> $map */
        $map = [
            'index'        => ['index', ['GET'], []],
            'space'        => ['space', ['GET'], ['spaceSlug' => '[a-z0-9]+(?:-[a-z0-9]+)*']],
            'page_new'     => ['newPage', ['GET', 'POST'], ['spaceSlug' => '[a-z0-9]+(?:-[a-z0-9]+)*']],
            'page_view'    => ['viewPage', ['GET'], ['spaceSlug' => '[a-z0-9]+(?:-[a-z0-9]+)*', 'pageSlug' => self::PAGE_SLUG]],
            'page_edit'    => ['editPage', ['GET', 'POST'], ['spaceSlug' => '[a-z0-9]+(?:-[a-z0-9]+)*', 'pageSlug' => self::PAGE_SLUG]],
            'page_history' => ['pageHistory', ['GET'], ['spaceSlug' => '[a-z0-9]+(?:-[a-z0-9]+)*', 'pageSlug' => self::PAGE_SLUG]],
            'page_diff'    => ['pageDiff', ['GET'], ['spaceSlug' => '[a-z0-9]+(?:-[a-z0-9]+)*', 'pageSlug' => self::PAGE_SLUG]],
            'page_archive' => ['archivePage', ['POST'], ['spaceSlug' => '[a-z0-9]+(?:-[a-z0-9]+)*', 'pageSlug' => self::PAGE_SLUG]],
            'search'       => ['search', ['GET'], []],
            'ai_ask'       => ['askAi', ['GET', 'POST'], []],
            'space_export' => ['exportSpace', ['GET'], ['spaceSlug' => '[a-z0-9]+(?:-[a-z0-9]+)*']],
            'space_import' => ['importSpace', ['POST'], ['spaceSlug' => '[a-z0-9]+(?:-[a-z0-9]+)*']],
        ];

        foreach ($map as $key => [$action, $methods, $requirements]) {
            if (!isset($this->routes[$key])) {
                continue;
            }

            $collection->add(
                $this->routes[$key]['name'],
                $this->createRoute(
                    $this->routes[$key]['path'],
                    ['_controller' => $controller . '::' . $action],
                    $methods,
                    $requirements,
                ),
            );
        }

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === 'nowo_wiki';
    }

    /**
     * @param list<string> $methods
     * @param array<string, string> $requirements
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
