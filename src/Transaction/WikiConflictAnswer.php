<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Transaction;

use Doctrine\DBAL\Exception\RetryableException;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\ConflictCode;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function max;

/**
 * 1.45: the REQ-RUNTIME-003 rule 6 answer of the wiki routes added in 1.45 (the favourite toggle) to a lock conflict:
 *
 * - nothing committed (`CONFLICT_001`): the policy's status (409 by default) + `Retry-After` + `X-Conflict-Code`;
 * - part of the request committed (`CONFLICT_002`, decided for the whole request with the commit watermark of
 *   nowo-tech/transaction-doctrine >= 1.6, {@see ConflictCode::forRequest()}): the same status, `X-Conflict-Code`, no
 *   `Retry-After` (a blind retry could repeat what was saved).
 *
 * Only lock conflicts (deadlock, lock wait timeout, serialization failure, a lost savepoint: the runner's
 * {@see TransientStorageFailure}, or a DBAL {@see RetryableException}) are answered; unique violations and lost
 * connections are not conflicts and are left to the caller. The answer is a response (JSON for JSON requests, plain text
 * otherwise), never an exception carrying the failure, so the 1.37 storage-failure subscriber does not rewrite it.
 * Stateless.
 */
final readonly class WikiConflictAnswer
{
    public function __construct(private ?ConflictResponsePolicy $policy = null)
    {
    }

    /**
     * The conflict answer for `$failure`, or null when it is not a lock conflict.
     */
    public function answer(Request $request, Throwable $failure): ?Response
    {
        $hint = $this->hint($failure);
        if ($hint === false) {
            return null;
        }

        $policy  = $this->policy ?? new ConflictResponsePolicy();
        $code    = ConflictCode::forRequest($request, $failure);
        $headers = $policy->headersFor($code, $hint) + ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff'];
        $message = $code->retrySafe()
            ? 'A concurrent request changed the same data. Retry later.'
            : WikiStorageFailureSubscriber::PARTIAL_MESSAGE;
        if (WikiStorageFailureSubscriber::wantsJson($request)) {
            return new JsonResponse(['error' => 'conflict', 'code' => $code->value, 'message' => $message], $policy->status, $headers);
        }

        return new Response($message, $policy->status, $headers + ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * The failure's Retry-After hint (null: the policy's), or false when it is not a lock conflict.
     */
    private function hint(Throwable $failure): int|false|null
    {
        for ($current = $failure; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof TransientStorageFailure) {
                $hint = $current->retryAfterSeconds();

                return $hint !== null ? max(1, $hint) : null;
            }
            if ($current instanceof RetryableException) {
                return null;
            }
        }

        return false;
    }
}
