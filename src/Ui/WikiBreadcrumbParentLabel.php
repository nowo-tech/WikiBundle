<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ui;

use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbNode;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbTrailView;
use Nowo\BreadcrumbKitBundle\Event\BreadcrumbTrailBuiltEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

use function in_array;
use function is_string;
use function parse_url;
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
 */
final readonly class WikiBreadcrumbParentLabel
{
    /** Request attribute holding the page title of an edit / history / compare / move page. */
    public const ATTRIBUTE = '_nowo_wiki_crumb_page_title';

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
     * Puts the page title on the request of a child page (called by the controller after the read check).
     */
    public static function remember(Request $request, string $title): void
    {
        $request->attributes->set(self::ATTRIBUTE, $title);
    }

    public function __invoke(BreadcrumbTrailBuiltEvent $event): void
    {
        $title = $event->request?->attributes->get(self::ATTRIBUTE);
        if (!is_string($title) || trim($title) === '' || !isset($this->routes['page_view']) || !in_array($event->routeName, $this->childRouteNames(), true)) {
            return;
        }

        try {
            $path = parse_url($this->urlGenerator->generate($this->routes['page_view']['name'], $event->routeParameters), PHP_URL_PATH);
        } catch (Throwable) {
            return;
        }

        $view    = $event->getView();
        $nodes   = [];
        $renamed = false;
        foreach ($view->nodes as $node) {
            if (!$node->current && $node->url !== null && is_string($path) && parse_url($node->url, PHP_URL_PATH) === $path) {
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
     * @return list<string>
     */
    private function childRouteNames(): array
    {
        $names = [];
        foreach (self::CHILD_ROUTES as $key) {
            if (isset($this->routes[$key])) {
                $names[] = $this->routes[$key]['name'];
            }
        }

        return $names;
    }
}
