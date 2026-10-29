<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Routing;

use Nowo\WikiBundle\Controller\WikiManageController;
use Nowo\WikiBundle\Routing\WikiRouteLoader;
use PHPUnit\Framework\TestCase;

/**
 * The #[Route] attributes keep every method, requirement and action of the pre-attribute route map (REQ-SF-004).
 */
final class WikiRouteLoaderAttributesTest extends TestCase
{
    public function testAttributeRoutesMatchTheLegacyMap(): void
    {
        $space = WikiRouteLoader::SPACE_SLUG;
        $page  = WikiRouteLoader::PAGE_SLUG;
        $map   = [
            'index'        => ['index', ['GET'], []],
            'space'        => ['space', ['GET'], ['spaceSlug' => $space]],
            'page_new'     => ['newPage', ['GET', 'POST'], ['spaceSlug' => $space]],
            'page_view'    => ['viewPage', ['GET'], ['spaceSlug' => $space, 'pageSlug' => $page]],
            'page_edit'    => ['editPage', ['GET', 'POST'], ['spaceSlug' => $space, 'pageSlug' => $page]],
            'page_history' => ['pageHistory', ['GET'], ['spaceSlug' => $space, 'pageSlug' => $page]],
            'page_diff'    => ['pageDiff', ['GET'], ['spaceSlug' => $space, 'pageSlug' => $page]],
            'page_archive' => ['archivePage', ['POST'], ['spaceSlug' => $space, 'pageSlug' => $page]],
            'search'       => ['search', ['GET'], []],
            'ai_ask'       => ['askAi', ['GET', 'POST'], []],
            'space_export' => ['exportSpace', ['GET'], ['spaceSlug' => $space]],
            'space_import' => ['importSpace', ['POST'], ['spaceSlug' => $space]],
        ];

        $routes = [];
        foreach ($map as $key => $unused) {
            $routes[$key] = ['path' => '/x/' . $key . '/{spaceSlug}/{pageSlug}', 'name' => 'custom_' . $key];
        }

        $collection = (new WikiRouteLoader($routes, '/prefix'))->load('.', 'nowo_wiki');

        self::assertCount(12, $collection);
        foreach ($map as $key => [$action, $methods, $requirements]) {
            $route = $collection->get('custom_' . $key);
            self::assertNotNull($route, $key);
            self::assertSame('/prefix/x/' . $key . '/{spaceSlug}/{pageSlug}', $route->getPath(), $key);
            self::assertSame(WikiManageController::class . '::' . $action, $route->getDefault('_controller'), $key);
            self::assertSame($methods, $route->getMethods(), $key);
            self::assertSame($requirements, $route->getRequirements(), $key);
        }
    }
}
