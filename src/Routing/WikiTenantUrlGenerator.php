<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Routing;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

use function is_string;

/**
 * Router decorator (1.9, registered when {@code tenant_paths} is configured): generating a space-scoped bundle route
 * with a {@code spaceSlug} that a tenant's requirement matches generates that tenant's copy instead, so templates and
 * redirects keep using the bundle route names.
 */
final readonly class WikiTenantUrlGenerator implements RouterInterface, RequestMatcherInterface, WarmableInterface
{
    /**
     * @param RequestMatcherInterface&RouterInterface&WarmableInterface $inner decorated router
     * @param WikiTenantPaths $tenantPaths configured tenant paths
     */
    public function __construct(
        private RouterInterface&RequestMatcherInterface&WarmableInterface $inner,
        private WikiTenantPaths $tenantPaths,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        $slug = $parameters['spaceSlug'] ?? null;
        if (is_string($slug) && $slug !== '') {
            $name = $this->tenantPaths->routeFor($name, $slug);
        }

        return $this->inner->generate($name, $parameters, $referenceType);
    }

    public function setContext(RequestContext $context): void
    {
        // @igor-ignore - The router contract: Symfony sets the request context on the router each request.
        $this->inner->setContext($context);
    }

    public function getContext(): RequestContext
    {
        return $this->inner->getContext();
    }

    public function getRouteCollection(): RouteCollection
    {
        return $this->inner->getRouteCollection();
    }

    /**
     * @return array<string, mixed>
     */
    public function match(string $pathinfo): array
    {
        return $this->inner->match($pathinfo);
    }

    /**
     * @return array<string, mixed>
     */
    public function matchRequest(Request $request): array
    {
        return $this->inner->matchRequest($request);
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        return $this->inner->warmUp($cacheDir, $buildDir);
    }
}
