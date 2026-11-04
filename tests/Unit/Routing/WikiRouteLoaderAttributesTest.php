<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Routing;

use Nowo\WikiBundle\Controller\WikiCommentController;
use Nowo\WikiBundle\Controller\WikiManageController;
use Nowo\WikiBundle\Controller\WikiTreeController;
use Nowo\WikiBundle\Routing\WikiRouteLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;

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

    public function testTheTreeRoutesAreLoadedWhenConfigured(): void
    {
        $collection = (new WikiRouteLoader([
            'page_move'  => ['path' => '/s/{spaceSlug}/p/{pageSlug}/move', 'name' => 'move'],
            'space_tree' => ['path' => '/s/{spaceSlug}/tree/{action}', 'name' => 'tree'],
        ], ''))->load('.', 'nowo_wiki');

        self::assertSame(WikiManageController::class . '::movePage', $collection->get('move')?->getDefault('_controller'));
        self::assertSame(['GET', 'POST'], $collection->get('move')?->getMethods());
        self::assertSame(WikiTreeController::class . '::tree', $collection->get('tree')?->getDefault('_controller'));
        self::assertSame(['POST'], $collection->get('tree')?->getMethods());
        self::assertSame('move|state', $collection->get('tree')?->getRequirement('action'));
    }

    public function testTheCommentRoutesTakeTheirOptionalPath(): void
    {
        // 1.16: `…/comments/{path}` with the attribute default `path: ''`, so the bare base URL matches too.
        $collection = (new WikiRouteLoader([
            'page_comments'     => ['path' => '/s/{spaceSlug}/p/{pageSlug}/comments/{path}', 'name' => 'comments'],
            'space_panel_state' => ['path' => '/s/{spaceSlug}/panel-state', 'name' => 'panel'],
        ], ''))->load('.', 'nowo_wiki');

        $comments = $collection->get('comments');
        self::assertNotNull($comments);
        self::assertSame(WikiCommentController::class . '::comments', $comments->getDefault('_controller'));
        self::assertSame('', $comments->getDefault('path'));
        self::assertSame(['GET', 'POST', 'PATCH', 'PUT', 'DELETE'], $comments->getMethods());
        $matcher = new UrlMatcher($collection, new RequestContext());
        self::assertSame('', $matcher->match('/s/eng/p/run/comments')['path']);
        self::assertSame('threads/abc/comments', $matcher->match('/s/eng/p/run/comments/threads/abc/comments')['path']);
        self::assertSame('comments/c1/reactions/👍', $matcher->match('/s/eng/p/run/comments/comments/c1/reactions/%F0%9F%91%8D')['path']);
        self::assertSame(WikiCommentController::class . '::panelState', $collection->get('panel')?->getDefault('_controller'));
        self::assertSame('/s/eng/p/run/comments', (new UrlGenerator($collection, new RequestContext()))->generate('comments', ['spaceSlug' => 'eng', 'pageSlug' => 'run', 'path' => '']));
    }
}
