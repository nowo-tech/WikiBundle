<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Api;

use LogicException;
use Nowo\WikiBundle\Api\Hook\WikiApiRateLimiterInterface;
use Nowo\WikiBundle\Api\Security\WikiApiRequestListener;
use Nowo\WikiBundle\Api\Value\WikiApiRateLimitState;
use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Port\Internal\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Port\Internal\WikiApiAuthenticatorInterface;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Stub\WikiForeignAdminUser;
use Nowo\WikiBundle\Tests\Stub\WikiSecurityUserDto;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Nowo\WikiBundle\User\WikiRefUser;
use Nowo\WikiBundle\User\WikiUserClassPolicy;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\UnexpectedSessionUsageException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * 1.24 (spec 218): the listener stays out of everything that is not the API, and an unexpected error is a 500 that
 * logs the class only.
 */
final class WikiApiRequestListenerTest extends TestCase
{
    public function testOtherRequestsAreLeftAlone(): void
    {
        $listener = $this->listener(new RecordingLogger());
        $kernel   = $this->createStub(HttpKernelInterface::class);
        $page     = Request::create('/tools/wiki');
        $page->attributes->set('_route', 'nowo_wiki_index');

        $request = new RequestEvent($kernel, $page, HttpKernelInterface::MAIN_REQUEST);
        $listener->onRequest($request);
        self::assertNull($request->getResponse());

        $response = new Response('ok');
        $listener->onResponse(new ResponseEvent($kernel, $page, HttpKernelInterface::MAIN_REQUEST, $response));
        self::assertNull($response->headers->get('X-Content-Type-Options'));

        $exception = new ExceptionEvent($kernel, $page, HttpKernelInterface::MAIN_REQUEST, new RuntimeException('x'));
        $listener->onException($exception);
        self::assertNull($exception->getResponse());

        $this->expectException(LogicException::class);
        WikiApiRequestListener::principalOf($page);
    }

    public function testAnUnexpectedErrorIsA500ThatLogsTheClassOnly(): void
    {
        $logger  = new RecordingLogger();
        $request = Request::create('/api/wiki/v1/wikis');
        $request->attributes->set(WikiApiRequestListener::ROUTE_FLAG, true);
        $exception = new ExceptionEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new RuntimeException('page text that must not be logged'));

        $this->listener($logger)->onException($exception);

        self::assertSame(500, $exception->getResponse()?->getStatusCode());
        self::assertSame('{"err":"Server error","ECODE":"SERVER_001"}', $exception->getResponse()?->getContent());
        self::assertSame(['error: Wiki API: unexpected error.'], $logger->lines());
        self::assertSame(RuntimeException::class, $logger->records[0]['context']['exception_class']);
    }

    public function testTheSecurityUserMayBeAnotherObjectOfTheSamePerson(): void
    {
        $principal = WikiApiPrincipal::of(WikiUserRefs::fromUser(new TestUserEntity('u-ana')), WikiApiLevel::Read, WikiApiWhere::everything(), 't', 'n');
        $tokens    = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken(new TestUserEntity('u-ana'), 'api', []));
        self::assertNull($this->enter($principal, $tokens)->getResponse());

        $tokens->setToken(new UsernamePasswordToken(new TestUserEntity('u-bob'), 'api', []));
        self::assertSame(500, $this->enter($principal, $tokens)->getResponse()?->getStatusCode());

        // Users without an id are the same person only as the same object.
        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('ana', null), 'api', []));
        $odd = WikiApiPrincipal::of(WikiUserRefs::fromUser(new InMemoryUser('ana', null)), WikiApiLevel::Read, WikiApiWhere::everything(), 't', 'n');
        self::assertSame(500, $this->enter($odd, $tokens)->getResponse()?->getStatusCode());
    }

    /**
     * Audit OX-L1 / OX-D1-L1: the firewall's user and the token's user are the same person as the same object, as two
     * users of `user_class` / an accepted class with the same usable id, or as two users of one other class with the
     * same exact id. In 1.x a mixed pair with the same exact id where a side is not an accepted class (a security
     * adapter) is let through with a deprecation notice naming the classes (refused in 2.0); it never decides
     * ownership (the guard judges the token's user alone). Any other mismatch is a configuration error naming both
     * classes, and a 500.
     */
    public function testTheSecurityUserAndTheTokensUserMustBeTheSamePerson(): void
    {
        $classes = new WikiUserClassPolicy(TestUserEntity::class, [WikiSecurityUserDto::class]);
        $tokens  = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken(new TestUserEntity('5'), 'api', []));
        foreach ([new TestUserEntity('5'), new WikiSecurityUserDto('5'), new WikiRefUser(WikiUserRef::ofId(TestUserEntity::class, '5'))] as $user) {
            $logger = new RecordingLogger();
            self::assertNull($this->enter($this->principalOf($user), $tokens, $classes, $logger)->getResponse(), $user::class);
            self::assertSame([], $logger->lines(), 'the same person: nothing logged');
        }

        // Mixed classes, same exact id: let through in 1.x with a notice; 2.0 refuses them (a look-alike id in another
        // user class is another person) as a configuration error naming the classes, and a 500.
        $mixed = [
            [new WikiForeignAdminUser('5'), new TestUserEntity('5'), WikiForeignAdminUser::class],
            [new WikiForeignAdminUser('5'), new WikiRefUser(WikiUserRef::ofId(TestUserEntity::class, '5')), WikiForeignAdminUser::class],
            [new TestUserEntity('5'), new WikiForeignAdminUser('5'), WikiForeignAdminUser::class],
            [new WikiForeignAdminUser('5'), new WikiRefUser(WikiUserRef::ofId('App\\Admin', '5')), WikiForeignAdminUser::class . ', App\\Admin'],
        ];
        foreach ($mixed as [$firewallUser, $tokenUser, $notAccepted]) {
            $tokens->setToken(new UsernamePasswordToken($firewallUser, 'api', []));
            $logger = new RecordingLogger();
            self::assertSame(500, $this->enter($this->principalOf($tokenUser), $tokens, $classes, $logger)->getResponse()?->getStatusCode(), $tokenUser::class);
            self::assertCount(1, $logger->records);
            self::assertSame('error', $logger->records[0]['level']);
            self::assertStringContainsString('share only their id; 2.0 refuses this: add ' . $notAccepted . ' to nowo_wiki.accepted_user_classes only if it carries user_class ids (an adapter or wrapper); otherwise the firewall and the authenticator must return the same user.', $logger->records[0]['message']);
            self::assertStringContainsString(WikiForeignAdminUser::class, $logger->records[0]['message']);
        }

        // Two users of one other class with the same id: the same person, silently.
        $tokens->setToken(new UsernamePasswordToken(new WikiForeignAdminUser('5'), 'api', []));
        $logger = new RecordingLogger();
        self::assertNull($this->enter($this->principalOf(new WikiForeignAdminUser('5')), $tokens, $classes, $logger)->getResponse());
        self::assertSame([], $logger->lines());

        // Another id or a look-alike: a configuration error naming both classes, and a 500.
        $wrong = [
            [new TestUserEntity('5'), new TestUserEntity('6')],
            [new TestUserEntity('0'), new TestUserEntity('0')], // accepted, but no usable id: never by id only
            [new TestUserEntity('5'), new WikiForeignAdminUser('6')],
            [new WikiForeignAdminUser('5'), new TestUserEntity(' 5')],
            [new WikiForeignAdminUser('5'), new WikiForeignAdminUser('5 ')],
            [new WikiForeignAdminUser('5'), new WikiRefUser(WikiUserRef::ofId('App\\Admin', '6'))],
        ];
        foreach ($wrong as [$firewallUser, $tokenUser]) {
            $tokens->setToken(new UsernamePasswordToken($firewallUser, 'api', []));
            $logger = new RecordingLogger();
            self::assertSame(500, $this->enter($this->principalOf($tokenUser), $tokens, $classes, $logger)->getResponse()?->getStatusCode(), $tokenUser::class);
            self::assertSame('error', $logger->records[0]['level'] ?? null);
            self::assertStringContainsString('is not the token\'s user', $logger->records[0]['message']);
            self::assertStringContainsString($firewallUser::class, $logger->records[0]['message']);
            self::assertStringContainsString($tokenUser instanceof WikiRefUser ? 'App\\Admin' : $tokenUser::class, $logger->records[0]['message']);
        }
    }

    /**
     * 1.56.1: Symfony 7.4.24+ refuses to read the token of a stateless request no stateless firewall covers
     * (`UnexpectedSessionUsageException`). That is no security user: the firewall answer (500, the "must sit behind a
     * stateless, secured firewall" log line), never "unexpected error".
     */
    public function testATokenReadRefusedForAStatelessRequestIsNoSecurityUser(): void
    {
        $tokens = new class implements TokenStorageInterface {
            public function getToken(): ?TokenInterface
            {
                throw new UnexpectedSessionUsageException('Session was used while the request was declared stateless.');
            }

            public function setToken(?TokenInterface $token): void
            {
            }
        };
        $logger = new RecordingLogger();

        self::assertSame(500, $this->enter($this->principalOf(new TestUserEntity('5')), $tokens, null, $logger)->getResponse()?->getStatusCode());
        self::assertSame(['error: Wiki API: the security user is not the token\'s user. The API must sit behind a stateless, secured firewall that authenticates the token.'], $logger->lines());
    }

    private function principalOf(object $user): WikiApiPrincipal
    {
        return WikiApiPrincipal::of(WikiUserRefs::fromUser($user), WikiApiLevel::Read, WikiApiWhere::everything(), 't', 'n');
    }

    private function enter(WikiApiPrincipal $principal, TokenStorageInterface $tokens, ?WikiUserClassPolicy $classes = null, ?RecordingLogger $logger = null): RequestEvent
    {
        $authenticator = $this->createStub(WikiApiAuthenticatorInterface::class);
        $authenticator->method('authenticate')->willReturn($principal);
        $limiter = $this->createStub(WikiApiRateLimiterInterface::class);
        $limiter->method('failedTokenState')->willReturn(new WikiApiRateLimitState(true, 1, 1, 0));
        $limiter->method('consume')->willReturn(new WikiApiRateLimitState(true, 1, 1, 0));
        $roles = $this->createStub(WikiAccessCheckerInterface::class);
        $roles->method('canAccess')->willReturn(true);
        $request = Request::create('/api/wiki/v1/wikis', server: ['HTTP_AUTHORIZATION' => 'Bearer x']);
        $request->attributes->set(WikiApiRequestListener::ROUTE_FLAG, true);
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        // No main request on the stack: the token guard trusts the token (outside HTTP).
        (new WikiApiRequestListener($authenticator, $limiter, $roles, $tokens, new WikiTokenGuard(new RequestStack()), '/api/wiki/v1', $logger ?? new RecordingLogger(), $classes))->onRequest($event);

        return $event;
    }

    private function listener(RecordingLogger $logger): WikiApiRequestListener
    {
        return new WikiApiRequestListener(
            $this->createStub(WikiApiAuthenticatorInterface::class),
            $this->createStub(WikiApiRateLimiterInterface::class),
            $this->createStub(WikiAccessCheckerInterface::class),
            new TokenStorage(),
            new WikiTokenGuard(new RequestStack()),
            '/api/wiki/v1',
            $logger,
        );
    }
}
