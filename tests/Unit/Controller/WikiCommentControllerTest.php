<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Controller;

use Doctrine\ORM\EntityManager;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Controller\WikiCommentController;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\History\WikiAuthorLabel;
use Nowo\WikiBundle\Markdown\WikiMarkdownRenderer;
use Nowo\WikiBundle\Panel\SessionWikiPanelStateStore;
use Nowo\WikiBundle\Panel\WikiPanelStateStoreInterface;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiCommentRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use Nowo\WikiBundle\Tests\Support\WikiControllerContainerBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_column;
use function in_array;
use function json_decode;
use function json_encode;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

/**
 * 1.16: the comment endpoints check the user, the page (404), the origin and the CSRF header of writes, the body, and
 * answer the service's refusals as JSON; the panel state endpoint stores the open state.
 */
final class WikiCommentControllerTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    private WikiPage $page;

    private TestUserEntity $user;

    private Container $container;

    /** @var list<WikiAction> */
    private array $granted;

    private SessionWikiPanelStateStore $store;

    protected function setUp(): void
    {
        $this->em    = SqliteWikiEntityManager::create();
        $this->space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $this->page  = new WikiPage($this->space, 'run', 'Runbooks');
        $this->user  = (new TestUserEntity('id-ana'))->withDisplayName('Ana Garcia');
        foreach ([$this->space, $this->page, $this->user] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        $this->granted = [WikiAction::View, ...WikiAction::commentActions()];
    }

    public function testTheJsonEndpoints(): void
    {
        $controller = $this->controller();

        $created = $this->call($controller, 'POST', 'threads', ['anchor' => null, 'body' => 'Hello']);
        self::assertSame(201, $created->getStatusCode());
        self::assertSame('no-store, private', $created->headers->get('Cache-Control'));
        $thread = $this->json($created);
        $id     = $thread['id'];
        $cid    = $thread['comments'][0]['id'];

        self::assertSame([$id], array_column($this->json($this->call($controller, 'GET', 'threads'))['page'], 'id'));
        self::assertSame($id, $this->json($this->call($controller, 'GET', 'threads/' . $id))['id']);
        self::assertSame(201, $this->call($controller, 'POST', 'threads/' . $id . '/comments', ['body' => 'Reply'])->getStatusCode());
        self::assertTrue($this->json($this->call($controller, 'PATCH', 'threads/' . $id, ['resolved' => true]))['resolved']);
        self::assertStringContainsString('Edited', $this->json($this->call($controller, 'PATCH', 'comments/' . $cid, ['body' => 'Edited']))['comments'][0]['html']);
        self::assertSame(1, $this->json($this->call($controller, 'PUT', 'comments/' . $cid . '/reactions/👍'))['comments'][0]['reactions'][0]['count']);
        self::assertSame([], $this->json($this->call($controller, 'DELETE', 'comments/' . $cid . '/reactions/👍'))['comments'][0]['reactions']);
        // 1.16.1: a moderator deletes a whole thread (204).
        $spam = $this->json($this->call($controller, 'POST', 'threads', ['anchor' => null, 'body' => 'Spam']))['id'];
        self::assertSame(204, $this->call($controller, 'DELETE', 'threads/' . $spam)->getStatusCode());
        self::assertSame(404, $this->call($controller, 'GET', 'threads/' . $spam)->getStatusCode());
        $deleted = $this->call($controller, 'DELETE', 'comments/' . $cid);
        self::assertSame(200, $deleted->getStatusCode());
        $last = $this->json($deleted)['comments'][1]['id'];
        $gone = $this->call($controller, 'DELETE', 'comments/' . $last);
        self::assertSame(204, $gone->getStatusCode());
        self::assertSame('no-store, private', $gone->headers->get('Cache-Control'));
    }

    public function testRefusalsAreJson(): void
    {
        $controller    = $this->controller();
        $this->granted = [WikiAction::View, WikiAction::ViewComments];

        $refused = $this->call($controller, 'POST', 'threads', ['anchor' => null, 'body' => 'Hello']);
        self::assertSame(403, $refused->getStatusCode());
        self::assertSame(['error' => 'forbidden'], $this->json($refused));
        self::assertSame(404, $this->call($controller, 'GET', 'threads/missing')->getStatusCode());

        $this->granted[] = WikiAction::Comment;
        self::assertSame(415, $this->call($controller, 'POST', 'threads', null, ['CONTENT_TYPE' => 'text/plain'], 'x')->getStatusCode());
        self::assertSame(413, $this->call($controller, 'POST', 'threads', null, [], '{"body":"' . str_repeat('a', WikiCommentController::MAX_BODY) . '"}')->getStatusCode());
        self::assertSame(['error' => 'invalid_json'], $this->json($this->call($controller, 'POST', 'threads', null, [], '{nope')));
        self::assertSame(422, $this->call($controller, 'POST', 'threads', null, [], '"text"')->getStatusCode());
    }

    public function testWritesNeedTheSameOriginAndTheCsrfHeader(): void
    {
        $controller = $this->controller();

        foreach ([
            ['HTTP_X_CSRF_TOKEN' => 'wrong'],
            ['HTTP_ORIGIN' => 'https://evil.test'],
            ['HTTP_ORIGIN' => null, 'HTTP_SEC_FETCH_SITE' => 'cross-site'],
        ] as $server) {
            try {
                $this->call($controller, 'POST', 'threads', ['body' => 'x'], $server);
                self::fail('A write without the same origin and the CSRF header went through.');
            } catch (AccessDeniedHttpException) {
                self::assertSame([], $this->json($this->call($controller, 'GET', 'threads'))['page']);
            }
        }
        // Reads need neither.
        self::assertSame(200, $this->call($controller, 'GET', 'threads', null, ['HTTP_ORIGIN' => 'https://evil.test', 'HTTP_X_CSRF_TOKEN' => 'wrong'])->getStatusCode());
    }

    public function testUnknownPathsMethodsPagesAndUsers(): void
    {
        $this->expectNotFound(fn (): Response => $this->controller(enabled: false)->comments(Request::create('/'), 'eng', 'run', 'threads'));
        $this->expectNotFound(fn (): Response => $this->controller()->comments(Request::create('/'), 'eng', 'run', 'nope'));
        $this->expectNotFound(fn (): Response => $this->controller()->comments(Request::create('/'), 'eng', 'missing', 'threads'));
        $this->expectNotFound(fn (): Response => $this->controller()->comments(Request::create('/'), 'other', 'run', 'threads'));
        $this->page->archive();
        $this->em->flush();
        $this->expectNotFound(fn (): Response => $this->controller()->comments(Request::create('/'), 'eng', 'run', 'threads'));
        $this->page->unarchive();
        $this->em->flush();
        $this->granted = [];
        $this->expectNotFound(fn (): Response => $this->controller()->comments(Request::create('/'), 'eng', 'run', 'threads'));

        try {
            $this->controller()->comments(Request::create('/', 'PUT'), 'eng', 'run', 'threads');
            self::fail('PUT /threads was accepted.');
        } catch (MethodNotAllowedHttpException $exception) {
            self::assertSame('GET, POST', $exception->getHeaders()['Allow']);
        }

        foreach ([$this->controller(user: false), $this->controller(trusted: false)] as $controller) {
            try {
                $controller->comments(Request::create('/'), 'eng', 'run', 'threads');
                self::fail('An anonymous or untrusted request went through.');
            } catch (AccessDeniedHttpException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTheNoJavaScriptFallback(): void
    {
        $controller = $this->controller();
        $back       = $controller->comments(Request::create('/'), 'eng', 'run', '');
        self::assertSame(303, $back->getStatusCode());
        self::assertSame('/generated/nowo_wiki_page_view#nowo-wiki-comments', $back->headers->get('Location'));

        $token  = WikiControllerContainerBuilder::csrfToken($this->container, WikiCommentService::csrfTokenId($this->page));
        $posted = $controller->comments($this->form(['body' => 'From a form', '_token' => $token]), 'eng', 'run', 'new');
        self::assertSame(303, $posted->getStatusCode());
        self::assertSame(['success' => ['wiki.comments.added']], $this->flashes());
        self::assertCount(1, $this->service()->list($this->user, $this->space, $this->page)['page']);

        $controller->comments($this->form(['body' => '   ', '_token' => $token]), 'eng', 'run', 'new');
        self::assertSame(['warning' => ['wiki.comments.error.invalid']], $this->flashes());
        // With a translator the flash is the translated text.
        $translated = $this->controller(translated: true);
        $translated->comments($this->form(['body' => 'Again', '_token' => WikiControllerContainerBuilder::csrfToken($this->container, WikiCommentService::csrfTokenId($this->page))]), 'eng', 'run', 'new');
        self::assertSame(['success' => ['T:wiki.comments.added']], $this->flashes());

        foreach ([['body' => 'x', '_token' => 'bad'], ['body' => 'x', '_token' => $token, '_origin' => 'https://evil.test']] as $fields) {
            try {
                $controller->comments($this->form($fields), 'eng', 'run', 'new');
                self::fail('A forged fallback post went through.');
            } catch (AccessDeniedHttpException) {
                self::addToAssertionCount(1);
            }
        }

        $this->granted = [WikiAction::View, WikiAction::ViewComments];
        $this->expectException(AccessDeniedHttpException::class);
        $controller->comments($this->form(['body' => 'x', '_token' => $token]), 'eng', 'run', 'new');
    }

    public function testThePanelState(): void
    {
        $controller = $this->controller();
        $token      = WikiControllerContainerBuilder::csrfToken($this->container, WikiCommentController::panelTokenId($this->space));

        $answer = $controller->panelState($this->raw('POST', ['panel' => 'comments', 'open' => true], ['HTTP_X_CSRF_TOKEN' => $token]), 'eng');
        self::assertSame(204, $answer->getStatusCode());
        self::assertTrue($this->store->isOpen($this->user, WikiPanelStateStoreInterface::COMMENTS));
        $controller->panelState($this->raw('POST', ['panel' => 'comments', 'open' => false, '_token' => $token]), 'eng');
        self::assertFalse($this->store->isOpen($this->user, WikiPanelStateStoreInterface::COMMENTS));

        self::assertSame(422, $controller->panelState($this->raw('POST', ['panel' => 'tree', 'open' => true, '_token' => $token]), 'eng')->getStatusCode());
        self::assertSame(422, $controller->panelState($this->raw('POST', ['panel' => 'comments', 'open' => 'yes', '_token' => $token]), 'eng')->getStatusCode());
        self::assertSame(400, $controller->panelState($this->raw('POST', null, [], '{x'), 'eng')->getStatusCode());
        foreach ([['_token' => 'bad'], ['_token' => 5]] as $fields) {
            try {
                $controller->panelState($this->raw('POST', ['panel' => 'comments', 'open' => true] + $fields), 'eng');
                self::fail('A forged state went through.');
            } catch (AccessDeniedHttpException) {
                self::addToAssertionCount(1);
            }
        }
        $this->expectNotFound(fn (): Response => $controller->panelState($this->raw('POST', ['panel' => 'comments', 'open' => true, '_token' => $token]), 'other'));
        $this->expectNotFound(fn (): Response => $this->controller(enabled: false)->panelState($this->raw('POST', []), 'eng'));
        $this->expectException(AccessDeniedHttpException::class);
        $controller->panelState($this->raw('POST', ['panel' => 'comments', 'open' => true, '_token' => $token], ['HTTP_ORIGIN' => 'https://evil.test']), 'eng');
    }

    /** 1.29 web_ui.tree_rail: the endpoint also takes `tree_rail`, alone (no comments) or beside the comments. */
    public function testTheTreeRailState(): void
    {
        // Comments on, rail off: tree_rail is refused.
        $controller = $this->controller();
        $token      = WikiControllerContainerBuilder::csrfToken($this->container, WikiCommentController::panelTokenId($this->space));
        self::assertSame(422, $controller->panelState($this->raw('POST', ['panel' => 'tree_rail', 'open' => true, '_token' => $token]), 'eng')->getStatusCode());

        // Rail only: tree_rail is stored, comments refused.
        $controller = $this->controller(enabled: false, treeRail: true);
        $token      = WikiControllerContainerBuilder::csrfToken($this->container, WikiCommentController::panelTokenId($this->space));
        self::assertSame(204, $controller->panelState($this->raw('POST', ['panel' => 'tree_rail', 'open' => true, '_token' => $token]), 'eng')->getStatusCode());
        self::assertTrue($this->store->isOpen($this->user, WikiPanelStateStoreInterface::TREE_RAIL));
        self::assertFalse($this->store->isOpen($this->user, WikiPanelStateStoreInterface::COMMENTS));
        self::assertSame(422, $controller->panelState($this->raw('POST', ['panel' => 'comments', 'open' => true, '_token' => $token]), 'eng')->getStatusCode());
        self::assertSame(422, $controller->panelState($this->raw('POST', ['panel' => ['tree_rail'], 'open' => true, '_token' => $token]), 'eng')->getStatusCode());

        // Both: each is its own flag.
        $controller = $this->controller(treeRail: true);
        $token      = WikiControllerContainerBuilder::csrfToken($this->container, WikiCommentController::panelTokenId($this->space));
        $controller->panelState($this->raw('POST', ['panel' => 'comments', 'open' => true, '_token' => $token]), 'eng');
        self::assertTrue($this->store->isOpen($this->user, WikiPanelStateStoreInterface::COMMENTS));
        self::assertFalse($this->store->isOpen($this->user, WikiPanelStateStoreInterface::TREE_RAIL));

        // A forged token is refused for the rail as well.
        $this->expectException(AccessDeniedHttpException::class);
        $controller->panelState($this->raw('POST', ['panel' => 'tree_rail', 'open' => true, '_token' => 'bad']), 'eng');
    }

    private function controller(bool $enabled = true, bool $user = true, bool $trusted = true, bool $translated = false, bool $treeRail = false): WikiCommentController
    {
        $guard = new WikiSpaceGuard(new FixedSpaceLocator($this->space), new CallbackSpaceAccessChecker(fn (WikiAction $action): bool => in_array($action, $this->granted, true)));
        // An untrusted token: a main request that went through no secured firewall.
        $tokenGuard = $trusted ? null : new WikiTokenGuard(new RequestStack([new Request()]));
        $session    = new Session(new MockArraySessionStorage());
        $stack      = new RequestStack();
        $request    = Request::create('/');
        $request->setSession($session);
        $stack->push($request);
        $this->store = new SessionWikiPanelStateStore($stack);
        $controller  = new WikiCommentController(
            $guard,
            new DoctrineOrmWikiPageRepository($this->em),
            ['page_view' => ['path' => '/p', 'name' => 'nowo_wiki_page_view']],
            $enabled ? $this->service($guard) : null,
            $enabled || $treeRail ? $this->store : null,
            $tokenGuard,
            $translated ? new class implements TranslatorInterface {
                public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
                {
                    return 'T:' . $id;
                }

                public function getLocale(): string
                {
                    return 'en';
                }
            } : null,
            ['tree_rail' => $treeRail],
        );
        $this->container = WikiControllerContainerBuilder::bind($controller, $user ? $this->user : null);

        return $controller;
    }

    private function service(?WikiSpaceGuard $guard = null): WikiCommentService
    {
        $guard ??= new WikiSpaceGuard(new FixedSpaceLocator($this->space), new CallbackSpaceAccessChecker(fn (WikiAction $action): bool => in_array($action, $this->granted, true)));

        return new WikiCommentService(new DoctrineOrmWikiCommentRepository($this->em), $guard, new WikiAuthorLabel('secret', true), new WikiMarkdownRenderer(), new WikiHtmlSanitizer());
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string|null> $server
     */
    private function call(WikiCommentController $controller, string $method, string $path, ?array $body = null, array $server = [], ?string $content = null): Response
    {
        $server += ['HTTP_X_CSRF_TOKEN' => WikiControllerContainerBuilder::csrfToken($this->container, WikiCommentService::csrfTokenId($this->page))];

        return $controller->comments($this->raw($method, $body, $server, $content), 'eng', 'run', $path);
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string|null> $server
     */
    private function raw(string $method, ?array $body, array $server = [], ?string $content = null): Request
    {
        $request = Request::create('http://localhost/', $method, [], [], [], $server + ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost'], $content ?? ($body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR)));
        if (($server['HTTP_ORIGIN'] ?? 'set') === null) {
            $request->headers->remove('Origin');
        }

        return $request;
    }

    /**
     * @param array<string, string> $fields
     */
    private function form(array $fields): Request
    {
        $origin = $fields['_origin'] ?? 'http://localhost';
        unset($fields['_origin']);

        return Request::create('http://localhost/', 'POST', $fields, [], [], ['HTTP_ORIGIN' => $origin]);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Response $response): array
    {
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function flashes(): array
    {
        /** @var RequestStack $stack */
        $stack = $this->container->get('request_stack');

        return $stack->getSession()->getFlashBag()->all();
    }

    private function expectNotFound(callable $call): void
    {
        try {
            $call();
            self::fail('Expected a 404.');
        } catch (NotFoundHttpException) {
            self::addToAssertionCount(1);
        }
    }
}
