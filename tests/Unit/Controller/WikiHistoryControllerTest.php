<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Controller;

use Closure;
use Doctrine\ORM\EntityManager;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\WikiBundle\Controller\WikiHistoryController;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Event\WikiQuotaCheckEvent;
use Nowo\WikiBundle\Form\WikiVersionLabelType;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiRevisionLabelRepository;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiQuotaGate;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\PageFixtures;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use Nowo\WikiBundle\Tests\Support\WikiControllerContainerBuilder;
use Nowo\WikiBundle\Ui\WikiPageFactory;
use Nowo\WikiBundle\Ui\WikiPages;
use Nowo\WikiBundle\Util\WikiSlugger;
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

use function mb_strlen;
use function str_repeat;
use function strlen;

/**
 * 1.15: Save version and Copy to new page check the timeline, the user, the rights, the page and the token.
 */
final class WikiHistoryControllerTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    private WikiPage $run;

    private WikiPage $vault;

    private WikiPage $plan;

    private WikiPageRevision $v1;

    private WikiPageRevision $md;

    private DoctrineOrmWikiRevisionLabelRepository $labels;

    private TestUserEntity $author;

    private object $twig;

    private Container $container;

    protected function setUp(): void
    {
        $this->em    = SqliteWikiEntityManager::create();
        $this->space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $this->em->persist($this->space);
        $author = $this->author = new TestUserEntity('u-1');
        $this->em->persist($author);
        $this->run   = $this->page('run', 'Runbooks');
        $this->vault = $this->page('vault', 'Vault');
        $this->plan  = $this->page('plan', 'Plan', $this->vault);
        $this->v1    = new WikiPageRevision($this->run, 1, '<p>first</p>', $author);
        $this->md    = new WikiPageRevision($this->run, 2, '<p>second</p>', $author, '# second');
        $this->em->persist($this->v1);
        $this->em->persist($this->md);
        $this->run->setCurrentRevision($this->md);
        $this->plan->setCurrentRevision($plan = new WikiPageRevision($this->plan, 1, '<p>plan</p>', $author));
        $this->em->persist($plan);
        $this->em->flush();
        $this->labels = new DoctrineOrmWikiRevisionLabelRepository($this->em);
        $this->twig   = new class {
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

    public function testSaveVersionShowsTheFormThenNamesTheCurrentRevision(): void
    {
        $response = $this->controller()->saveVersion(Request::create('/'), 'eng', 'run');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertInstanceOf(FormPage::class, $this->twig->context['page']);

        $response = $this->controller()->saveVersion($this->labelPost('Before the migration'), 'eng', 'run');
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertStringStartsWith('/nowo_wiki_page_history', $response->getTargetUrl());
        self::assertSame('Before the migration', $this->labels->findForRevision($this->md)?->getLabel());
        self::assertSame(['wiki.history.version_saved', ['%label%' => 'Before the migration', '%number%' => 2]], $this->flash());

        // Saving again renames the version (one label per revision).
        $this->controller(routes: PageFixtures::routes() + ['page_version_save' => ['path' => '/v', 'name' => 'nowo_wiki_page_version_save']])
            ->saveVersion($this->labelPost('  Reviewed  '), 'eng', 'run');
        self::assertSame('Reviewed', $this->labels->findForRevision($this->md)?->getLabel());
    }

    public function testWithoutAHistoryRouteSaveVersionGoesBackToThePage(): void
    {
        $routes = PageFixtures::routes();
        unset($routes['page_history']);

        $response = $this->controller(routes: $routes)->saveVersion($this->labelPost('x'), 'eng', 'run');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertStringStartsWith('/nowo_wiki_page_view', $response->getTargetUrl());
    }

    public function testAnInvalidLabelAnswers422(): void
    {
        $response = $this->controller()->saveVersion($this->labelPost(''), 'eng', 'run');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertNull($this->labels->findForRevision($this->md));
    }

    public function testSaveVersionRefusals(): void
    {
        // No Edit right, no edit role, a page without a revision, a hidden or missing page.
        $this->assertThrows(AccessDeniedHttpException::class, fn (): Response => $this->controller(decide: static fn (WikiAction $a): bool => $a !== WikiAction::Edit)->saveVersion(Request::create('/'), 'eng', 'run'));
        $this->assertThrows(AccessDeniedHttpException::class, fn (): Response => $this->controller(roles: false)->saveVersion(Request::create('/'), 'eng', 'run'));
        $this->assertThrows(NotFoundHttpException::class, fn (): Response => $this->controller()->saveVersion(Request::create('/'), 'eng', 'vault'));
        $this->assertThrows(NotFoundHttpException::class, fn (): Response => $this->controller(decide: fn (WikiAction $a, WikiSpace $s, ?WikiPage $p): bool => $p !== $this->vault)->saveVersion(Request::create('/'), 'eng', 'plan'));
        $this->assertThrows(NotFoundHttpException::class, fn (): Response => $this->controller()->saveVersion(Request::create('/'), 'eng', 'nope'));
        $this->run->archive();
        $this->em->flush();
        $this->assertThrows(NotFoundHttpException::class, fn (): Response => $this->controller()->saveVersion(Request::create('/'), 'eng', 'run'));
    }

    public function testCopyCreatesATopLevelPageFromTheRevision(): void
    {
        $controller = $this->controller();

        $response = $controller->copyVersion($this->post(WikiHistoryController::copyTokenId($this->md)), 'eng', 'run', 2);

        self::assertSame('/nowo_wiki_page_view?spaceSlug=eng&pageSlug=run-v2', $response->getTargetUrl());
        self::assertSame(['wiki.history.copied', ['%title%' => 'Runbooks (v2)']], $this->flash());
        $copy = (new DoctrineOrmWikiPageRepository($this->em))->findBySlug($this->space, 'run-v2');
        self::assertInstanceOf(WikiPage::class, $copy);
        self::assertNull($copy->getParent());
        self::assertSame('# second', $copy->getCurrentRevision()?->getContentMarkdown());

        // A second copy gets a free slug; an HTML revision stays HTML.
        $this->controller()->copyVersion($this->post(WikiHistoryController::copyTokenId($this->md)), 'eng', 'run', 2);
        self::assertNotNull((new DoctrineOrmWikiPageRepository($this->em))->findBySlug($this->space, 'run-v2-2'));
        $this->controller()->copyVersion($this->post(WikiHistoryController::copyTokenId($this->v1)), 'eng', 'run', 1);
        $html = (new DoctrineOrmWikiPageRepository($this->em))->findBySlug($this->space, 'run-v1');
        self::assertSame(WikiPageRevision::FORMAT_HTML, $html?->getCurrentRevision()?->getFormat());
    }

    public function testLongTitlesAndSlugsAreCutToTheColumnsKeepingTheSuffix(): void
    {
        $title = str_repeat('é', 250) . 'xyz';
        $slug  = str_repeat('a', 118) . 'bc';
        $long  = $this->page($slug, $title);
        $long->setCurrentRevision($revision = new WikiPageRevision($long, 12, '<p>long</p>', $this->author));
        $this->em->persist($revision);
        $this->em->flush();

        $this->controller()->copyVersion($this->post(WikiHistoryController::copyTokenId($revision)), 'eng', $slug, 12);
        $this->controller()->copyVersion($this->post(WikiHistoryController::copyTokenId($revision)), 'eng', $slug, 12);

        $repository = new DoctrineOrmWikiPageRepository($this->em);
        $first      = $repository->findBySlug($this->space, str_repeat('a', 116) . '-v12');
        $second     = $repository->findBySlug($this->space, str_repeat('a', 114) . '-v12-2');
        self::assertInstanceOf(WikiPage::class, $first);
        self::assertInstanceOf(WikiPage::class, $second);
        self::assertSame(255, mb_strlen($first->getTitle()));
        self::assertStringEndsWith('é (v12)', $first->getTitle());
        self::assertSame(120, strlen($second->getSlug()));
    }

    public function testBidiControlsAreStrippedFromVersionNames(): void
    {
        $this->controller()->saveVersion($this->labelPost("\u{202E}Before\u{2066} the move\u{200F}"), 'eng', 'run');
        self::assertSame('Before the move', $this->labels->findForRevision($this->md)?->getLabel());

        // Nothing visible: refused like an empty name.
        $response = $this->controller()->saveVersion($this->labelPost("\u{202E}\u{2066} "), 'eng', 'run');
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $response = $this->controller(translator: true)->saveVersion($this->labelPost("\u{200E}"), 'eng', 'run');
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('Before the move', $this->labels->findForRevision($this->md)?->getLabel());
    }

    public function testACopyTheQuotaRefusesIsNotMade(): void
    {
        $seen     = [];
        $response = $this->controller(quota: $this->refusingQuota($seen))->copyVersion($this->post(WikiHistoryController::copyTokenId($this->md)), 'eng', 'run', 2);

        self::assertSame('/nowo_wiki_page_history?spaceSlug=eng&pageSlug=run', $response->getTargetUrl());
        self::assertSame(['Plan limit reached.'], $this->errors());
        self::assertNull((new DoctrineOrmWikiPageRepository($this->em))->findBySlug($this->space, 'run-v2'));
        self::assertSame(WikiQuotaCheckEvent::PAGE_CREATE, $seen[0]->action);
        self::assertSame('run', $seen[0]->page?->getSlug());

        // Without a history route the refusal goes back to the page.
        $routes = PageFixtures::routes();
        unset($routes['page_history']);
        $response = $this->controller(routes: $routes, quota: $this->refusingQuota($seen))->copyVersion($this->post(WikiHistoryController::copyTokenId($this->md)), 'eng', 'run', 2);
        self::assertSame('/nowo_wiki_page_view?spaceSlug=eng&pageSlug=run', $response->getTargetUrl());
    }

    public function testCopyRefusals(): void
    {
        $token = WikiHistoryController::copyTokenId($this->md);
        $this->assertThrows(AccessDeniedHttpException::class, fn (): Response => $this->controller(decide: static fn (WikiAction $a): bool => $a !== WikiAction::History)->copyVersion($this->post($token), 'eng', 'run', 2));
        $this->assertThrows(AccessDeniedHttpException::class, fn (): Response => $this->controller(decide: static fn (WikiAction $a): bool => $a !== WikiAction::Create)->copyVersion($this->post($token), 'eng', 'run', 2));
        $this->assertThrows(AccessDeniedHttpException::class, fn (): Response => $this->controller(roles: false)->copyVersion($this->post($token), 'eng', 'run', 2));
        $this->assertThrows(AccessDeniedHttpException::class, fn (): Response => $this->controller()->copyVersion($this->post('wrong'), 'eng', 'run', 2));
        $this->assertThrows(NotFoundHttpException::class, fn (): Response => $this->controller()->copyVersion($this->post($token), 'eng', 'run', 9));
        $this->assertThrows(NotFoundHttpException::class, fn (): Response => $this->controller(decide: fn (WikiAction $a, WikiSpace $s, ?WikiPage $p): bool => $p !== $this->vault)->copyVersion($this->post($token), 'eng', 'plan', 1));
    }

    public function testWithoutTheTimelineOrAUserNothingIsReachable(): void
    {
        $this->assertThrows(NotFoundHttpException::class, fn (): Response => $this->controller(enabled: false)->saveVersion(Request::create('/'), 'eng', 'run'));
        $this->assertThrows(NotFoundHttpException::class, fn (): Response => $this->controller(enabled: false)->copyVersion(Request::create('/'), 'eng', 'run', 2));
        $this->assertThrows(AccessDeniedHttpException::class, fn (): Response => $this->controller(user: false)->saveVersion(Request::create('/'), 'eng', 'run'));
        $this->assertThrows(AccessDeniedHttpException::class, fn (): Response => $this->controller(guard: new WikiTokenGuard(new RequestStack([new Request()])))->saveVersion(Request::create('/'), 'eng', 'run'));
    }

    public function testFlashesAreTranslatedWithATranslator(): void
    {
        $this->controller(translator: true)->saveVersion($this->labelPost('Named'), 'eng', 'run');

        /** @var RequestStack $stack */
        $stack = $this->container->get('request_stack');
        /** @var FlashBagAwareSessionInterface $session */
        $session = $stack->getSession();
        self::assertSame(['translated wiki.history.version_saved'], $session->getFlashBag()->get('success'));
    }

    private function page(string $slug, string $title, ?WikiPage $parent = null): WikiPage
    {
        $page = new WikiPage($this->space, $slug, $title, $parent);
        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }

    private function labelPost(string $label): Request
    {
        return Request::create('/', 'POST', ['label' => $label, '_token' => WikiControllerContainerBuilder::csrfToken($this->container, WikiVersionLabelType::csrfTokenId($this->run->getId()))]);
    }

    private function post(string $tokenId): Request
    {
        return Request::create('/', 'POST', ['_token' => WikiControllerContainerBuilder::csrfToken($this->container, $tokenId)]);
    }

    /**
     * 1.21: a quota gate whose listener refuses every check, recording the events.
     *
     * @param list<WikiQuotaCheckEvent> $seen
     */
    private function refusingQuota(array &$seen): WikiQuotaGate
    {
        $events = new EventDispatcher();
        $events->addListener(WikiEvents::QUOTA_CHECK, static function (WikiQuotaCheckEvent $event) use (&$seen): void {
            $seen[] = $event;
            $event->deny('Plan limit reached.');
        });

        return new WikiQuotaGate($events);
    }

    /**
     * @return list<mixed>
     */
    private function errors(): array
    {
        /** @var RequestStack $stack */
        $stack = $this->container->get('request_stack');
        /** @var FlashBagAwareSessionInterface $session */
        $session = $stack->getSession();

        return $session->getFlashBag()->get('error');
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
    private function controller(bool $enabled = true, bool $user = true, ?WikiTokenGuard $guard = null, ?Closure $decide = null, bool $roles = true, ?array $routes = null, bool $translator = false, ?WikiQuotaGate $quota = null): WikiHistoryController
    {
        $checker = $this->createStub(WikiAccessCheckerInterface::class);
        $checker->method('canEdit')->willReturn($roles);
        $checker->method('canCreate')->willReturn($roles);
        $routes ??= PageFixtures::routes();
        $pages     = new DoctrineOrmWikiPageRepository($this->em);
        $revisions = new DoctrineOrmWikiPageRevisionRepository($this->em);

        $controller = new WikiHistoryController(
            new WikiSpaceGuard(new FixedSpaceLocator($this->space), new CallbackSpaceAccessChecker($decide ?? static fn (): bool => true)),
            $pages,
            $revisions,
            new WikiPageService($pages, $revisions, new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher()),
            $checker,
            new WikiPageFactory(PageFixtures::urlGenerator(), $routes, new WikiPages()),
            new WikiPages(),
            new WikiSlugger(),
            $routes,
            $enabled ? $this->labels : null,
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
            $quota,
        );
        $this->container = WikiControllerContainerBuilder::bind($controller, $user ? $this->author : null, ['twig' => $this->twig]);
        $this->container->set('router', PageFixtures::urlGenerator());

        return $controller;
    }
}
