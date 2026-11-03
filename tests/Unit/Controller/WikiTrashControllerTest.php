<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Controller;

use Closure;
use Doctrine\ORM\EntityManager;
use Nowo\UiKitBundle\View\ShowPage;
use Nowo\WikiBundle\Controller\WikiTrashController;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiTrashRepository;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\PageFixtures;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use Nowo\WikiBundle\Tests\Support\WikiControllerContainerBuilder;
use Nowo\WikiBundle\Trash\WikiTrashService;
use Nowo\WikiBundle\Ui\WikiPageFactory;
use Nowo\WikiBundle\Ui\WikiPages;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

use function array_column;

/**
 * 1.14 (T065): Delete, the trash list, Restore and the two-step Purge check the user, the rights and the token.
 */
final class WikiTrashControllerTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    private WikiPage $ops;

    private WikiPage $run;

    private WikiPage $vault;

    private WikiPage $plan;

    private WikiTrashService $trash;

    private object $twig;

    private Container $container;

    protected function setUp(): void
    {
        $this->em    = SqliteWikiEntityManager::create();
        $this->space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $this->em->persist($this->space);
        $this->ops   = $this->page('ops', 'Operations');
        $this->run   = $this->page('run', 'Runbooks', $this->ops);
        $this->vault = $this->page('vault', 'Vault');
        $this->plan  = $this->page('plan', 'Plan', $this->vault);
        $pages       = new DoctrineOrmWikiPageRepository($this->em);
        $this->trash = new WikiTrashService($pages, new DoctrineOrmWikiTrashRepository($this->em), $pages, new EventDispatcher(), retentionDays: 14);
        $this->twig  = new class {
            /** @var array<string, mixed> */
            public array $context = [];

            /** @param array<string, mixed> $context */
            public function render(string $name, array $context = []): string
            {
                $this->context = $context;

                return $name;
            }
        };
    }

    public function testDeleteMovesThePageAndItsSubPagesToTheTrash(): void
    {
        $controller = $this->controller();

        $response = $controller->delete($this->post(WikiTrashController::deleteTokenId($this->ops)), 'eng', 'ops');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertTrue($this->ops->isArchived() && $this->run->isArchived());
        self::assertSame(['wiki.trash.deleted', ['%title%' => 'Operations']], $this->flash());

        // An archived (trashed) page is not there any more.
        $this->assertThrows(NotFoundHttpException::class, fn (): RedirectResponse => $controller->delete($this->post(WikiTrashController::deleteTokenId($this->ops)), 'eng', 'ops'));
        $this->assertThrows(NotFoundHttpException::class, fn (): RedirectResponse => $controller->delete($this->post('x'), 'eng', 'nope'));
    }

    public function testDeleteRefusals(): void
    {
        // A page under a hidden page reads as missing; no Delete right; a bad token.
        $this->assertThrows(NotFoundHttpException::class, fn (): RedirectResponse => $this->controller(decide: fn (WikiAction $a, WikiSpace $s, ?WikiPage $p): bool => $p !== $this->vault)->delete($this->post(WikiTrashController::deleteTokenId($this->plan)), 'eng', 'plan'));
        $this->assertThrows(AccessDeniedHttpException::class, fn (): RedirectResponse => $this->controller(decide: static fn (WikiAction $a): bool => $a !== WikiAction::Delete)->delete($this->post(WikiTrashController::deleteTokenId($this->ops)), 'eng', 'ops'));
        $this->assertThrows(AccessDeniedHttpException::class, fn (): RedirectResponse => $this->controller()->delete($this->post('wrong-token-id'), 'eng', 'ops'));
        self::assertFalse($this->ops->isArchived());
    }

    public function testTheTrashListsReadableDeletionsWithRestoreAndPurge(): void
    {
        $this->trash->delete($this->ops);
        $this->trash->delete($this->plan);
        $controller = $this->controller(decide: fn (WikiAction $a, WikiSpace $s, ?WikiPage $p): bool => $p !== $this->vault);

        $controller->index('eng');

        self::assertInstanceOf(ShowPage::class, $this->twig->context['page']);
        self::assertSame(['Operations'], array_column($this->twig->context['items'], 'title'));
        self::assertSame(2, $this->twig->context['items'][0]['pages']);
        self::assertSame(['wiki-trash-restore', 'wiki-trash-purge'], array_map(static fn ($a) => $a->attributes['data-testid'], $this->twig->context['items'][0]['actions']));

        // Without Purge: Restore only.
        $this->controller(decide: static fn (WikiAction $a): bool => $a !== WikiAction::Purge)->index('eng');
        self::assertCount(1, $this->twig->context['items'][0]['actions']);

        $this->assertThrows(AccessDeniedHttpException::class, fn (): Response => $this->controller(decide: static fn (WikiAction $a): bool => $a !== WikiAction::Delete)->index('eng'));
    }

    public function testRestoreBringsTheDeletionBackAndShowsThePage(): void
    {
        $batch      = $this->trash->delete($this->ops);
        $controller = $this->controller();

        $this->assertThrows(AccessDeniedHttpException::class, fn (): RedirectResponse => $controller->restore($this->post('wrong'), 'eng', $batch));
        $response = $controller->restore($this->post('wiki_trash_restore_' . $batch), 'eng', $batch);

        self::assertSame('/nowo_wiki_page_view?spaceSlug=eng&pageSlug=ops', $response->getTargetUrl());
        self::assertFalse($this->ops->isArchived() || $this->run->isArchived());
        self::assertSame(['wiki.trash.restored', ['%title%' => 'Operations']], $this->flash());

        // Gone from the trash (and any unknown or malformed id): 404.
        $this->assertThrows(NotFoundHttpException::class, fn (): RedirectResponse => $controller->restore($this->post('wiki_trash_restore_' . $batch), 'eng', $batch));
        $this->assertThrows(NotFoundHttpException::class, fn (): RedirectResponse => $controller->restore($this->post('x'), 'eng', 'not-a-uuid'));
    }

    public function testWithoutAPageViewRouteRestoreGoesBackToTheTrash(): void
    {
        $batch = $this->trash->delete($this->ops);

        $response = $this->controller(routes: ['space_trash' => ['path' => '/t', 'name' => 'nowo_wiki_space_trash']])
            ->restore($this->post('wiki_trash_restore_' . $batch), 'eng', $batch);

        self::assertStringStartsWith('/nowo_wiki_space_trash', $response->getTargetUrl());
    }

    public function testPurgeAsksASecondConfirmationThenDeletesForGood(): void
    {
        $batch      = $this->trash->delete($this->vault);
        $controller = $this->controller(decide: fn (WikiAction $a, WikiSpace $s, ?WikiPage $p): bool => $p !== $this->plan);

        $controller->purge(Request::create('/'), 'eng', $batch);
        // Two pages, but only the readable one is named.
        self::assertSame(['Vault'], $this->twig->context['titles']);
        self::assertInstanceOf(ShowPage::class, $this->twig->context['page']);

        $this->assertThrows(AccessDeniedHttpException::class, fn (): Response => $controller->purge($this->post('wrong', 'POST'), 'eng', $batch));
        $response = $controller->purge($this->post('wiki_trash_purge_' . $batch), 'eng', $batch);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(['wiki.trash.purged', ['%title%' => 'Vault', '%count%' => 2]], $this->flash());
        $this->em->clear();
        self::assertNull($this->em->find(WikiPage::class, $this->vault->getId()));

        $ops = $this->em->find(WikiPage::class, $this->ops->getId());
        self::assertInstanceOf(WikiPage::class, $ops);
        $other = $this->trash->delete($ops);
        $this->assertThrows(AccessDeniedHttpException::class, fn (): Response => $this->controller(decide: static fn (WikiAction $a): bool => $a !== WikiAction::Purge)->purge(Request::create('/'), 'eng', $other));
    }

    public function testHiddenPagesAreNotCountedAndNeedAManagerToBePurged(): void
    {
        $batch = $this->trash->delete($this->vault);
        // Plan (under Vault) is hidden from this user, who is not a space manager.
        $decide = fn (WikiAction $a, WikiSpace $s, ?WikiPage $p): bool => $p !== $this->plan && $a !== WikiAction::Manage;

        $this->controller(decide: $decide)->index('eng');
        self::assertSame(1, $this->twig->context['items'][0]['pages']);

        foreach (['GET', 'POST'] as $method) {
            $controller = $this->controller(decide: $decide);
            $response   = $controller->purge($this->post('wiki_trash_purge_' . $batch, $method), 'eng', $batch);
            self::assertInstanceOf(RedirectResponse::class, $response);
            /** @var RequestStack $stack */
            $stack = $this->container->get('request_stack');
            /** @var FlashBagAwareSessionInterface $session */
            $session = $stack->getSession();
            $message = $session->getFlashBag()->get('warning')[0] ?? null;
            self::assertInstanceOf(TranslatableMessage::class, $message);
            self::assertSame('wiki.trash.purge_hidden', $message->getMessage());
        }
        self::assertTrue($this->vault->isArchived());
    }

    public function testWithoutTheTrashOrAUserNothingIsReachable(): void
    {
        $this->assertThrows(NotFoundHttpException::class, fn (): Response => $this->controller(enabled: false)->index('eng'));
        $this->assertThrows(AccessDeniedHttpException::class, fn (): Response => $this->controller(user: false)->index('eng'));
        $this->assertThrows(AccessDeniedHttpException::class, fn (): Response => $this->controller(guard: new WikiTokenGuard(new RequestStack([new Request()])))->index('eng'));
        $this->assertThrows(AccessDeniedHttpException::class, fn (): Response => $this->controller(canArchive: false)->index('eng'));
    }

    public function testFlashesAreTranslatedWithATranslator(): void
    {
        $this->controller(translator: true)->delete($this->post(WikiTrashController::deleteTokenId($this->ops)), 'eng', 'ops');

        /** @var RequestStack $stack */
        $stack = $this->container->get('request_stack');
        /** @var FlashBagAwareSessionInterface $session */
        $session = $stack->getSession();
        self::assertSame(['translated wiki.trash.deleted'], $session->getFlashBag()->get('success'));
    }

    private function page(string $slug, string $title, ?WikiPage $parent = null): WikiPage
    {
        $page = new WikiPage($this->space, $slug, $title, $parent);
        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }

    private function post(string $tokenId, string $method = 'POST'): Request
    {
        return Request::create('/', $method, ['_token' => WikiControllerContainerBuilder::csrfToken($this->container, $tokenId)]);
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function flash(): array
    {
        /** @var RequestStack $stack */
        $stack = $this->container->get('request_stack');
        /** @var FlashBagAwareSessionInterface $session */
        $session = $stack->getSession();
        $message = $session->getFlashBag()->get('success')[0] ?? null;
        self::assertInstanceOf(TranslatableMessage::class, $message);

        return [$message->getMessage(), $message->getParameters()];
    }

    /**
     * @param callable(): mixed $call
     * @param class-string<Throwable> $expected
     */
    private function assertThrows(string $expected, callable $call): void
    {
        try {
            $call();
        } catch (Throwable $e) {
            self::assertInstanceOf($expected, $e);

            return;
        }
        self::fail('Expected ' . $expected . '.');
    }

    /**
     * @param (Closure(WikiAction, WikiSpace, ?WikiPage): bool)|null $decide
     * @param array<string, array{path: string, name: string}>|null $routes
     */
    private function controller(bool $enabled = true, bool $user = true, ?WikiTokenGuard $guard = null, ?Closure $decide = null, bool $canArchive = true, ?array $routes = null, bool $translator = false): WikiTrashController
    {
        $roles = $this->createStub(WikiAccessCheckerInterface::class);
        $roles->method('canArchive')->willReturn($canArchive);
        $routes ??= PageFixtures::routes() + [
            'space_trash'   => ['path' => '/t', 'name' => 'nowo_wiki_space_trash'],
            'trash_restore' => ['path' => '/r', 'name' => 'nowo_wiki_trash_restore'],
            'trash_purge'   => ['path' => '/p', 'name' => 'nowo_wiki_trash_purge'],
        ];

        $controller = new WikiTrashController(
            new WikiSpaceGuard(new FixedSpaceLocator($this->space), new CallbackSpaceAccessChecker($decide ?? static fn (): bool => true)),
            new DoctrineOrmWikiPageRepository($this->em),
            $roles,
            new WikiPageFactory(PageFixtures::urlGenerator(), $routes, new WikiPages()),
            new WikiPages(),
            $routes,
            $enabled ? $this->trash : null,
            $guard,
            $translator ? new class implements TranslatorInterface {
                public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
                {
                    return 'translated ' . $id;
                }

                public function getLocale(): string
                {
                    return 'en';
                }
            } : null,
        );
        $this->container = WikiControllerContainerBuilder::bind($controller, $user ? new TestUser() : null, ['twig' => $this->twig]);
        $this->container->set('router', PageFixtures::urlGenerator());

        return $controller;
    }
}
