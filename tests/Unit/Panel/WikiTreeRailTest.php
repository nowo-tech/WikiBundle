<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Panel;

use Nowo\WikiBundle\Controller\WikiCommentController;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Panel\SessionWikiPanelStateStore;
use Nowo\WikiBundle\Panel\WikiPanelStateStoreInterface;
use Nowo\WikiBundle\Panel\WikiTreeRail;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * 1.29 web_ui.tree_rail: the rail of the tree card — the state (query, else the store), the no-JavaScript link, the
 * store endpoint, the localStorage fallback without a session; per user and per request (worker mode).
 */
final class WikiTreeRailTest extends TestCase
{
    private const ROUTES = ['space_panel_state' => ['path' => '/s/{spaceSlug}/panel-state', 'name' => 'nowo_wiki_space_panel_state']];

    private ?Session $session = null;

    public function testTheRailFollowsTheQueryThenTheStore(): void
    {
        [$rail, $stack, $store] = $this->rail();
        $user                   = new TestUserEntity('ana');
        $space                  = $this->space();
        $request                = $this->request('/wiki/run?x=1', $stack);

        $expanded = $rail->rail($request, $user, $space);
        self::assertSame(WikiTreeRail::ID, $expanded['id']);
        self::assertSame(WikiTreeRail::BODY_ID, $expanded['body_id']);
        self::assertFalse($expanded['collapsed']);
        self::assertSame('/wiki/run?tree=rail', $expanded['href']);
        self::assertSame('/r/nowo_wiki_space_panel_state/eng', $expanded['state_url']);
        self::assertSame('csrf:' . WikiCommentController::panelTokenId($space), $expanded['state_token']);
        self::assertSame(WikiPanelStateStoreInterface::TREE_RAIL, $expanded['state_panel']);
        self::assertNull($expanded['persist']);

        $store->setOpen($user, WikiPanelStateStoreInterface::TREE_RAIL, true);
        $collapsed = $rail->rail($request, $user, $space);
        self::assertTrue($collapsed['collapsed']);
        self::assertSame('/wiki/run?tree=full', $collapsed['href']);
        // The query wins for this render (the no-JavaScript link), both ways.
        self::assertFalse($rail->rail($this->request('/wiki/run?tree=full', $stack), $user, $space)['collapsed']);
        $store->setOpen($user, WikiPanelStateStoreInterface::TREE_RAIL, false);
        self::assertTrue($rail->rail($this->request('/wiki/run?tree=rail', $stack), $user, $space)['collapsed']);
        self::assertFalse($rail->rail($this->request('/wiki/run?tree=other', $stack), $user, $space)['collapsed']);
    }

    public function testWithoutASessionTheBrowserKeepsTheState(): void
    {
        [$rail] = $this->rail();
        $out    = $rail->rail(Request::create('/wiki/run'), new TestUserEntity('ana'), $this->space());
        self::assertFalse($out['collapsed']);
        self::assertSame(WikiTreeRail::PERSIST_KEY, $out['persist']);
        self::assertNull($out['state_url']);
        self::assertNull($out['state_token']);
        self::assertSame('?tree=rail', $rail->rail(null, new TestUserEntity('ana'), $this->space())['href']);

        // A host store (comments.state_store): the server keeps it even without a session.
        [$rail] = $this->rail(sessionStore: false);
        $out    = $rail->rail(Request::create('/wiki/run'), new TestUserEntity('ana'), $this->space());
        self::assertNull($out['persist']);
        self::assertSame('/r/nowo_wiki_space_panel_state/eng', $out['state_url']);

        // No panel state route: no store attributes.
        [$rail] = $this->rail(routes: []);
        $out    = $rail->rail(null, new TestUserEntity('ana'), $this->space());
        self::assertNull($out['state_url']);
        self::assertNull($out['state_token']);
    }

    /**
     * Worker mode (kernel reset false): one service instance, many requests alternating two users; each sees only
     * its own state and the service keeps nothing between calls.
     */
    public function testTwoUsersInOneKernelNeverShareTheState(): void
    {
        [$rail, $stack, $store] = $this->rail();
        $ana                    = new TestUserEntity('ana');
        $bob                    = new TestUserEntity('bob');
        $space                  = $this->space();
        $session                = new Session(new MockArraySessionStorage());
        $before                 = null;
        for ($i = 0; $i < 2000; ++$i) {
            $request = Request::create('/wiki/run');
            $request->setSession($session);
            $stack->push($request);
            $store->setOpen($ana, WikiPanelStateStoreInterface::TREE_RAIL, $i % 2 === 0);
            self::assertSame($i % 2 === 0, $rail->rail($request, $ana, $space)['collapsed']);
            self::assertFalse($rail->rail($request, $bob, $space)['collapsed']);
            $stack->pop();
            if ($i === 100) {
                $before = memory_get_usage();
            }
        }
        self::assertLessThan(512 * 1024, memory_get_usage() - (int) $before);
        foreach (['state', 'urlGenerator', 'csrf', 'routes', 'sessionStore'] as $property) {
            self::assertTrue((new ReflectionProperty(WikiTreeRail::class, $property))->isReadOnly());
        }
    }

    /**
     * @param array<string, array{path: string, name: string}> $routes
     *
     * @return array{0: WikiTreeRail, 1: RequestStack, 2: WikiPanelStateStoreInterface}
     */
    private function rail(bool $sessionStore = true, array $routes = self::ROUTES): array
    {
        $stack  = new RequestStack();
        $store  = new SessionWikiPanelStateStore($stack);
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $name, array $params): string => '/r/' . $name . '/' . $params['spaceSlug']);
        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('getToken')->willReturnCallback(static fn (string $id): CsrfToken => new CsrfToken($id, 'csrf:' . $id));

        return [new WikiTreeRail($store, $router, $csrf, $routes, $sessionStore), $stack, $store];
    }

    private function request(string $uri, RequestStack $stack): Request
    {
        $this->session ??= new Session(new MockArraySessionStorage());
        $request = Request::create($uri);
        $request->setSession($this->session);
        $stack->pop();
        $stack->push($request);

        return $request;
    }

    private function space(): WikiSpace
    {
        return new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
    }
}
