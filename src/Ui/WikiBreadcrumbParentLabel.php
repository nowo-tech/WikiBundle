<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ui;

use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbNode;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbTrailView;
use Nowo\BreadcrumbKitBundle\Event\BreadcrumbTrailBuiltEvent;
use Nowo\WikiBundle\Routing\WikiRouteLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

use function in_array;
use function is_string;
use function parse_url;
use function str_ends_with;
use function strlen;
use function substr;
use function trim;

use const PHP_URL_PATH;

/**
 * 1.31 (`web_ui.breadcrumb_current_label`, optional BreadcrumbKit >= 2.17): on the edit, history, compare and move
 * pages of a page, the BreadcrumbKit crumb of the page view (the parent of the current crumb, "Page" in a host's trail)
 * is named after the page title, as the page view's own current crumb is (`BreadcrumbCurrentLabelInterface`).
 *
 * A `BreadcrumbTrailBuiltEvent` listener, registered only with the option on and BreadcrumbKit installed. The title
 * travels on the request being handled ({@see self::ATTRIBUTE}), set by the controller once the viewer passed the read
 * check of the page; nothing is kept in this service (worker mode). Only a crumb that is not the current one and
 * whose URL path is the page view of the same space and page is renamed; its link, icon and parameters stay. Plain
 * text: BreadcrumbKit escapes labels.
 *
 * 1.33 (fix): the page is recognised by its route key ({@see WikiRouteLoader::ROUTE_KEY_OPTION} of the controller
 * action, {@see self::ROUTE_KEY_ATTRIBUTE} on the request), not by comparing route names: a tenant copy of the route
 * (`tenant_paths.name_prefix`, e.g. `app_project_nowo_wiki_page_edit`) is named after the page too, and its page view
 * URL is generated from the same prefix (`app_project_nowo_wiki_page_view`), the bundle name as the fallback.
 */
final readonly class WikiBreadcrumbParentLabel
{
    /** Request attribute holding the page title of an edit / history / compare / move page. */
    public const ATTRIBUTE = '_nowo_wiki_crumb_page_title';

    /** 1.33: request attribute holding the route key of the page (page_edit, page_history, page_diff, page_move). */
    public const ROUTE_KEY_ATTRIBUTE = '_nowo_wiki_crumb_route_key';

    /** Route keys whose trail has the page view as a parent crumb. */
    public const CHILD_ROUTES = ['page_edit', 'page_history', 'page_diff', 'page_move'];

    /**
     * @param array<string, array{path: string, name: string}> $routes `nowo_wiki.routes`
     */
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private array $routes,
    ) {
    }

    /**
     * Puts the page title on the request of a child page (called by the controller after the read check). 1.33: with
     * the route key of the action ({@see WikiRouteLoader::ROUTE_KEY_OPTION}); without it the route is recognised by its
     * configured name only (the 1.31 behaviour).
     */
    public static function remember(Request $request, string $title, ?string $routeKey = null): void
    {
        $request->attributes->set(self::ATTRIBUTE, $title);
        if ($routeKey !== null) {
            $request->attributes->set(self::ROUTE_KEY_ATTRIBUTE, $routeKey);
        }
    }

    public function __invoke(BreadcrumbTrailBuiltEvent $event): void
    {
        $title  = $event->request?->attributes->get(self::ATTRIBUTE);
        $prefix = $this->routePrefix($event);
        if (!is_string($title) || trim($title) === '' || !isset($this->routes['page_view']) || $prefix === null) {
            return;
        }

        $path = $this->pageViewPath($prefix, $event->routeParameters);
        if ($path === null) {
            return;
        }

        $view    = $event->getView();
        $nodes   = [];
        $renamed = false;
        foreach ($view->nodes as $node) {
            if (!$node->current && $node->url !== null && parse_url($node->url, PHP_URL_PATH) === $path) {
                $node    = new BreadcrumbNode($title, $node->url, $node->linkEnabled, $node->current, $node->icon, $node->routeParams);
                $renamed = true;
            }
            $nodes[] = $node;
        }

        if ($renamed) {
            $event->setView(new BreadcrumbTrailView(
                $nodes,
                $view->homeIcon,
                $view->separatorIcon,
                $view->classList,
                $view->classItem,
                $view->classSeparator,
                $view->classCurrent,
                $view->responsiveConfig,
                $view->homeIconReplacesLabel,
                $view->collapse,
            ));
        }
    }

    /**
     * The name prefix of the matched child route ('' for the bundle route, `tenant_paths.name_prefix` for a tenant copy),
     * or null when the trail is not one of a child page. With the route key the controller put on the request, the
     * matched route must end with that key's configured name; without it (1.31 callers) it must equal one.
     */
    private function routePrefix(BreadcrumbTrailBuiltEvent $event): ?string
    {
        $route = $event->routeName;
        if (!is_string($route) || $route === '') {
            return null;
        }
        $key = $event->request?->attributes->get(self::ROUTE_KEY_ATTRIBUTE);
        if (!is_string($key)) {
            foreach (self::CHILD_ROUTES as $child) {
                if (isset($this->routes[$child]) && $this->routes[$child]['name'] === $route) {
                    return '';
                }
            }

            return null;
        }
        if (!in_array($key, self::CHILD_ROUTES, true) || !isset($this->routes[$key])) {
            return null;
        }
        $name = $this->routes[$key]['name'];

        return str_ends_with($route, $name) ? substr($route, 0, -strlen($name)) : null;
    }

    /**
     * Path of the page view in the same family of routes (tenant prefix first, then the bundle name), or null.
     *
     * @param array<string, mixed> $parameters
     */
    private function pageViewPath(string $prefix, array $parameters): ?string
    {
        $name  = $this->routes['page_view']['name'];
        $names = $prefix === '' ? [$name] : [$prefix . $name, $name];
        foreach ($names as $candidate) {
            try {
                $path = parse_url($this->urlGenerator->generate($candidate, $parameters), PHP_URL_PATH);
            } catch (Throwable) {
                continue;
            }
            if (is_string($path)) {
                return $path;
            }
        }

        return null;
    }
}
