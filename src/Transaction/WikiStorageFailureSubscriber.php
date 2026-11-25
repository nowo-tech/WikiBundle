<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Transaction;

use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\ConflictCode;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\WriteUnit\ConcurrencyConflict;
use Nowo\WikiBundle\Api\Security\WikiApiIdempotencyListener;
use Nowo\WikiBundle\Api\Security\WikiApiRequestListener;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

use function is_string;
use function max;
use function str_starts_with;

/**
 * 1.37 (Beacon spec 239): a storage failure of a wiki route answers 409 or 503 instead of 500.
 *
 * - lock conflict ({@see TransientStorageFailure} of the wiki's runner, or a DBAL {@see RetryableException}: deadlock,
 *   lock wait timeout, serialization failure): the status of the shared {@see ConflictResponsePolicy} (409 by default;
 *   `nowo_transaction_doctrine.conflict_response.status: 503` keeps 503) and the REQ-RUNTIME-003 rule 6 code of the
 *   whole request ({@see ConflictCode::forRequest()}, the commit watermark of nowo-tech/transaction-doctrine >= 1.6):
 *   - `CONFLICT_001` (nothing committed): `Retry-After` (the runner's hint, else the policy's) + `X-Conflict-Code`;
 *   - `CONFLICT_002` (part of the request committed: a {@see ConcurrencyConflict} with `committed: true`, a lost
 *     savepoint with an unknown outcome, or an earlier commit of the same request): `X-Conflict-Code`, **no**
 *     `Retry-After` and a "reload" message, since a blind retry could repeat the part that was saved;
 * - domain conflict (a unique key refused the write of a concurrent request: a page or space slug taken meanwhile):
 *   `409 Conflict` with the wiki's own code and no `X-Conflict-Code` (not a lock conflict).
 *
 * The REST API answers in its own format (1.48: `{"err", "ECODE": "CONFLICT_001" | "CONFLICT_002" | "WIKI_CONFLICT"}`,
 * was `SERVER_002`; before the API's own exception listener turns any failure into a 500); the bundle's internal JSON
 * endpoints answer `{"error": "unavailable", "code": "CONFLICT_00x"}` or `{"error": "conflict"}`; the HTML pages get
 * the policy's HTTP exception ({@see ConflictResponsePolicy::httpExceptionFor()}, with `X-Conflict-Code`) or a
 * {@see ConflictHttpException}, rendered by the application's error page. The storage error stays the previous
 * exception (for the logs): no SQL or parameter reaches the client. Lost connections and other database errors are
 * not conflicts and are left alone.
 *
 * Only the wiki's routes: a controller of the bundle (route names are configurable) or a route of the REST API.
 */
final readonly class WikiStorageFailureSubscriber implements EventSubscriberInterface
{
    /** Controller namespace of the bundle (pages, comments, co-editing, REST API). */
    private const CONTROLLER_PREFIX = 'Nowo\\WikiBundle\\';

    /** Answer text of a conflict that already committed part of the write: no blind retry. */
    public const PARTIAL_MESSAGE = 'Part of your change may have been saved. Reload before trying again.';

    /**
     * @param ConflictResponsePolicy|null $policy The shared lock-conflict policy (status, Retry-After); 409 + 1 s when the host did not register nowo-tech/transaction-doctrine's bundle
     */
    public function __construct(private ?ConflictResponsePolicy $policy = null)
    {
    }

    /**
     * kernel.exception: at 32 for the REST API (before its own listener at 16), at -64 for the other wiki routes (after
     * the application's own listeners, before the framework's error listener at -128).
     *
     * @return array<string, list<array{0: string, 1: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => [['onApiException', 32], ['onException', -64]],
        ];
    }

    /** REST API routes: the API's JSON format. */
    public function onApiException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->attributes->getBoolean(WikiApiRequestListener::ROUTE_FLAG)) {
            return;
        }
        $failure = $event->getThrowable();
        $hint    = $this->retryAfter($failure);
        if ($hint !== null) {
            $policy = $this->policy ?? new ConflictResponsePolicy();
            $code   = ConflictCode::forRequest($request, $failure);
            if (!$code->retrySafe()) {
                // Audit SR-WK-I2: keep the idempotency claim, a retry of the same key replays this answer.
                WikiApiIdempotencyListener::keepClaim($request);
            }
            $message = $code->retrySafe() ? 'A concurrent request changed the same data. Retry later.' : self::PARTIAL_MESSAGE;
            $event->setResponse(WikiApiRequestListener::answer(new WikiApiRefusal($policy->status, $code->value, $message, [], $policy->headersFor($code, $hint))));

            return;
        }
        if ($this->isConflict($failure)) {
            $event->setResponse(WikiApiRequestListener::answer(new WikiApiRefusal(Response::HTTP_CONFLICT, 'WIKI_CONFLICT', 'A concurrent request changed the same data')));
        }
    }

    /** The other wiki routes: JSON for the internal endpoints, an HTTP exception for the pages. */
    public function onException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->attributes->getBoolean(WikiApiRequestListener::ROUTE_FLAG) || !self::isWikiRequest($request)) {
            return;
        }

        $failure = $event->getThrowable();
        $hint    = $this->retryAfter($failure);
        if ($hint !== null) {
            $policy  = $this->policy ?? new ConflictResponsePolicy();
            $code    = ConflictCode::forRequest($request, $failure);
            $headers = $policy->headersFor($code, $hint);
            if (self::wantsJson($request)) {
                $event->setResponse($this->json(['error' => 'unavailable', 'code' => $code->value], $policy->status, $headers));

                return;
            }
            $message = $code->retrySafe() ? 'The service is temporarily unavailable. Retry later.' : self::PARTIAL_MESSAGE;
            $event->setThrowable($policy->httpExceptionFor($code, $message, $failure, $hint));

            return;
        }

        if ($this->isConflict($failure)) {
            if (self::wantsJson($request)) {
                $event->setResponse($this->json(['error' => 'conflict'], Response::HTTP_CONFLICT));

                return;
            }
            $event->setThrowable(new ConflictHttpException('A concurrent request changed the same data. Reload and retry.', $failure));
        }
    }

    /**
     * The Retry-After hint (seconds) of a transient failure; null when the failure is not transient.
     */
    private function retryAfter(Throwable $failure): ?int
    {
        // Audit QN-I6: down the chain, so a host decorator that wraps a wiki failure keeps the answer.
        for ($current = $failure; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof TransientStorageFailure) {
                $hint = $current->retryAfterSeconds();

                return $hint !== null ? max(1, $hint) : $this->fallbackHint();
            }
            if ($current instanceof RetryableException) {
                return $this->fallbackHint();
            }
        }

        return null;
    }

    /**
     * 1.48: whether the chain holds a lock conflict (the runner's {@see TransientStorageFailure}, or a DBAL
     * {@see RetryableException}): the failures this subscriber answers with `CONFLICT_001` / `CONFLICT_002`. A caller
     * that wraps such a failure in a domain exception rethrows it so the answer stays the platform's.
     */
    public static function isLockConflict(Throwable $failure): bool
    {
        for ($current = $failure; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof TransientStorageFailure || $current instanceof RetryableException) {
                return true;
            }
        }

        return false;
    }

    /** The policy's own Retry-After, as the hint of a failure that carries none. */
    private function fallbackHint(): int
    {
        return ($this->policy ?? new ConflictResponsePolicy())->retryAfterSeconds;
    }

    /** Whether a unique key refused the write, anywhere down the chain. */
    private function isConflict(Throwable $failure): bool
    {
        for ($current = $failure; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof UniqueConstraintViolationException) {
                return true;
            }
        }

        return false;
    }

    /** Whether the request is one of the wiki's own controllers (not the REST API, which is flagged). */
    public static function isWikiRequest(Request $request): bool
    {
        $controller = $request->attributes->get('_controller');

        return is_string($controller) && str_starts_with($controller, self::CONTROLLER_PREFIX);
    }

    /** Whether the request asks for, or sends, JSON. */
    public static function wantsJson(Request $request): bool
    {
        return $request->getPreferredFormat() === 'json' || $request->getContentTypeFormat() === 'json';
    }

    /**
     * @param array<string, string> $body
     * @param array<string, string> $headers
     */
    private function json(array $body, int $status, array $headers = []): JsonResponse
    {
        return new JsonResponse($body, $status, $headers + ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
