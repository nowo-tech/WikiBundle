<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Ui;

use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbCollapse;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbNode;
use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbTrailView;
use Nowo\BreadcrumbKitBundle\Event\BreadcrumbTrailBuiltEvent;
use Nowo\WikiBundle\Ui\WikiBreadcrumbParentLabel;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function array_map;
use function http_build_query;

require_once __DIR__ . '/../../Stub/BreadcrumbKit/BreadcrumbTrail.php';

/**
 * 1.31 (web_ui.breadcrumb_current_label): the page crumb of the edit / history / compare / move trails is named after
 * the page; nothing else changes.
 */
final class WikiBreadcrumbParentLabelTest extends TestCase
{
    private const ROUTES = [
        'page_view'    => ['path' => '/p', 'name' => 'wiki_view'],
        'page_edit'    => ['path' => '/e', 'name' => 'wiki_edit'],
        'page_history' => ['path' => '/h', 'name' => 'wiki_history'],
        'page_diff'    => ['path' => '/d', 'name' => 'wiki_diff'],
        'page_move'    => ['path' => '/m', 'name' => 'wiki_move'],
    ];

    public function testThePageCrumbOfAChildPageTakesTheTitle(): void
    {
        $event = $this->event('wiki_edit', 'Deploy <runbook>');
        $this->listener()($event);

        $view = $event->getView();
        self::assertSame(['Home', 'Wiki', 'Deploy <runbook>', 'Edit'], array_map(static fn (BreadcrumbNode $n): string => $n->label, $view->nodes));
        self::assertSame('/spaces/eng/pages/deploy?x=1', $view->nodes[2]->url);
        self::assertSame(['spaceSlug' => 'eng'], $view->nodes[2]->routeParams);
        self::assertSame('icon', $view->nodes[2]->icon);
        self::assertSame('trail', $view->classList);
        self::assertTrue($view->collapse->enabled);
        self::assertFalse($view->homeIconReplacesLabel);
    }

    public function testNothingChangesWithoutATitleARouteOrAMatchingCrumb(): void
    {
        foreach ([
            $this->event('wiki_edit', null),
            $this->event('wiki_edit', '  '),
            $this->event('wiki_view', 'Deploy'),
            $this->event('other', 'Deploy'),
            $this->event('wiki_history', 'Deploy', '/elsewhere'),
        ] as $event) {
            $before = $event->getView();
            $this->listener()($event);
            self::assertSame($before, $event->getView());
        }

        // No page_view route, or a generator that cannot build it.
        $event = $this->event('wiki_diff', 'Deploy');
        $view  = $event->getView();
        (new WikiBreadcrumbParentLabel($this->generator(), ['page_diff' => self::ROUTES['page_diff']]))($event);
        self::assertSame($view, $event->getView());
        $failing = $this->createStub(UrlGeneratorInterface::class);
        $failing->method('generate')->willThrowException(new RuntimeException('missing parameter'));
        (new WikiBreadcrumbParentLabel($failing, self::ROUTES))($event);
        self::assertSame($view, $event->getView());
        // No request at all.
        $event = new BreadcrumbTrailBuiltEvent($view, 'default', 'default', 'ok', null, null);
        $this->listener()($event);
        self::assertSame($view, $event->getView());
    }

    /**
     * 1.33 fix: tenant copies of the routes (`tenant_paths.name_prefix`) are recognised by the route key the controller
     * puts on the request; the page view URL comes from the same prefix ("Beacon › Wiki › <title> › Edit").
     */
    public function testATenantRouteIsRecognisedByItsRouteKey(): void
    {
        foreach (['page_edit' => 'app_project_wiki_edit', 'page_history' => 'app_project_wiki_history', 'page_diff' => 'app_project_wiki_diff', 'page_move' => 'app_project_wiki_move'] as $key => $route) {
            $event = $this->event($route, 'Deploy', '/projects/eng/wiki/pages/deploy', $key);
            $this->listener()($event);
            self::assertSame(['Home', 'Wiki', 'Deploy', 'Edit'], array_map(static fn (BreadcrumbNode $n): string => $n->label, $event->getView()->nodes), $route);
        }

        // The bundle route with its key, as before.
        $event = $this->event('wiki_edit', 'Deploy', '/spaces/eng/pages/deploy', 'page_edit');
        $this->listener()($event);
        self::assertSame('Deploy', $event->getView()->nodes[2]->label);

        // The prefixed page view does not exist: the bundle name (the tenant URL generator maps it).
        $generator = $this->createStub(UrlGeneratorInterface::class);
        $generator->method('generate')->willReturnCallback(static fn (string $name): string => $name === 'wiki_view' ? '/projects/eng/wiki/pages/deploy' : throw new RuntimeException('no route'));
        $event = $this->event('app_project_wiki_edit', 'Deploy', '/projects/eng/wiki/pages/deploy', 'page_edit');
        (new WikiBreadcrumbParentLabel($generator, self::ROUTES))($event);
        self::assertSame('Deploy', $event->getView()->nodes[2]->label);
    }

    public function testATenantRouteWithoutAMatchingKeyChangesNothing(): void
    {
        foreach ([
            // 1.31 callers (no key): only the configured names.
            $this->event('app_project_wiki_edit', 'Deploy', '/projects/eng/wiki/pages/deploy'),
            // A key that is not a child page, an unconfigured key, a route of another action.
            $this->event('app_project_wiki_view', 'Deploy', '/projects/eng/wiki/pages/deploy', 'page_view'),
            $this->event('app_project_wiki_edit', 'Deploy', '/projects/eng/wiki/pages/deploy', 'page_trash'),
            $this->event('app_project_wiki_history', 'Deploy', '/projects/eng/wiki/pages/deploy', 'page_edit'),
            // No route on the request.
            $this->event('', 'Deploy', '/projects/eng/wiki/pages/deploy', 'page_edit'),
        ] as $event) {
            $before = $event->getView();
            $this->listener()($event);
            self::assertSame($before, $event->getView());
        }

        // A key configured nowhere.
        $event  = $this->event('app_project_wiki_move', 'Deploy', '/projects/eng/wiki/pages/deploy', 'page_move');
        $before = $event->getView();
        (new WikiBreadcrumbParentLabel($this->generator(), ['page_view' => self::ROUTES['page_view']]))($event);
        self::assertSame($before, $event->getView());

        // A generator answering without a path.
        $generator = $this->createStub(UrlGeneratorInterface::class);
        $generator->method('generate')->willReturn('http://');
        (new WikiBreadcrumbParentLabel($generator, self::ROUTES))($event);
        self::assertSame($before, $event->getView());
    }

    public function testRememberPutsTheTitleOnTheRequest(): void
    {
        $request = Request::create('/');
        WikiBreadcrumbParentLabel::remember($request, 'Deploy');

        self::assertSame('Deploy', $request->attributes->get(WikiBreadcrumbParentLabel::ATTRIBUTE));
        self::assertFalse($request->attributes->has(WikiBreadcrumbParentLabel::ROUTE_KEY_ATTRIBUTE));
        WikiBreadcrumbParentLabel::remember($request, 'Deploy', 'page_edit');
        self::assertSame('page_edit', $request->attributes->get(WikiBreadcrumbParentLabel::ROUTE_KEY_ATTRIBUTE));
    }

    private function listener(): WikiBreadcrumbParentLabel
    {
        return new WikiBreadcrumbParentLabel($this->generator(), self::ROUTES);
    }

    private function generator(): UrlGeneratorInterface
    {
        $generator = $this->createStub(UrlGeneratorInterface::class);
        $generator->method('generate')->willReturnCallback(static fn (string $name, array $params): string => match ($name) {
            'wiki_view'             => '/spaces/' . $params['spaceSlug'] . '/pages/' . $params['pageSlug'] . '?' . http_build_query($params),
            'app_project_wiki_view' => '/projects/' . $params['spaceSlug'] . '/wiki/pages/' . $params['pageSlug'],
            default                 => '/' . $name,
        });

        return $generator;
    }

    private function event(string $route, ?string $title, string $pageUrl = '/spaces/eng/pages/deploy?x=1', ?string $routeKey = null): BreadcrumbTrailBuiltEvent
    {
        $request = Request::create('/');
        if ($route !== '') {
            $request->attributes->set('_route', $route);
        }
        $request->attributes->set('_route_params', ['spaceSlug' => 'eng', 'pageSlug' => 'deploy', 'object' => new stdClass()]);
        if ($title !== null) {
            WikiBreadcrumbParentLabel::remember($request, $title, $routeKey);
        }
        $view = new BreadcrumbTrailView(
            [
                new BreadcrumbNode('Home', '/', true, false),
                new BreadcrumbNode('Wiki', null, false, false),
                new BreadcrumbNode('Page', $pageUrl, true, false, 'icon', ['spaceSlug' => 'eng']),
                new BreadcrumbNode('Edit', '/spaces/eng/pages/deploy', false, true),
            ],
            classList: 'trail',
            homeIconReplacesLabel: false,
            collapse: new BreadcrumbCollapse(true),
        );

        return new BreadcrumbTrailBuiltEvent($view, 'default', 'default', 'ok', $request, $route);
    }
}
