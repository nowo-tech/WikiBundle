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
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiTokenGuard;
use Nowo\WikiBundle\User\WikiUserClassPolicy;
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
use Symfony\Component\Security\Core\User\UserInterface;
use Throwable;

use function count;
use function implode;
use function in_array;
use function is_scalar;
use function method_exists;
use function sprintf;
use function str_starts_with;

/**
 * 1.24 (spec 218): what every API request goes through before its controller, on the API's routes only, and how every
 * answer leaves. Below the firewall, so the security user is set. It fails closed: anything it cannot establish is a
 * refusal, and the credential is never logged or echoed.
 */
final readonly class WikiApiRequestListener implements EventSubscriberInterface
{
    /** {@see self::match()}: the same person. */
    private const SAME = 'same';

    /** {@see self::match()}: another person, or an id that cannot be matched exactly. */
    private const DIFFERENT = 'different';

    /** Route default marking the API's routes. */
    public const ROUTE_FLAG = '_nowo_wiki_api';

    private const PRINCIPAL = '_nowo_wiki_api_principal';

    private const RATE_LIMIT = '_nowo_wiki_api_rate_limit';

    private const AFTER_COMMIT = '_nowo_wiki_api_after_commit';

    public function __construct(
        private WikiApiAuthenticatorInterface $authenticator,
        private WikiApiRateLimiterInterface $rateLimiter,
        private WikiAccessCheckerInterface $accessChecker,
        private TokenStorageInterface $tokenStorage,
        private WikiTokenGuard $tokenGuard,
        private string $prefix,
        private LoggerInterface $logger = new NullLogger(),
        private ?WikiUserClassPolicy $classes = null,
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
        $refusal = $this->refusalFor($event->getThrowable());
        if ($refusal->afterCommit) {
            $event->getRequest()->attributes->set(self::AFTER_COMMIT, true);
        }
        $event->setResponse(self::answer($refusal));
    }

    /** Whether the request's write was stored although it was answered with a refusal (audit IF-L8). */
    public static function wroteAnyway(Request $request): bool
    {
        return $request->attributes->getBoolean(self::AFTER_COMMIT);
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
            // @igor-ignore - Failed-token counter is meant to persist across requests (host rate limiter storage).
            $this->rateLimiter->registerFailedToken($clientIp);

            throw $this->invalidToken();
        }
        if ($principal === WikiApiAuthFailure::NoWikiLevel) {
            throw WikiApiRefusal::levelRefused();
        }
        $this->requireSecurityUser($principal);
        if (!$this->accessChecker->canAccess($principal->user)) {
            // The same answer as a bad token, and counted as one.
            // @igor-ignore - Failed-token counter is meant to persist across requests (host rate limiter storage).
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
        $match        = $securityUser instanceof UserInterface ? $this->match($securityUser, $principal->user) : null;
        if ($match !== null && $match !== self::DIFFERENT && $this->tokenGuard->isTokenTrusted()) {
            if ($match !== self::SAME) {
                // Audit OX-D1-L1 (1.36): a security adapter of another class with the same exact id is let through in
                // 1.x. It only stands behind the token: ownership and the API id are judged on the token's user alone.
                $this->logger->warning(sprintf('Wiki API: the firewall user (%s) and the token\'s user (%s) are matched by their id only: add %s to nowo_wiki.accepted_user_classes; this will be refused in 2.0.', $this->classOf($securityUser), $this->classOf($principal->user), $match), ['bundle' => 'nowo_wiki']);
            }

            return;
        }
        if ($match === self::DIFFERENT && $securityUser instanceof UserInterface) {
            $this->logger->error(sprintf('Wiki API: configuration error: the firewall user (%s) is not the token\'s user (%s): another id, or an id that cannot be matched exactly. The firewall must authenticate the same person as nowo_wiki.api.hooks.authenticator.', $this->classOf($securityUser), $this->classOf($principal->user)), ['bundle' => 'nowo_wiki']);
        } else {
            $this->logger->error('Wiki API: the security user is not the token\'s user. The API must sit behind a stateless, secured firewall that authenticates the token.', ['bundle' => 'nowo_wiki']);
        }

        throw $this->serverError();
    }

    /**
     * Whether the firewall's user is the token's user: {@see self::SAME} for the same object, two users of `user_class`
     * or a listed accepted class with the same usable id (audit OX-L1, JX-W1), or two users of one other class with the
     * same exact id; {@see self::DIFFERENT} otherwise. Audit OX-D1-L1: two users of different classes with the same
     * exact id where a side is not an accepted class (a security adapter) give the classes to add to
     * `accepted_user_classes` (let through with a warning in 1.x). Without the class policy (a listener built by hand),
     * the 1.24 rule: the same id.
     */
    private function match(object $securityUser, object $tokenUser): string
    {
        if ($securityUser === $tokenUser) {
            return self::SAME;
        }
        if (!$this->classes instanceof WikiUserClassPolicy) {
            $id = $this->idOf($securityUser);

            return $id !== null && $id === $this->idOf($tokenUser) ? self::SAME : self::DIFFERENT;
        }
        $a = $this->classes->idOf($securityUser);
        $b = $this->classes->idOf($tokenUser);
        if ($a !== null && $b !== null) {
            return (string) $a === (string) $b ? self::SAME : self::DIFFERENT;
        }
        $first  = WikiUserRefs::fromUser($securityUser);
        $second = WikiUserRefs::fromUser($tokenUser);
        if ($first->id === null || $first->id !== $second->id) {
            return self::DIFFERENT;
        }
        $outside = [];
        foreach ([[$securityUser, $first->userClass], [$tokenUser, $second->userClass]] as [$user, $class]) {
            if (!$this->classes->isAccepted($user) && !in_array($class, $outside, true)) {
                $outside[] = $class;
            }
        }
        if ($outside === []) {
            return self::DIFFERENT; // both accepted, one without a usable id
        }

        // Two users of one other class: the same identity domain.
        return count($outside) === 1 && $first->userClass === $second->userClass ? self::SAME : implode(', ', $outside);
    }

    /** A user's class for the log (a framework-free reference: the class it names). */
    private function classOf(object $user): string
    {
        return WikiUserRefs::fromUser($user)->userClass;
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
