<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Transaction;

use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\WikiBundle\Api\Security\WikiApiRequestListener;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

use function is_string;
use function max;
use function str_starts_with;

/**
 * 1.37 (Beacon spec 239): a storage failure of a wiki route answers 503 or 409 instead of 500.
 *
 * - transient ({@see TransientStorageFailure} of the wiki's runner, or a DBAL {@see RetryableException}: deadlock, lock
 *   wait timeout): `503 Service Unavailable` with `Retry-After` (the runner's hint, else 1 s);
 * - conflict (a unique key refused the write of a concurrent request: a page or space slug taken meanwhile): `409 Conflict`.
 *
 * The REST API answers in its own format (`{"err", "ECODE": "SERVER_002" | "WIKI_CONFLICT"}`, before the API's own
 * exception listener turns any failure into a 500); the bundle's internal JSON endpoints answer
 * `{"error": "unavailable" | "conflict"}`; the HTML pages get a {@see ServiceUnavailableHttpException} /
 * {@see ConflictHttpException}, rendered by the application's error page. The storage error stays the previous
 * exception (for the logs): no SQL or parameter reaches the client.
 *
 * Only the wiki's routes: a controller of the bundle (route names are configurable) or a route of the REST API.
 */
final readonly class WikiStorageFailureSubscriber implements EventSubscriberInterface
{
    /** Controller namespace of the bundle (pages, comments, co-editing, REST API). */
    private const CONTROLLER_PREFIX = 'Nowo\\WikiBundle\\';

    /** Retry-After when the failure carries no hint. */
    private const DEFAULT_RETRY_AFTER = 1;

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
        $retry   = $this->retryAfter($failure);
        if ($retry !== null) {
            $event->setResponse(WikiApiRequestListener::answer(new WikiApiRefusal(Response::HTTP_SERVICE_UNAVAILABLE, 'SERVER_002', 'Service temporarily unavailable', [], ['Retry-After' => (string) $retry])));

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
        if ($request->attributes->getBoolean(WikiApiRequestListener::ROUTE_FLAG) || !$this->isWikiRequest($request)) {
            return;
        }

        $failure = $event->getThrowable();
        $retry   = $this->retryAfter($failure);
        if ($retry !== null) {
            if ($this->wantsJson($request)) {
                $event->setResponse($this->json('unavailable', Response::HTTP_SERVICE_UNAVAILABLE, ['Retry-After' => (string) $retry]));

                return;
            }
            $event->setThrowable(new ServiceUnavailableHttpException($retry, 'The service is temporarily unavailable. Retry later.', $failure));

            return;
        }

        if ($this->isConflict($failure)) {
            if ($this->wantsJson($request)) {
                $event->setResponse($this->json('conflict', Response::HTTP_CONFLICT));

                return;
            }
            $event->setThrowable(new ConflictHttpException('A concurrent request changed the same data. Reload and retry.', $failure));
        }
    }

    /**
     * The Retry-After seconds of a transient failure; null when the failure is not transient.
     */
    private function retryAfter(Throwable $failure): ?int
    {
        // Audit QN-I6: down the chain, so a host decorator that wraps a wiki failure keeps the answer.
        for ($current = $failure; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof TransientStorageFailure) {
                return max(1, $current->retryAfterSeconds() ?? self::DEFAULT_RETRY_AFTER);
            }
            if ($current instanceof RetryableException) {
                return self::DEFAULT_RETRY_AFTER;
            }
        }

        return null;
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

    private function isWikiRequest(Request $request): bool
    {
        $controller = $request->attributes->get('_controller');

        return is_string($controller) && str_starts_with($controller, self::CONTROLLER_PREFIX);
    }

    private function wantsJson(Request $request): bool
    {
        return $request->getPreferredFormat() === 'json' || $request->getContentTypeFormat() === 'json';
    }

    /**
     * @param array<string, string> $headers
     */
    private function json(string $error, int $status, array $headers = []): JsonResponse
    {
        return new JsonResponse(['error' => $error], $status, $headers + ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
