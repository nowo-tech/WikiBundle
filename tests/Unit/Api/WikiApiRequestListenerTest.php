<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Api;

use LogicException;
use Nowo\WikiBundle\Api\Hook\WikiApiAuthenticatorInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiRateLimiterInterface;
use Nowo\WikiBundle\Api\Security\WikiApiRequestListener;
use Nowo\WikiBundle\Api\Value\WikiApiRateLimitState;
use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
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
        $principal = new WikiApiPrincipal(new TestUserEntity('u-ana'), WikiApiLevel::Read, WikiApiWhere::everything(), 't', 'n');
        $tokens    = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken(new TestUserEntity('u-ana'), 'api', []));
        self::assertNull($this->enter($principal, $tokens)->getResponse());

        $tokens->setToken(new UsernamePasswordToken(new TestUserEntity('u-bob'), 'api', []));
        self::assertSame(500, $this->enter($principal, $tokens)->getResponse()?->getStatusCode());

        // Users without an id are the same person only as the same object.
        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('ana', null), 'api', []));
        $odd = new WikiApiPrincipal(new InMemoryUser('ana', null), WikiApiLevel::Read, WikiApiWhere::everything(), 't', 'n');
        self::assertSame(500, $this->enter($odd, $tokens)->getResponse()?->getStatusCode());
    }

    private function enter(WikiApiPrincipal $principal, TokenStorage $tokens): RequestEvent
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
        (new WikiApiRequestListener($authenticator, $limiter, $roles, $tokens, new WikiTokenGuard(new RequestStack()), '/api/wiki/v1'))->onRequest($event);

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
