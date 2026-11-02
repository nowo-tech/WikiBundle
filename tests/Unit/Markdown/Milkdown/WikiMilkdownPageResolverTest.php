<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Markdown\Milkdown;

use Nowo\MilkdownEditorBundle\Markdown\Resolver\ResolvedPage;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownPageResolver;
use Nowo\WikiBundle\Repository\WikiPageBatchFinderInterface;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_keys;

final class WikiMilkdownPageResolverTest extends TestCase
{
    private WikiSpace $space;

    private WikiPage $parent;

    private WikiPage $child;

    private WikiPage $hidden;

    private WikiPage $underHidden;

    private WikiPage $archived;

    protected function setUp(): void
    {
        $this->space       = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->parent      = new WikiPage($this->space, 'guide', 'Guide');
        $this->child       = new WikiPage($this->space, 'install', 'Install', $this->parent);
        $this->hidden      = new WikiPage($this->space, 'secret', 'Secret');
        $this->underHidden = new WikiPage($this->space, 'deeper', 'Deeper', $this->hidden);
        $this->archived    = new WikiPage($this->space, 'old', 'Old');
        $this->archived->archive();
    }

    public function testResolvesReadablePagesWithTheirUrlInOneBatch(): void
    {
        $repository = $this->createMockForIntersectionOfInterfaces([WikiPageRepositoryInterface::class, WikiPageBatchFinderInterface::class]);
        $repository->expects(self::once())->method('findByIds')->willReturn($this->allPages());
        $repository->expects(self::never())->method('findById');

        $resolved = $this->resolver($repository)->resolvePages($this->allIds());

        self::assertSame([$this->parent->getId(), $this->child->getId()], array_keys($resolved));
        self::assertEquals(new ResolvedPage('Guide', '/wiki/eng/guide'), $resolved[$this->parent->getId()]);
        self::assertEquals(new ResolvedPage('Install', '/wiki/eng/install'), $resolved[$this->child->getId()]);
    }

    public function testTheSpaceIsDecidedOncePerDocument(): void
    {
        $pages = [];
        for ($i = 0; $i < 50; ++$i) {
            $pages[] = new WikiPage($this->space, 'p' . $i, 'P' . $i);
        }

        $repository = $this->createMockForIntersectionOfInterfaces([WikiPageRepositoryInterface::class, WikiPageBatchFinderInterface::class]);
        $repository->method('findByIds')->willReturn($pages);
        $calls  = ['space' => 0, 'page' => 0];
        $access = $this->createStub(WikiSpaceAccessCheckerInterface::class);
        $access->method('isGranted')->willReturnCallback(static function (UserInterface $user, WikiAction $action, WikiSpace $space, ?WikiPage $page = null) use (&$calls): bool {
            ++$calls[$page instanceof WikiPage ? 'page' : 'space'];

            return true;
        });
        $checker = $this->createStub(WikiAccessCheckerInterface::class);
        $checker->method('canList')->willReturn(true);
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken(new TestUser(), 'main', ['ROLE_USER']));

        $resolved = (new WikiMilkdownPageResolver($repository, $access, $checker, $tokens))->resolvePages(['x']);

        self::assertCount(50, $resolved);
        self::assertSame(['space' => 1, 'page' => 50], $calls);
    }

    public function testReadsOneIdAtATimeWithoutABatchFinder(): void
    {
        $repository = $this->createMock(WikiPageRepositoryInterface::class);
        $pages      = [];
        foreach ($this->allPages() as $page) {
            $pages[$page->getId()] = $page;
        }

        $repository->method('findById')->willReturnCallback(static fn (string $id): ?WikiPage => $pages[$id] ?? null);

        $resolved = $this->resolver($repository)->resolvePages([...$this->allIds(), 'missing']);

        self::assertSame([$this->parent->getId(), $this->child->getId()], array_keys($resolved));
    }

    public function testNothingResolvesWithoutAReader(): void
    {
        $repository = $this->createMock(WikiPageRepositoryInterface::class);
        $repository->expects(self::never())->method('findById');

        self::assertSame([], $this->resolver($repository, user: null)->resolvePages($this->allIds()));
        self::assertSame([], $this->resolver($repository, canList: false)->resolvePages($this->allIds()));
        self::assertSame([], $this->resolver($repository)->resolvePages([]));

        $untrusted = new WikiTokenGuard($this->requestStack(new Request()));
        self::assertSame([], $this->resolver($repository, guard: $untrusted)->resolvePages($this->allIds()));
    }

    public function testTitlesWithoutUrlWhenThereIsNoRoute(): void
    {
        $repository = $this->createMock(WikiPageRepositoryInterface::class);
        $repository->method('findById')->willReturn($this->parent);
        $trusted = new Request();
        $trusted->attributes->set('_firewall_context', 'security.firewall.map.context.main');

        self::assertEquals(['x' => new ResolvedPage('Guide', '')], $this->resolverWithoutIds($repository, routes: [], guard: new WikiTokenGuard($this->requestStack($trusted))));
        self::assertEquals(['x' => new ResolvedPage('Guide', '')], $this->resolverWithoutIds($repository, generator: false));
        self::assertEquals(['x' => new ResolvedPage('Guide', '')], $this->resolverWithoutIds($repository, failingGenerator: true));
    }

    /**
     * @return list<WikiPage>
     */
    private function allPages(): array
    {
        return [$this->parent, $this->child, $this->hidden, $this->underHidden, $this->archived];
    }

    /**
     * @return list<string>
     */
    private function allIds(): array
    {
        $ids = [];
        foreach ($this->allPages() as $page) {
            $ids[] = $page->getId();
        }

        return $ids;
    }

    /**
     * @param array<string, array{name: string}>|null $routes
     *
     * @return array<string, ResolvedPage>
     */
    private function resolverWithoutIds(WikiPageRepositoryInterface $repository, ?array $routes = null, bool $generator = true, bool $failingGenerator = false, ?WikiTokenGuard $guard = null): array
    {
        $resolved = $this->resolver($repository, routes: $routes, generator: $generator, failingGenerator: $failingGenerator, guard: $guard)->resolvePages(['p']);

        return ['x' => $resolved[$this->parent->getId()]];
    }

    /**
     * @param array<string, array{name: string}>|null $routes
     */
    private function resolver(
        WikiPageRepositoryInterface $repository,
        ?UserInterface $user = new TestUser(),
        bool $canList = true,
        ?array $routes = null,
        bool $generator = true,
        bool $failingGenerator = false,
        ?WikiTokenGuard $guard = null,
    ): WikiMilkdownPageResolver {
        $hiddenId = $this->hidden->getId();
        $access   = $this->createStub(WikiSpaceAccessCheckerInterface::class);
        $access->method('isGranted')->willReturnCallback(static fn (UserInterface $user, WikiAction $action, WikiSpace $space, ?WikiPage $page = null): bool => $action === WikiAction::View && $page?->getId() !== $hiddenId);
        $checker = $this->createStub(WikiAccessCheckerInterface::class);
        $checker->method('canList')->willReturn($canList);
        $tokens = new TokenStorage();
        if ($user instanceof UserInterface) {
            $tokens->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        }

        $urls = $this->createStub(UrlGeneratorInterface::class);
        if ($failingGenerator) {
            $urls->method('generate')->willThrowException(new RouteNotFoundException('none'));
        } else {
            $urls->method('generate')->willReturnCallback(static fn (string $name, array $params): string => '/wiki/' . $params['spaceSlug'] . '/' . $params['pageSlug']);
        }

        return new WikiMilkdownPageResolver(
            $repository,
            $access,
            $checker,
            $tokens,
            $generator ? $urls : null,
            $routes ?? ['page_view' => ['name' => 'nowo_wiki_page_view']],
            $guard,
        );
    }

    private function requestStack(Request $request): RequestStack
    {
        $stack = new RequestStack();
        $stack->push($request);

        return $stack;
    }
}
