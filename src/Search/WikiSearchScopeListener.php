<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Search;

use Nowo\WikiBundle\Routing\WikiRouteLoader;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function is_array;
use function is_string;
use function preg_match;

/**
 * `search.scope: space` (1.21): the search page answers inside one space only. A request to the search route without
 * a valid `?space=` slug (one the space routes accept: the default form or a `tenant_paths` slug_requirement) is 404;
 * with one, the slug becomes the request attribute `spaceSlug` (and a route parameter),
 * as on the space pages, so host listeners (permissions, tabs, breadcrumbs) see the space. Access to the space is
 * still checked by the controller (a hidden or unknown space is 404 there). Registered by WikiExtension only with
 * `search.scope: space`, on kernel.request after the router (priority 30). Stateless: safe in worker mode.
 */
final readonly class WikiSearchScopeListener
{
    /** Request attribute of the space on the space-scoped routes. */
    public const ATTRIBUTE = 'spaceSlug';

    /**
     * @param string $routeName name of the search route (`routes.search.name`)
     * @param list<string> $slugRequirements the `slug_requirement` of each `tenant_paths` row
     */
    public function __construct(
        private string $routeName,
        private array $slugRequirements = [],
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->attributes->get('_route') !== $this->routeName) {
            return;
        }

        // all(): a ?space[]= array never reaches a string check (InputBag::get() would throw a 400).
        $space = $request->query->all()['space'] ?? null;
        if (!is_string($space) || !$this->isSlug($space)) {
            throw new NotFoundHttpException('Not found.');
        }

        $params = $request->attributes->get('_route_params');
        $request->attributes->set(self::ATTRIBUTE, $space);
        $request->attributes->set('_route_params', [self::ATTRIBUTE => $space] + (is_array($params) ? $params : []));
    }

    /** Only the form of the value: which spaces the user may see is the controller's check (WikiSpaceGuard). */
    private function isSlug(string $space): bool
    {
        foreach ([WikiRouteLoader::SPACE_SLUG, ...$this->slugRequirements] as $requirement) {
            if (preg_match('#^(?:' . $requirement . ')$#D', $space) === 1) {
                return true;
            }
        }

        return false;
    }
}
