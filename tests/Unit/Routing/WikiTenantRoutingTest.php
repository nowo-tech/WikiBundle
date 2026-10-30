<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Routing;

use LogicException;
use Nowo\WikiBundle\Routing\WikiRouteLoader;
use Nowo\WikiBundle\Routing\WikiTenantPaths;
use Nowo\WikiBundle\Routing\WikiTenantUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;

final class WikiTenantRoutingTest extends TestCase
{
    private const UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    private const PROJECT = '0b9c9a9e-1111-4222-8333-444455556666';

    /** @var array<string, array{path: string, name: string}> */
    private const ROUTES = [
        'search'              => ['path' => '/wiki/search', 'name' => 'nowo_wiki_search'],
        'space'               => ['path' => '/tools/wiki/spaces/{spaceSlug}', 'name' => 'nowo_wiki_space'],
        'page_view'           => ['path' => '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}', 'name' => 'nowo_wiki_page_view'],
        'attachment_download' => ['path' => '/tools/wiki/spaces/{spaceSlug}/attachments/{attachmentId}', 'name' => 'nowo_wiki_attachment_download'],
    ];

    public function testTenantCopiesAreRegisteredAndTheTenantLessOnesCanBeLeftOut(): void
    {
        $paths      = $this->paths(true);
        $collection = (new WikiRouteLoader(self::ROUTES, '', $paths))->load('nowo_wiki', 'nowo_wiki');

        self::assertSame('/projects/{spaceSlug}/wiki/pages/{pageSlug}', $collection->get('app_project_nowo_wiki_page_view')?->getPath());
        self::assertSame(self::UUID, $collection->get('app_project_nowo_wiki_page_view')?->getRequirement('spaceSlug'));
        self::assertSame('/wiki/{spaceSlug}/attachments/{attachmentId}', $collection->get('app_nowo_wiki_attachment_download')?->getPath());
        self::assertNull($collection->get('nowo_wiki_page_view'));
        self::assertNotNull($collection->get('nowo_wiki_search'));
        self::assertNull($collection->get('app_project_nowo_wiki_search'));
        self::assertTrue($paths->isTenantOnly());

        $kept = (new WikiRouteLoader(self::ROUTES, '', $this->paths(false)))->load('nowo_wiki', 'nowo_wiki');
        self::assertNotNull($kept->get('nowo_wiki_page_view'));
        self::assertFalse((new WikiTenantPaths([], self::ROUTES, true))->isTenantOnly());
    }

    public function testTheFirstMatchingTenantOwnsTheSlug(): void
    {
        $paths = $this->paths(true);

        self::assertSame('app_project_nowo_wiki_page_view', $paths->routeFor('nowo_wiki_page_view', self::PROJECT));
        self::assertSame('app_nowo_wiki_page_view', $paths->routeFor('nowo_wiki_page_view', 'general'));
        self::assertSame('nowo_wiki_page_view', $paths->routeFor('nowo_wiki_page_view', 'other-space'));
        self::assertSame('nowo_wiki_search', $paths->routeFor('nowo_wiki_search', 'general'));
        self::assertSame('not_a_wiki_route', $paths->routeFor('not_a_wiki_route', 'general'));
        self::assertSame('/x/{spaceSlug}/y', WikiTenantPaths::tenantPath('/x/{spaceSlug}', '/a/{spaceSlug}/y'));
    }

    public function testTheGeneratorRewritesTenantRoutesAndDelegatesTheRest(): void
    {
        $paths = $this->paths(true);
        $inner = new class(new class((new WikiRouteLoader(self::ROUTES, '', $paths))->load('nowo_wiki', 'nowo_wiki')) implements LoaderInterface {
            public function __construct(private readonly RouteCollection $collection)
            {
            }

            public function load(mixed $resource, ?string $type = null): mixed
            {
                return $this->collection;
            }

            public function supports(mixed $resource, ?string $type = null): bool
            {
                return true;
            }

            public function getResolver(): LoaderResolverInterface
            {
                throw new LogicException('unused');
            }

            public function setResolver(LoaderResolverInterface $resolver): void
            {
            }
        }, 'routes') extends Router implements WarmableInterface {

            public function warmUp(string $cacheDir, ?string $buildDir = null): array
            {
                return [];
            }
        };
        $router = new WikiTenantUrlGenerator($inner, $paths);

        self::assertSame('/projects/' . self::PROJECT . '/wiki/pages/intro', $router->generate('nowo_wiki_page_view', ['spaceSlug' => self::PROJECT, 'pageSlug' => 'intro']));
        self::assertSame('/wiki/general', $router->generate('nowo_wiki_space', ['spaceSlug' => 'general']));
        self::assertSame('/wiki/search', $router->generate('nowo_wiki_search'));

        $context = new RequestContext('/base');
        $router->setContext($context);
        self::assertSame($context, $router->getContext());
        self::assertCount(7, $router->getRouteCollection());
        self::assertSame('nowo_wiki_search', $router->match('/wiki/search')['_route']);
        self::assertSame('app_nowo_wiki_space', $router->matchRequest(Request::create('/wiki/general'))['_route']);
        self::assertSame([], $router->warmUp(sys_get_temp_dir()));
    }

    private function paths(bool $tenantOnly): WikiTenantPaths
    {
        return new WikiTenantPaths([
            ['path' => '/projects/{spaceSlug}/wiki', 'name_prefix' => 'app_project_', 'slug_requirement' => self::UUID],
            ['path' => '/wiki/{spaceSlug}', 'name_prefix' => 'app_', 'slug_requirement' => 'general|user-' . self::UUID],
        ], self::ROUTES, $tenantOnly);
    }
}
