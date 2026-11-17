<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Transaction;

use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponseContext;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponseFormatterInterface;
use Nowo\WikiBundle\Api\Security\WikiApiIdempotencyListener;
use Nowo\WikiBundle\Api\Security\WikiApiRequestListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 1.42: shapes the lock-conflict answer of nowo-tech/transaction-doctrine's responder for the wiki's own routes.
 *
 * A host that lists wiki routes in `nowo_transaction_doctrine.controller_routes` gets the wiki's JSON instead of the
 * library's `{"error": "concurrency_conflict"}`: the REST API answers `{"err", "ECODE": "SERVER_002"}`, the internal JSON
 * endpoints `{"error": "unavailable"}`. The status, `Retry-After` (none when the conflict already committed) and
 * `Cache-Control` stay the responder's. The wiki's requests are recognised the way {@see WikiStorageFailureSubscriber}
 * does; the HTML pages and every other request fall through (`null`).
 *
 * Stateless.
 */
final readonly class WikiStorageFailureConflictFormatter implements ConflictResponseFormatterInterface
{
    public function format(Request $request, ConflictResponseContext $context): ?Response
    {
        if ($request->attributes->getBoolean(WikiApiRequestListener::ROUTE_FLAG)) {
            if ($context->committed) {
                // Audit SR-WK-I2: keep the idempotency claim, a retry of the same key replays this answer.
                WikiApiIdempotencyListener::keepClaim($request);
            }

            return new JsonResponse(['err' => $context->message, 'ECODE' => 'SERVER_002'], $context->status);
        }
        if (WikiStorageFailureSubscriber::isWikiRequest($request) && WikiStorageFailureSubscriber::wantsJson($request)) {
            // Audit SR-WK-I1: the subscriber's JSON answer sets nosniff too.
            return new JsonResponse(['error' => 'unavailable'], $context->status, ['X-Content-Type-Options' => 'nosniff']);
        }

        return null;
    }
}
