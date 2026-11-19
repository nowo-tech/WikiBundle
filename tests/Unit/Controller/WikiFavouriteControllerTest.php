<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Controller;

use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\ORM\EntityManager;
use Nowo\TransactionDoctrine\ConflictCode;
use Nowo\WikiBundle\Controller\WikiFavouriteController;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\QuickAccess\WikiQuickAccess;
use Nowo\WikiBundle\QuickAccess\WikiQuickAccessStore;
use Nowo\WikiBundle\QuickAccess\WikiReadableSpaces;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use Nowo\WikiBundle\Tests\Support\WikiControllerContainerBuilder;
use Nowo\WikiBundle\Transaction\WikiConflictAnswer;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Throwable;

use function array_map;
use function in_array;
use function json_decode;

/**
 * 1.45: {@see WikiFavouriteController} — the page view's favourite toggle: the read rules, CSRF, add / remove, the
 * limit, JSON and HTML answers, and the REQ-RUNTIME-003 rule 6 conflict answer.
 */
final class WikiFavouriteControllerTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    private WikiPage $page;

    /** @var list<string> page slugs the checker hides */
    private array $hidden = [];

    protected function setUp(): void
    {
        $this->em    = SqliteWikiEntityManager::create();
        $this->space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 't');
        $this->page  = new WikiPage($this->space, 'welcome', 'Welcome');
        $this->em->persist($this->space);
        $this->em->persist($this->page);
        $this->em->flush();
    }

    public function testAddsThenRemovesWithAFlashAndARedirect(): void
    {
        [$controller, $container] = $this->bound();

        $response = $controller->toggle($this->post($container, '1'), 'eng', 'welcome');
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/generated/nowo_wiki_page_view', $response->getTargetUrl());
        self::assertSame(['wiki.favourites.added'], $this->flashes($container, 'success'));
        self::assertSame(1, $this->rows());

        $controller->toggle($this->post($container, '0'), 'eng', 'welcome');
        self::assertSame(['wiki.favourites.removed'], $this->flashes($container, 'success'));
        self::assertSame(0, $this->rows());
    }

    public function testJsonAnswersAndTheLimit(): void
    {
        [$controller, $container] = $this->bound(max: 1);

        $response = $controller->toggle($this->post($container, '1', json: true), 'eng', 'welcome');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['favourite' => true, 'result' => 'added'], json_decode((string) $response->getContent(), true));
        $response = $controller->toggle($this->post($container, '1', json: true), 'eng', 'welcome');
        self::assertSame(['favourite' => true, 'result' => 'unchanged'], json_decode((string) $response->getContent(), true));

        $other = new WikiPage($this->space, 'other', 'Other');
        $this->em->persist($other);
        $this->em->flush();
        $request  = $this->post($container, '1', json: true, page: $other);
        $response = $controller->toggle($request, 'eng', 'other');
        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['error' => 'limit_reached'], json_decode((string) $response->getContent(), true));

        $controller->toggle($this->post($container, '1', page: $other), 'eng', 'other');
        self::assertSame(['wiki.favourites.limit_reached'], $this->flashes($container, 'warning'));
    }

    public function testALockConflictAnswers409WithTheConflictCode(): void
    {
        [$controller, $container] = $this->bound();
        $this->em->getConnection()->executeStatement("CREATE TRIGGER wk_fav_locked BEFORE INSERT ON wiki_page_favourites BEGIN SELECT RAISE(ABORT, 'database is locked'); END");

        $response = $controller->toggle($this->post($container, '1', json: true), 'eng', 'welcome');
        self::assertSame(409, $response->getStatusCode());
        self::assertSame(ConflictCode::RETRY_SAFE, $response->headers->get('X-Conflict-Code'));
        self::assertSame('1', $response->headers->get('Retry-After'));
        self::assertSame('CONFLICT_001', json_decode((string) $response->getContent(), true)['code']);

        // Without the service's own answer (autowired null): the default policy.
        [$controller, $container] = $this->bound(conflicts: false);
        $response                 = $controller->toggle($this->post($container, '1'), 'eng', 'welcome');
        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
    }

    public function testAnyOtherFailurePropagates(): void
    {
        [$controller, $container] = $this->bound();
        $this->em->getConnection()->executeStatement('DROP TABLE wiki_page_favourites');

        $this->expectException(TableNotFoundException::class);
        $controller->toggle($this->post($container, '1'), 'eng', 'welcome');
    }

    public function testRefusals(): void
    {
        // Favourites off (no service, or the switch off): 404.
        foreach ([null, false] as $enabled) {
            [$controller, $container] = $this->bound(enabled: $enabled);
            $this->assertRefused(NotFoundHttpException::class, fn () => $controller->toggle($this->post($container, '1'), 'eng', 'welcome'));
        }
        // No user, an untrusted token: 403.
        [$controller, $container] = $this->bound(user: null);
        $this->assertRefused(AccessDeniedHttpException::class, fn () => $controller->toggle($this->post($container, '1'), 'eng', 'welcome'));
        [$controller, $container] = $this->bound(tokenGuard: new WikiTokenGuard(new RequestStack([new Request()])));
        $this->assertRefused(AccessDeniedHttpException::class, fn () => $controller->toggle($this->post($container, '1'), 'eng', 'welcome'));
        // No list role, a user without an id: 404.
        [$controller, $container] = $this->bound(canList: false);
        $this->assertRefused(NotFoundHttpException::class, fn () => $controller->toggle($this->post($container, '1'), 'eng', 'welcome'));
        [$controller, $container] = $this->bound(user: new TestUserEntity(''));
        $this->assertRefused(NotFoundHttpException::class, fn () => $controller->toggle($this->post($container, '1'), 'eng', 'welcome'));
        // Unknown, archived and hidden pages answer 404 alike.
        [$controller, $container] = $this->bound();
        $this->assertRefused(NotFoundHttpException::class, fn () => $controller->toggle($this->post($container, '1'), 'eng', 'missing'));
        $this->hidden = ['welcome'];
        $this->assertRefused(NotFoundHttpException::class, fn () => $controller->toggle($this->post($container, '1'), 'eng', 'welcome'));
        $this->hidden = [];
        $this->page->archive();
        $this->em->flush();
        $this->assertRefused(NotFoundHttpException::class, fn () => $controller->toggle($this->post($container, '1'), 'eng', 'welcome'));
        $this->page->unarchive();
        $this->em->flush();
        // A bad token: 403.
        $request = Request::create('/favourite', 'POST', ['_token' => 'bad', 'favourite' => '1']);
        $this->assertRefused(AccessDeniedHttpException::class, static fn () => $controller->toggle($request, 'eng', 'welcome'));
        self::assertSame(0, $this->rows());
        self::assertSame('wiki_favourite_' . $this->page->getId(), WikiFavouriteController::tokenId($this->page));
    }

    /**
     * @param class-string<Throwable> $class
     */
    private function assertRefused(string $class, callable $call): void
    {
        try {
            $call();
            self::fail('Expected ' . $class);
        } catch (Throwable $failure) {
            self::assertInstanceOf($class, $failure);
        }
    }

    /**
     * @return array{0: WikiFavouriteController, 1: Container}
     */
    private function bound(?bool $enabled = true, int $max = 10, ?UserInterface $user = new TestUser('u-1'), bool $canList = true, ?WikiTokenGuard $tokenGuard = null, bool $conflicts = true): array
    {
        $roles = $this->createStub(WikiAccessCheckerInterface::class);
        $roles->method('canList')->willReturn($canList);
        $resolver = $this->createStub(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$this->space]);
        $checker = new CallbackSpaceAccessChecker(fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => !$page instanceof WikiPage || !in_array($page->getSlug(), $this->hidden, true));
        $quick   = $enabled === null ? null : new WikiQuickAccess(
            new WikiQuickAccessStore(new WikiTransactionRunner($this->em, backoffBaseMilliseconds: 0)),
            new WikiReadableSpaces($roles, $resolver, $checker),
            ['enabled' => $enabled, 'max' => $max, 'shown' => 10],
            ['enabled' => false, 'keep' => 20, 'shown' => 8],
        );
        $controller = new WikiFavouriteController(
            new WikiSpaceGuard(new FixedSpaceLocator($this->space), $checker),
            new DoctrineOrmWikiPageRepository($this->em),
            $roles,
            ['page_view' => ['name' => 'nowo_wiki_page_view', 'path' => '/p']],
            $quick,
            $conflicts ? new WikiConflictAnswer() : null,
            $tokenGuard,
        );
        $container = WikiControllerContainerBuilder::bind($controller, $user);

        return [$controller, $container];
    }

    private function post(Container $container, string $favourite, bool $json = false, ?WikiPage $page = null): Request
    {
        $request = Request::create('/favourite', 'POST', [
            '_token'    => WikiControllerContainerBuilder::csrfToken($container, WikiFavouriteController::tokenId($page ?? $this->page)),
            'favourite' => $favourite,
        ]);
        if ($json) {
            $request->headers->set('Accept', 'application/json');
        }

        return $request;
    }

    /**
     * @return list<mixed>
     */
    private function flashes(Container $container, string $type): array
    {
        $session = $container->get('request_stack')->getSession();
        self::assertInstanceOf(FlashBagAwareSessionInterface::class, $session);

        return array_map(static fn (mixed $message): mixed => $message instanceof TranslatableMessage ? $message->getMessage() : $message, $session->getFlashBag()->get($type));
    }

    private function rows(): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM wiki_page_favourites');
    }
}
