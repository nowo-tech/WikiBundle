<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Controller;

use Doctrine\ORM\EntityManager;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\AllowlistIconResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\NullPageResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\NullUserResolver;
use Nowo\WikiBundle\Controller\WikiLinkPickerController;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\LinkPicker\ConfiguredWikiLinkPickerSettings;
use Nowo\WikiBundle\LinkPicker\WikiLinkPickerService;
use Nowo\WikiBundle\LinkPicker\WikiTenantPathsLinkTenantResolver;
use Nowo\WikiBundle\Markdown\Milkdown\MilkdownWikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\WikiRenderContext;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use Nowo\WikiBundle\Tests\Support\WikiControllerContainerBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * 1.40 (W2): the picker endpoints — 404 while off, a trusted user, the same origin, `Accept: application/json`, the
 * per-user rate limiter (429 + Retry-After), private no-store answers, and the same answer for missing and hidden.
 */
final class WikiLinkPickerControllerTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    private WikiPage $page;

    protected function setUp(): void
    {
        $this->em    = SqliteWikiEntityManager::create();
        $this->space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 't');
        $this->page  = new WikiPage($this->space, 'guide', 'Install guide');
        $revision    = new WikiPageRevision($this->page, 1, '', null, '## Set up');
        $this->page->setCurrentRevision($revision);
        foreach ([$this->space, $this->page, $revision] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
    }

    public function testCandidatesAndSections(): void
    {
        $controller = $this->controller();

        $candidates = $controller->candidates($this->request('/c?q=install'), 'eng');
        self::assertSame(200, $candidates->getStatusCode());
        self::assertStringContainsString('no-store', (string) $candidates->headers->get('Cache-Control'));
        self::assertStringContainsString('private', (string) $candidates->headers->get('Cache-Control'));
        self::assertSame('Cookie', $candidates->headers->get('Vary'));
        self::assertSame(['here' => [['id' => $this->page->getId(), 'label' => 'Install guide', 'url' => '/wiki/eng/guide']], 'others' => []], $this->json($candidates));

        $sections = $controller->sections($this->request('/s'), 'eng', $this->page->getId());
        self::assertSame(['sections' => [['id' => 'set-up', 'level' => 2, 'text' => 'Set up']]], $this->json($sections));
        self::assertSame('Cookie', $sections->headers->get('Vary'));
    }

    public function testMissingAndHiddenAnswerAlike(): void
    {
        $controller = $this->controller();

        self::assertSame(['here' => [], 'others' => []], $this->json($controller->candidates($this->request('/c?q=install'), 'nope')));
        // A `q[]=` array is no query: the recent pages, never a 400.
        self::assertSame('Install guide', $this->json($controller->candidates($this->request('/c?q[]=x'), 'eng'))['here'][0]['label']);
        $this->expectException(NotFoundHttpException::class);
        $controller->sections($this->request('/s'), 'nope', $this->page->getId());
    }

    public function testAMissingPageIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->controller()->sections($this->request('/s'), 'eng', '00000000-0000-4000-8000-000000000000');
    }

    public function testOffIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->controller(enabled: false)->candidates($this->request('/c'), 'eng');
    }

    public function testAUserIsRequired(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->controller(user: false)->candidates($this->request('/c'), 'eng');
    }

    public function testAnUntrustedTokenIsNoUser(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->controller(trusted: false)->candidates($this->request('/c'), 'eng');
    }

    public function testACrossSiteRequestIsRefused(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->controller()->candidates($this->request('/c', ['HTTP_SEC_FETCH_SITE' => 'cross-site']), 'eng');
    }

    public function testAForeignOriginIsRefused(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->controller()->sections($this->request('/s', ['HTTP_ORIGIN' => 'https://evil.example']), 'eng', $this->page->getId());
    }

    public function testSameOriginHeadersPass(): void
    {
        $response = $this->controller()->candidates($this->request('/c', ['HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_ORIGIN' => 'http://localhost']), 'eng');

        self::assertSame(200, $response->getStatusCode());
    }

    public function testJsonMustBeAccepted(): void
    {
        $response = $this->controller()->candidates($this->request('/c', ['HTTP_ACCEPT' => 'text/html']), 'eng');

        self::assertSame(Response::HTTP_NOT_ACCEPTABLE, $response->getStatusCode());
        self::assertSame(Response::HTTP_NOT_ACCEPTABLE, $this->controller()->sections($this->request('/s', ['HTTP_ACCEPT' => 'text/html']), 'eng', $this->page->getId())->getStatusCode());
    }

    public function testTheRateLimiterAnswers429WithRetryAfter(): void
    {
        $controller = $this->controller(limit: 1);

        self::assertSame(200, $controller->candidates($this->request('/c'), 'eng')->getStatusCode());
        $refused = $controller->candidates($this->request('/c'), 'eng');
        self::assertSame(429, $refused->getStatusCode());
        self::assertGreaterThanOrEqual(1, (int) $refused->headers->get('Retry-After'));
        self::assertSame(429, $controller->sections($this->request('/s'), 'eng', $this->page->getId())->getStatusCode());
    }

    /**
     * @param array<string, string> $server
     */
    private function request(string $uri, array $server = []): Request
    {
        return Request::create($uri, 'GET', [], [], [], $server + ['HTTP_ACCEPT' => 'application/json']);
    }

    private function controller(bool $enabled = true, bool $user = true, bool $trusted = true, int $limit = 100): WikiLinkPickerController
    {
        $guard  = new WikiSpaceGuard(new FixedSpaceLocator($this->space), CallbackSpaceAccessChecker::allowing(WikiAction::View));
        $access = $this->createStub(WikiAccessCheckerInterface::class);
        $access->method('canList')->willReturn(true);
        $spaces = $this->createStub(WikiSpaceAccessResolverInterface::class);
        $spaces->method('listSpacesForUser')->willReturn([$this->space]);
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $name, array $params): string => '/wiki/' . $params['spaceSlug'] . '/' . $params['pageSlug']);
        $picker = new WikiLinkPickerService(
            new DoctrineOrmWikiPageRepository($this->em),
            $guard,
            $access,
            $spaces,
            $urls,
            ['page_view' => ['path' => '/p', 'name' => 'nowo_wiki_page_view']],
            new ConfiguredWikiLinkPickerSettings(),
            new WikiTenantPathsLinkTenantResolver(),
            new MilkdownWikiMarkdownRenderer([], new NullPageResolver(), new NullUserResolver(), new AllowlistIconResolver('/i.svg', [])),
            new WikiHtmlSanitizer(),
            new WikiRenderContext(),
        );
        $limiter    = new RateLimiterFactory(['id' => 'picker', 'policy' => 'fixed_window', 'limit' => $limit, 'interval' => '1 minute'], new InMemoryStorage());
        $controller = new WikiLinkPickerController($guard, $enabled ? $picker : null, $limiter, $trusted ? null : new WikiTokenGuard(new RequestStack([new Request()])));
        WikiControllerContainerBuilder::bind($controller, $user ? new TestUser() : null);

        return $controller;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Response $response): array
    {
        /* @var array<string, mixed> */
        return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
