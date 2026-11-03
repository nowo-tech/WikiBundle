<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Controller;

use Closure;
use Doctrine\ORM\EntityManager;
use Nowo\WikiBundle\Controller\WikiTreeController;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Service\WikiPageMover;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use Nowo\WikiBundle\Tests\Support\WikiControllerContainerBuilder;
use Nowo\WikiBundle\Tree\SessionWikiTreeStateStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

use function in_array;
use function json_decode;
use function json_encode;
use function str_repeat;
use function strtr;

use const JSON_THROW_ON_ERROR;

/**
 * 1.14: the tree endpoints check the user, the origin, the body, the CSRF token and the rights before moving.
 */
final class WikiTreeControllerTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    private WikiPage $ops;

    private WikiPage $run;

    private WikiPage $guide;

    private WikiPage $hidden;

    private WikiPage $plan;

    private WikiPage $secret;

    private SessionWikiTreeStateStore $store;

    private Container $container;

    protected function setUp(): void
    {
        $this->em    = SqliteWikiEntityManager::create();
        $this->space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $this->em->persist($this->space);
        $this->ops    = $this->page('ops', 'Operations');
        $this->run    = $this->page('run', 'Runbooks', $this->ops);
        $this->guide  = $this->page('guide', 'Guide');
        $this->hidden = $this->page('hidden', 'Hidden');
        // DI-L1 / DI-I1: a readable page under a hidden one, and a hidden sibling of Runbooks.
        $this->plan   = $this->page('plan', 'Plan', $this->hidden);
        $this->secret = $this->page('secret', 'Secret', $this->ops);
    }

    public function testMovesAPageAndSaysWhere(): void
    {
        $controller = $this->controller();

        $response = $controller->tree($this->post(['page' => $this->guide->getId(), 'parent' => $this->ops->getId(), 'index' => 0]), 'eng', 'move');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame(['index' => 0, 'count' => 2, 'moved' => true, 'message' => '"Guide" moved under "Operations", position 1 of 2.'], $this->json($response->getContent()));
        self::assertSame($this->ops, $this->guide->getParent());

        $response = $controller->tree($this->post(['page' => $this->run->getId(), 'parent' => null, 'index' => 9]), 'eng', 'move');
        // The hidden root page is not counted (DI-I1).
        self::assertSame('"Runbooks" moved to the top level, position 2 of 2.', $this->json($response->getContent())['message']);

        // A refused move: 422 with the reason.
        $response = $controller->tree($this->post(['page' => $this->ops->getId(), 'parent' => $this->guide->getId(), 'index' => 0]), 'eng', 'move');
        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['error' => 'cycle', 'message' => 'wiki.tree.error.cycle'], $this->json($response->getContent()));
    }

    public function testAPageUnderAHiddenPageIsHiddenToo(): void
    {
        // DI-L1 (audit PoC): Plan is readable, its parent Vault (here: Hidden) is not.
        foreach ([
            ['state', ['page' => $this->plan->getId(), 'expanded' => true]],
            ['move', ['page' => $this->guide->getId(), 'parent' => $this->plan->getId(), 'index' => 0]],
            ['move', ['page' => $this->plan->getId(), 'parent' => null, 'index' => 0]],
        ] as [$action, $body]) {
            $controller = $this->controller();
            try {
                $controller->tree($this->post($body), 'eng', $action);
                self::fail('A page under a hidden page was reachable.');
            } catch (NotFoundHttpException) {
                self::assertNull($this->guide->getParent());
            }
        }
    }

    public function testTheIndexAndTheAnswerCountTheVisibleSiblingsOnly(): void
    {
        $controller = $this->controller();

        // Under Operations: Runbooks (visible) and Secret (hidden). Index 1 = after Runbooks, of 2.
        $response = $controller->tree($this->post(['page' => $this->guide->getId(), 'parent' => $this->ops->getId(), 'index' => 1]), 'eng', 'move');

        self::assertSame(['index' => 1, 'count' => 2, 'moved' => true, 'message' => '"Guide" moved under "Operations", position 2 of 2.'], $this->json($response->getContent()));
        $order = [];
        foreach ((new DoctrineOrmWikiPageRepository($this->em))->findActiveBySpace($this->space) as $page) {
            if ($page->getParent() === $this->ops) {
                $order[] = $page->getSlug();
            }
        }
        self::assertSame(['run', 'guide', 'secret'], $order);
    }

    public function testRecordsFoldChanges(): void
    {
        $controller = $this->controller();
        $user       = new TestUser();

        self::assertSame(204, $controller->tree($this->post(['page' => $this->ops->getId(), 'expanded' => true]), 'eng', 'state')->getStatusCode());
        self::assertSame([$this->ops->getId()], $this->store->expanded($user, $this->space));
        $controller->tree($this->post(['page' => $this->ops->getId(), 'expanded' => false]), 'eng', 'state');
        self::assertSame([], $this->store->expanded($user, $this->space));

        $this->expectException(BadRequestHttpException::class);
        $controller->tree($this->post(['page' => $this->ops->getId(), 'expanded' => 'yes']), 'eng', 'state');
    }

    public function testWithoutTheTreeEveryEndpointIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->controller(enabled: false)->tree($this->post([]), 'eng', 'state');
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string<HttpException>}>
     */
    public static function refusals(): iterable
    {
        yield 'no user' => ['no_user', AccessDeniedHttpException::class];
        yield 'untrusted token' => ['untrusted', AccessDeniedHttpException::class];
        yield 'other origin' => ['origin', AccessDeniedHttpException::class];
        yield 'cross-site fetch' => ['fetch_site', AccessDeniedHttpException::class];
        yield 'not JSON' => ['content_type', UnsupportedMediaTypeHttpException::class];
        yield 'too large' => ['large', BadRequestHttpException::class];
        yield 'invalid JSON' => ['invalid', BadRequestHttpException::class];
        yield 'not an object' => ['scalar', BadRequestHttpException::class];
        yield 'bad token' => ['token', AccessDeniedHttpException::class];
        yield 'missing page' => ['missing', NotFoundHttpException::class];
        yield 'invalid id' => ['bad_id', NotFoundHttpException::class];
        yield 'archived page' => ['archived', NotFoundHttpException::class];
        yield 'page of another space' => ['foreign', NotFoundHttpException::class];
        yield 'unreadable page' => ['unreadable', NotFoundHttpException::class];
        yield 'no edit role' => ['role', AccessDeniedHttpException::class];
        yield 'invalid parent' => ['bad_parent', BadRequestHttpException::class];
        yield 'invalid index' => ['bad_index', BadRequestHttpException::class];
        yield 'unreadable parent' => ['unreadable_parent', NotFoundHttpException::class];
        yield 'no edit right on the new parent' => ['edit_parent', AccessDeniedHttpException::class];
        yield 'no edit right on the old parent' => ['edit_old_parent', AccessDeniedHttpException::class];
    }

    /**
     * @param class-string<HttpException> $exception
     */
    #[DataProvider('refusals')]
    public function testRefusesBeforeMoving(string $case, string $exception): void
    {
        $body                      = ['page' => $this->guide->getId(), 'parent' => $this->ops->getId(), 'index' => 0];
        $deny                      = static fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => true;
        [$user, $guard, $roleEdit] = [true, null, true];
        $request                   = null; // built once the controller (and its CSRF storage) exists
        switch ($case) {
            case 'no_user':
                $user = false;
                break;
            case 'untrusted':
                $guard = new WikiTokenGuard(new RequestStack([new Request()]));
                break;
            case 'origin':
                $request = fn (): Request => $this->post($body, ['HTTP_ORIGIN' => 'https://evil.test']);
                break;
            case 'fetch_site':
                $request = fn (): Request => $this->post($body, ['HTTP_ORIGIN' => null, 'HTTP_SEC_FETCH_SITE' => 'cross-site']);
                break;
            case 'content_type':
                $request = fn (): Request => $this->post($body, ['CONTENT_TYPE' => 'text/plain']);
                break;
            case 'large':
                $request = fn (): Request => $this->post(['page' => str_repeat('x', WikiTreeController::MAX_BODY)]);
                break;
            case 'invalid':
                $request = fn (): Request => $this->raw('{');
                break;
            case 'scalar':
                $request = fn (): Request => $this->raw('"x"');
                break;
            case 'token':
                $request = fn (): Request => $this->post($body, [], 'wrong');
                break;
            case 'missing':
                $body['page'] = 'f00';
                break;
            case 'bad_id':
                $body['page'] = '../x';
                break;
            case 'archived':
                $this->guide->archive();
                $this->em->flush();
                break;
            case 'foreign':
                $other = new WikiSpace('ops', 'Ops', WikiSpaceOwnerScope::Team, 't');
                $this->em->persist($other);
                $body['page'] = $this->page('x', 'X', null, $other)->getId();
                break;
            case 'unreadable':
                $body['page'] = $this->hidden->getId();
                break;
            case 'role':
                $roleEdit = false;
                break;
            case 'bad_parent':
                $body['parent'] = 3;
                break;
            case 'bad_index':
                $body['index'] = '1';
                break;
            case 'unreadable_parent':
                $body['parent'] = $this->hidden->getId();
                break;
            case 'edit_parent':
                $deny = fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => $action !== WikiAction::Edit || $page !== $this->ops;
                break;
            default:
                $body = ['page' => $this->run->getId(), 'parent' => null, 'index' => 0];
                $deny = fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => $action !== WikiAction::Edit || $page !== $this->ops;
        }

        $controller = $this->controller(user: $user, guard: $guard, roleEdit: $roleEdit, decide: $deny);
        $this->expectException($exception);
        $controller->tree($request !== null ? $request() : $this->post($body), 'eng', 'move');
    }

    public function testTheTokenIdIsPerSpace(): void
    {
        self::assertSame('wiki_tree_' . $this->space->getId(), WikiTreeController::csrfTokenId($this->space));
    }

    public function testWithoutTranslatorTheKeysAreAnswered(): void
    {
        $controller = $this->controller(translator: false);

        $response = $controller->tree($this->post(['page' => $this->guide->getId(), 'parent' => null, 'index' => 0]), 'eng', 'move');

        self::assertSame('wiki.tree.moved_root', $this->json($response->getContent())['message']);
    }

    private function page(string $slug, string $title, ?WikiPage $parent = null, ?WikiSpace $space = null): WikiPage
    {
        $page = new WikiPage($space ?? $this->space, $slug, $title, $parent);
        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }

    /**
     * @param (Closure(WikiAction, WikiSpace, ?WikiPage): bool)|null $decide
     */
    private function controller(
        bool $enabled = true,
        bool $user = true,
        ?WikiTokenGuard $guard = null,
        bool $roleEdit = true,
        ?Closure $decide = null,
        bool $translator = true,
    ): WikiTreeController {
        $repository = new DoctrineOrmWikiPageRepository($this->em);
        $denied     = [$this->hidden, $this->secret];
        $decide ??= static fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => true;
        $checker = new CallbackSpaceAccessChecker(static fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => !in_array($page, $denied, true) && $decide($action, $space, $page));
        $roles   = $this->createStub(WikiAccessCheckerInterface::class);
        $roles->method('canEdit')->willReturn($roleEdit);

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $this->store = new SessionWikiTreeStateStore(new RequestStack([$request]));

        $controller = new WikiTreeController(
            new WikiSpaceGuard(new FixedSpaceLocator($this->space), $checker),
            $repository,
            $roles,
            $enabled ? new WikiPageMover($repository, $repository) : null,
            $this->store,
            $guard,
            $translator ? new class implements TranslatorInterface {
                public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
                {
                    return strtr([
                        'wiki.tree.moved_under' => '"%title%" moved under "%parent%", position %position% of %count%.',
                        'wiki.tree.moved_root'  => '"%title%" moved to the top level, position %position% of %count%.',
                    ][$id] ?? $id, $parameters);
                }

                public function getLocale(): string
                {
                    return 'en';
                }
            } : null,
        );
        $this->container = WikiControllerContainerBuilder::bind($controller, $user ? new TestUser() : null);

        return $controller;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string|null> $server
     */
    private function post(array $body, array $server = [], ?string $token = null): Request
    {
        $body['_token'] = $token ?? WikiControllerContainerBuilder::csrfToken($this->container, WikiTreeController::csrfTokenId($this->space));

        return $this->raw(json_encode($body, JSON_THROW_ON_ERROR), $server);
    }

    /**
     * @param array<string, string|null> $server
     */
    private function raw(string $content, array $server = []): Request
    {
        $request = Request::create('http://localhost/', 'POST', [], [], [], $server + ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost'], $content);
        if (($server['HTTP_ORIGIN'] ?? 'set') === null) {
            $request->headers->remove('Origin');
        }

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string|false $content): array
    {
        /* @var array<string, mixed> */
        return json_decode((string) $content, true, 512, JSON_THROW_ON_ERROR);
    }
}
