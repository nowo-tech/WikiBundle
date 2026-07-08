<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Routing;

use Nowo\WikiBundle\Routing\WikiRouteLoader;
use PHPUnit\Framework\TestCase;

final class WikiRouteLoaderTest extends TestCase
{
    public function testLoadsWikiRoutes(): void
    {
        $loader = new WikiRouteLoader([
            'index' => ['path' => '/tools/wiki', 'name' => 'nowo_wiki_index'],
            'space' => ['path' => '/tools/wiki/spaces/{spaceSlug}', 'name' => 'nowo_wiki_space'],
        ], '');

        $collection = $loader->load('nowo_wiki', 'nowo_wiki');

        self::assertTrue($collection->get('nowo_wiki_index') instanceof \Symfony\Component\Routing\Route);
        self::assertTrue($collection->get('nowo_wiki_space') instanceof \Symfony\Component\Routing\Route);
    }

    public function testSupportsNowoWikiType(): void
    {
        $loader = new WikiRouteLoader([], '');
        self::assertTrue($loader->supports('x', 'nowo_wiki'));
        self::assertFalse($loader->supports('x', 'other'));
    }

    public function testAppliesRoutePrefix(): void
    {
        $loader = new WikiRouteLoader([
            'index' => ['path' => '/wiki', 'name' => 'nowo_wiki_index'],
        ], '/tools');

        $route = $loader->load('nowo_wiki', 'nowo_wiki')->get('nowo_wiki_index');

        self::assertNotNull($route);
        self::assertSame('/tools/wiki', $route->getPath());
    }

    public function testPageNewRouteRegisteredBeforePageView(): void
    {
        $routes = [
            'page_new'  => ['path' => '/tools/wiki/spaces/{spaceSlug}/pages/new', 'name' => 'nowo_wiki_page_new'],
            'page_view' => ['path' => '/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}', 'name' => 'nowo_wiki_page_view'],
        ];

        $collection = (new WikiRouteLoader($routes, ''))->load('nowo_wiki', 'nowo_wiki');

        $newRoute  = $collection->get('nowo_wiki_page_new');
        $viewRoute = $collection->get('nowo_wiki_page_view');

        self::assertNotNull($newRoute);
        self::assertNotNull($viewRoute);
        self::assertSame('/tools/wiki/spaces/{spaceSlug}/pages/new', $newRoute->getPath());
        self::assertSame('(?!new$)[a-z0-9]+(?:-[a-z0-9]+)*', $viewRoute->getRequirements()['pageSlug']);
    }
}
