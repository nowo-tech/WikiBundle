<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Security;

use LogicException;
use Nowo\WikiBundle\Api\Hook\WikiApiAuthenticatorInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiRateLimiterInterface;
use Nowo\WikiBundle\Api\Value\WikiApiRateLimitState;
use Nowo\WikiBundle\Api\WikiApiAuthFailure;
use Nowo\WikiBundle\Api\WikiApiCredential;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stringable;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Throwable;

use function in_array;
use function is_scalar;
use function method_exists;
use function str_starts_with;

/**
 * 1.24 (spec 218): what every API request goes through before its controller, on the API's routes only, and how every
 * answer leaves. Below the firewall, so the security user is set. It fails closed: anything it cannot establish is a
 * refusal, and the credential is never logged or echoed.
 */
final readonly class WikiApiRequestListener implements EventSubscriberInterface
{
    /** Route default marking the API's routes. */
    public const ROUTE_FLAG = '_nowo_wiki_api';

    private const PRINCIPAL = '_nowo_wiki_api_principal';

    private const RATE_LIMIT = '_nowo_wiki_api_rate_limit';

    public function __construct(
        private WikiApiAuthenticatorInterface $authenticator,
        private WikiApiRateLimiterInterface $rateLimiter,
        private WikiAccessCheckerInterface $accessChecker,
        private TokenStorageInterface $tokenStorage,
        private WikiTokenGuard $tokenGuard,
        private string $prefix,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // The firewall is at 8.
            KernelEvents::REQUEST  => ['onRequest', 7],
            KernelEvents::RESPONSE => 'onResponse',
            // Before the firewall's own (1) and the kernel's error page.
            KernelEvents::EXCEPTION => ['onException', 16],
        ];
    }

    /** The principal of an authenticated API request (set by {@see self::onRequest()}). */
    public static function principalOf(Request $request): WikiApiPrincipal
    {
        $principal = $request->attributes->get(self::PRINCIPAL);

        return $principal instanceof WikiApiPrincipal ? $principal : throw new LogicException('Not an authenticated API request.');
    }

    /** The JSON answer of a refusal: `{"err", "ECODE"}` and its extra fields, with its headers. */
    public static function answer(WikiApiRefusal $refusal): JsonResponse
    {
        return new JsonResponse(['err' => $refusal->getMessage(), 'ECODE' => $refusal->ecode] + $refusal->extra, $refusal->status, $refusal->headers);
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->attributes->getBoolean(self::ROUTE_FLAG)) {
            return;
        }

        try {
            $request->attributes->set(self::PRINCIPAL, $this->enter($request));
        } catch (WikiApiRefusal $refusal) {
            $event->setResponse(self::answer($refusal));
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$this->isApiRequest($request)) {
            return;
        }
        $response = $event->getResponse();
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $state = $request->attributes->get(self::RATE_LIMIT);
        if ($state instanceof WikiApiRateLimitState) {
            $response->headers->set('X-RateLimit-Limit', (string) $state->limit);
            $response->headers->set('X-RateLimit-Remaining', (string) $state->remaining);
            $response->headers->set('X-RateLimit-Reset', (string) $state->resetAt);
        }
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->isApiRequest($event->getRequest())) {
            return;
        }
        $event->setResponse(self::answer($this->refusalFor($event->getThrowable())));
    }

    /**
     * @throws WikiApiRefusal
     */
    private function enter(Request $request): WikiApiPrincipal
    {
        $clientIp = (string) $request->getClientIp();
        $blocked  = $this->rateLimiter->failedTokenState($clientIp);
        if (!$blocked->accepted) {
            $request->attributes->set(self::RATE_LIMIT, $blocked);

            throw new WikiApiRefusal(Response::HTTP_TOO_MANY_REQUESTS, 'RATE_002', 'Too many failed tokens');
        }
        if (WikiApiCredential::fromRequest($request) === null) {
            throw new WikiApiRefusal(Response::HTTP_UNAUTHORIZED, 'OAUTH_017', 'Authorization header required');
        }

        $principal = $this->authenticator->authenticate($request);
        if ($principal === WikiApiAuthFailure::ApiDisabled) {
            throw new WikiApiRefusal(Response::HTTP_NOT_FOUND, 'API_001', 'Not found');
        }
        if ($principal === WikiApiAuthFailure::Invalid) {
            $this->rateLimiter->registerFailedToken($clientIp);

            throw $this->invalidToken();
        }
        if ($principal === WikiApiAuthFailure::NoWikiLevel) {
            throw WikiApiRefusal::levelRefused();
        }
        $this->requireSecurityUser($principal);
        if (!$this->accessChecker->canAccess($principal->user)) {
            // The same answer as a bad token, and counted as one.
            $this->rateLimiter->registerFailedToken($clientIp);

            throw $this->invalidToken();
        }

        $state = $this->rateLimiter->consume($principal);
        $request->attributes->set(self::RATE_LIMIT, $state);
        if (!$state->accepted) {
            throw new WikiApiRefusal(Response::HTTP_TOO_MANY_REQUESTS, 'RATE_001', 'Rate limit reached');
        }

        return $principal;
    }

    /**
     * Access checks and the per-viewer render read the security token, and trust it only on a secured firewall
     * ({@see WikiTokenGuard}): a principal the firewall does not stand behind would be judged as someone else, or as
     * nobody (contract F2).
     *
     * @throws WikiApiRefusal
     */
    private function requireSecurityUser(WikiApiPrincipal $principal): void
    {
        $securityUser = $this->tokenStorage->getToken()?->getUser();
        $same         = $securityUser === $principal->user || ($securityUser !== null && $this->idOf($securityUser) !== null && $this->idOf($securityUser) === $this->idOf($principal->user));
        if ($same && $this->tokenGuard->isTokenTrusted()) {
            return;
        }
        $this->logger->error('Wiki API: the security user is not the token\'s user. The API must sit behind a stateless, secured firewall that authenticates the token.', ['bundle' => 'nowo_wiki']);

        throw $this->serverError();
    }

    private function idOf(object $user): ?string
    {
        $id = method_exists($user, 'getId') ? $user->getId() : null;

        return is_scalar($id) || $id instanceof Stringable ? (string) $id : null;
    }

    private function isApiRequest(Request $request): bool
    {
        if ($request->attributes->getBoolean(self::ROUTE_FLAG)) {
            return true;
        }

        // No route matched: an unknown path under the prefix answers like the rest of the API.
        return !$request->attributes->has('_route') && str_starts_with($request->getPathInfo() . '/', $this->prefix . '/');
    }

    private function refusalFor(Throwable $throwable): WikiApiRefusal
    {
        if ($throwable instanceof WikiApiRefusal) {
            return $throwable;
        }
        if ($throwable instanceof HttpExceptionInterface && in_array($throwable->getStatusCode(), [Response::HTTP_NOT_FOUND, Response::HTTP_METHOD_NOT_ALLOWED], true)) {
            return WikiApiRefusal::notFound();
        }
        // The class only: a message may hold user data.
        $this->logger->error('Wiki API: unexpected error.', ['bundle' => 'nowo_wiki', 'exception_class' => $throwable::class]);

        return $this->serverError();
    }

    private function invalidToken(): WikiApiRefusal
    {
        return new WikiApiRefusal(Response::HTTP_UNAUTHORIZED, 'OAUTH_019', 'Token not valid');
    }

    private function serverError(): WikiApiRefusal
    {
        return new WikiApiRefusal(Response::HTTP_INTERNAL_SERVER_ERROR, 'SERVER_001', 'Server error');
    }
}
