<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Controller;

use Nowo\WikiBundle\Collaboration\WikiCollabPublisherInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Nowo\WikiBundle\Collaboration\WikiCollabSubscriberAuthorizerInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabTopicsAuthorizerInterface;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Controller\WikiCollabController;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\History\WikiAuthorLabel;
use Nowo\WikiBundle\Markdown\WikiMarkdownRenderer;
use Nowo\WikiBundle\Repository\WikiCommentRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\InMemoryCollabStateStore;
use Nowo\WikiBundle\Tests\Support\WikiControllerContainerBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * 1.13: every collaboration endpoint checks the edit permission, the method, the origin and the payload.
 */
final class WikiCollabControllerTest extends TestCase
{
    private WikiSpace $space;

    private WikiPage $page;

    private WikiPage $archived;

    /** @var list<string> */
    private array $published = [];

    /** @var list<list<string>|string> */
    private array $authorized = [];

    protected function setUp(): void
    {
        $this->space    = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $this->page     = new WikiPage($this->space, 'runbook', 'Runbook');
        $this->archived = new WikiPage($this->space, 'old', 'Old');
        $this->archived->archive();
    }

    public function testEditorsGetTheTokenTheStateAndRelayUpdates(): void
    {
        $controller = $this->controller();

        $token = $controller->collab($this->post(''), 'eng', 'runbook', 'token');
        self::assertSame(Response::HTTP_NO_CONTENT, $token->getStatusCode());
        self::assertSame('no-store, private', $token->headers->get('Cache-Control'));
        self::assertSame(['urn:nowo-wiki:page:' . $this->page->getId()], $this->authorized);

        $state = $controller->collab(Request::create('/', 'GET'), 'eng', 'runbook', 'state');
        self::assertSame(['state' => 'AAA=', 'updates' => [], 'seq' => 0, 'compact' => false, 'seed' => true], json_decode((string) $state->getContent(), true, 512, JSON_THROW_ON_ERROR));

        self::assertSame(204, $controller->collab($this->post('{"c":1,"d":"AQ=="}'), 'eng', 'runbook', 'update')->getStatusCode());
        self::assertSame(204, $controller->collab($this->post('{"c":1,"d":"Ag=="}'), 'eng', 'runbook', 'awareness')->getStatusCode());
        self::assertSame(204, $controller->collab($this->post('{"c":1,"d":"Aw==","s":1}'), 'eng', 'runbook', 'compact')->getStatusCode());
        self::assertSame(['{"t":"u","c":1,"d":"AQ=="}', '{"t":"a","c":1,"d":"Ag=="}'], $this->published);

        $invalid = $controller->collab($this->post('{"c":1}'), 'eng', 'runbook', 'update');
        self::assertSame(400, $invalid->getStatusCode());
        self::assertSame('{"error":"invalid_payload"}', $invalid->getContent());
    }

    public function testViewersOfCommentsAlsoGetTheCommentsTopic(): void
    {
        // 1.16: one grant for the co-editing topic and the private comments topic (WikiCollabTopicsAuthorizerInterface).
        $topic      = 'urn:nowo-wiki:page:' . $this->page->getId();
        $controller = $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::Edit, WikiAction::ViewComments), comments: true, topics: true);
        $controller->collab($this->post(''), 'eng', 'runbook', 'token');
        self::assertSame([[$topic, $topic . '/comments']], $this->authorized);

        // Without ViewComments, or with an authorizer that grants one topic: only the co-editing topic.
        $this->authorized = [];
        $this->controller(comments: true, topics: true)->collab($this->post(''), 'eng', 'runbook', 'token');
        $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::Edit, WikiAction::ViewComments), comments: true)->collab($this->post(''), 'eng', 'runbook', 'token');
        $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::Edit, WikiAction::ViewComments), comments: true, collabTopic: false)->collab($this->post(''), 'eng', 'runbook', 'token');
        self::assertSame([[$topic], $topic, $topic], $this->authorized);
    }

    public function testCorruptReportsAreRelayedAsResets(): void
    {
        $controller = $this->controller();
        $controller->collab($this->post('{"c":1,"d":"AQ=="}'), 'eng', 'runbook', 'update');

        self::assertSame(204, $controller->collab($this->post('{"c":1,"s":1}'), 'eng', 'runbook', 'corrupt')->getStatusCode());
        self::assertSame('{"t":"r","c":0,"d":""}', $this->published[1]);
    }

    public function testTheManualResetChecksTheCsrfTokenAndRedirectsToTheEditor(): void
    {
        [$controller, $container] = $this->controllerWithContainer(['page_edit' => ['path' => '/e', 'name' => 'nowo_wiki_page_edit']]);
        $controller->collab($this->post('{"c":1,"d":"AQ=="}'), 'eng', 'runbook', 'update');

        $request  = Request::create('http://localhost/', 'POST', ['_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_collab_reset_' . $this->page->getId())], [], [], ['HTTP_ORIGIN' => 'http://localhost']);
        $response = $controller->collab($request, 'eng', 'runbook', 'reset');
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('{"t":"r","c":0,"d":""}', $this->published[1]);

        $again = Request::create('http://localhost/', 'POST', ['_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_collab_reset_' . $this->page->getId())], [], [], ['HTTP_ORIGIN' => 'http://localhost']);
        self::assertSame(302, $controller->collab($again, 'eng', 'runbook', 'reset')->getStatusCode());
        self::assertCount(2, $this->published, 'an empty log is not broadcast again');

        [$bare, $bareContainer] = $this->controllerWithContainer();
        $request                = Request::create('http://localhost/', 'POST', ['_token' => WikiControllerContainerBuilder::csrfToken($bareContainer, 'wiki_collab_reset_' . $this->page->getId())], [], [], ['HTTP_ORIGIN' => 'http://localhost']);
        self::assertSame(204, $bare->collab($request, 'eng', 'runbook', 'reset')->getStatusCode());

        $this->expectException(AccessDeniedHttpException::class);
        $controller->collab(Request::create('http://localhost/', 'POST', ['_token' => 'forged'], [], [], ['HTTP_ORIGIN' => 'http://localhost']), 'eng', 'runbook', 'reset');
    }

    public function testCappedManualResetsStillGoBackToTheEditor(): void
    {
        [$controller, $container] = $this->controllerWithContainer(['page_edit' => ['path' => '/e', 'name' => 'nowo_wiki_page_edit']]);
        for ($i = 0; $i < 4; ++$i) {
            $controller->collab($this->post('{"c":1,"d":"AQ=="}'), 'eng', 'runbook', 'update');
            $request = Request::create('http://localhost/', 'POST', ['_token' => WikiControllerContainerBuilder::csrfToken($container, 'wiki_collab_reset_' . $this->page->getId())], [], [], ['HTTP_ORIGIN' => 'http://localhost']);
            self::assertSame(302, $controller->collab($request, 'eng', 'runbook', 'reset')->getStatusCode());
        }

        self::assertCount(3, array_filter($this->published, static fn (string $m): bool => $m === '{"t":"r","c":0,"d":""}'), 'CW-L3: capped at corrupt_resets');
    }

    public function testReadersCannotResetTheSession(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View))->collab(Request::create('http://localhost/', 'POST', ['_token' => 'x'], [], [], ['HTTP_ORIGIN' => 'http://localhost']), 'eng', 'runbook', 'reset');
    }

    public function testRequestsWithoutAnOriginHeaderNeedASameOriginFetch(): void
    {
        $request = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_SEC_FETCH_SITE' => 'same-origin'], '{"c":1,"d":"AQ=="}');
        self::assertSame(204, $this->controller()->collab($request, 'eng', 'runbook', 'update')->getStatusCode());

        $this->expectException(AccessDeniedHttpException::class);
        $request = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_SEC_FETCH_SITE' => 'cross-site'], '{"c":1,"d":"AQ=="}');
        $this->controller()->collab($request, 'eng', 'runbook', 'update');
    }

    public function testCrossOriginPostsAreRefused(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $request = Request::create('http://localhost/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'https://evil.test'], '{"c":1,"d":"AQ=="}');
        $this->controller()->collab($request, 'eng', 'runbook', 'update');
    }

    public function testReadersCannotSubscribeOrPost(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->controller(CallbackSpaceAccessChecker::allowing(WikiAction::View))->collab($this->post(''), 'eng', 'runbook', 'token');
    }

    public function testTheRoleGateIsChecked(): void
    {
        $roles = $this->createStub(WikiAccessCheckerInterface::class);
        $roles->method('canEdit')->willReturn(false);

        $this->expectException(AccessDeniedHttpException::class);
        $this->controller(roles: $roles)->collab(Request::create('/', 'GET'), 'eng', 'runbook', 'state');
    }

    public function testHiddenMissingAndArchivedPagesAre404(): void
    {
        foreach ([[CallbackSpaceAccessChecker::allowing(WikiAction::Edit), 'runbook'], [null, 'missing'], [null, 'old']] as [$checker, $slug]) {
            try {
                $this->controller($checker)->collab(Request::create('/', 'GET'), 'eng', $slug, 'state');
                self::fail('reachable');
            } catch (NotFoundHttpException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testDisabledCollaborationAndUnknownEndpointsAre404(): void
    {
        foreach ([[false, 'state'], [true, 'nope']] as [$enabled, $action]) {
            try {
                $this->controller(enabled: $enabled)->collab(Request::create('/', 'GET'), 'eng', 'runbook', $action);
                self::fail('reachable');
            } catch (NotFoundHttpException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testTheMethodMustMatchTheEndpoint(): void
    {
        $this->expectException(MethodNotAllowedHttpException::class);
        $this->controller()->collab(Request::create('/', 'GET'), 'eng', 'runbook', 'update');
    }

    public function testAPageUnderAHiddenParentIsNotFound(): void
    {
        // 1.13.2: the page is readable and editable, its parent is not readable.
        $vault      = new WikiPage($this->space, 'vault', 'Vault');
        $this->page = new WikiPage($this->space, 'runbook', 'Runbook', $vault);

        $this->expectException(NotFoundHttpException::class);
        $this->controller(new CallbackSpaceAccessChecker(static fn (WikiAction $a, WikiSpace $s, ?WikiPage $p): bool => $p !== $vault))
            ->collab(Request::create('/', 'GET'), 'eng', 'runbook', 'state');
    }

    public function testAnonymousAndUntrustedTokensAreRefused(): void
    {
        foreach ([[false, null], [true, new WikiTokenGuard(new RequestStack([new Request()]))]] as [$user, $guard]) {
            try {
                $this->controller(user: $user, guard: $guard)->collab(Request::create('/', 'GET'), 'eng', 'runbook', 'state');
                self::fail('reachable');
            } catch (AccessDeniedHttpException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function post(string $content): Request
    {
        return Request::create('http://localhost/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost'], $content);
    }

    /**
     * @param array<string, array{path: string, name: string}> $routes
     *
     * @return array{0: WikiCollabController, 1: Container}
     */
    private function controllerWithContainer(array $routes = []): array
    {
        $controller = $this->controller(routes: $routes);

        return [$controller, WikiControllerContainerBuilder::bind($controller, new TestUser())];
    }

    /**
     * @param array<string, array{path: string, name: string}> $routes
     */
    private function controller(
        ?CallbackSpaceAccessChecker $checker = null,
        bool $enabled = true,
        bool $user = true,
        ?WikiTokenGuard $guard = null,
        ?WikiAccessCheckerInterface $roles = null,
        array $routes = [],
        bool $comments = false,
        bool $topics = false,
        bool $collabTopic = true,
    ): WikiCollabController {
        $pages = $this->createStub(WikiPageRepositoryInterface::class);
        $pages->method('findBySlug')->willReturnCallback(fn (WikiSpace $space, string $slug): ?WikiPage => ['runbook' => $this->page, 'old' => $this->archived][$slug] ?? null);
        if (!$roles instanceof WikiAccessCheckerInterface) {
            $roles = $this->createStub(WikiAccessCheckerInterface::class);
            $roles->method('canEdit')->willReturn(true);
        }

        $publisher = new class($this->published) implements WikiCollabPublisherInterface {
            /** @param list<string> $published */
            public function __construct(private array &$published)
            {
            }

            public function publish(string $topic, string $message): void
            {
                $this->published[] = $message;
            }
        };
        $authorizer = new class($this->authorized) implements WikiCollabSubscriberAuthorizerInterface {
            /** @param list<string> $authorized */
            public function __construct(private array &$authorized)
            {
            }

            public function authorize(Request $request, string $topic): void
            {
                $this->authorized[] = $topic;
            }
        };

        if ($topics) {
            $authorizer = new class($this->authorized) implements WikiCollabTopicsAuthorizerInterface {
                /** @param list<list<string>|string> $authorized */
                public function __construct(private array &$authorized)
                {
                }

                public function authorize(Request $request, string $topic): void
                {
                    $this->authorized[] = [$topic];
                }

                public function authorizeTopics(Request $request, array $topics): void
                {
                    $this->authorized[] = $topics;
                }
            };
        }
        $spaceGuard = new WikiSpaceGuard(new FixedSpaceLocator($this->space), $checker ?? CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::Edit));
        $collab     = new WikiCollabService(new InMemoryCollabStateStore(), $publisher, $authorizer, secret: 'test-secret');
        $controller = new WikiCollabController(
            $spaceGuard,
            $pages,
            $roles,
            $enabled ? $collab : null,
            $guard,
            $routes,
            $comments ? new WikiCommentService($this->createStub(WikiCommentRepositoryInterface::class), $spaceGuard, new WikiAuthorLabel('s'), new WikiMarkdownRenderer(), new WikiHtmlSanitizer(), collab: $collabTopic ? $collab : null) : null,
        );
        WikiControllerContainerBuilder::bind($controller, $user ? new TestUser() : null);

        return $controller;
    }
}
