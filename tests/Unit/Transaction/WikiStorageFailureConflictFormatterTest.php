<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Transaction;

use Doctrine\DBAL\Driver\Exception as DriverExceptionInterface;
use Doctrine\DBAL\Exception\DeadlockException;
use Nowo\TransactionDoctrine\ConflictCode;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponseContext;
use Nowo\WikiBundle\Api\Security\WikiApiRequestListener;
use Nowo\WikiBundle\Transaction\WikiStorageFailureConflictFormatter;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

use function json_decode;

/**
 * 1.42: the formatter answers the wiki's own routes in the wiki's JSON shapes and lets everything else fall through.
 */
final class WikiStorageFailureConflictFormatterTest extends TestCase
{
    public function testTheRestApiGetsItsErrFormat(): void
    {
        $request  = new Request(attributes: [WikiApiRequestListener::ROUTE_FLAG => true]);
        $response = (new WikiStorageFailureConflictFormatter())->format($request, $this->context(409));

        self::assertSame(409, $response?->getStatusCode());
        self::assertSame(['err' => 'Retry later.', 'ECODE' => 'CONFLICT_001'], json_decode((string) $response->getContent(), true));

        $committed = (new WikiStorageFailureConflictFormatter())->format(new Request(attributes: [WikiApiRequestListener::ROUTE_FLAG => true]), $this->context(409, true));
        self::assertSame('CONFLICT_002', json_decode((string) $committed?->getContent(), true)['ECODE']);
    }

    public function testTheRequestLevelCodeOfTheResponderWins(): void
    {
        // An earlier commit of the request: the responder passes CONFLICT_002 although the unit itself did not commit.
        $request  = new Request(attributes: [WikiApiRequestListener::ROUTE_FLAG => true]);
        $context  = new ConflictResponseContext(409, null, 'concurrency_conflict_partial', 'Reload.', null, new RuntimeException('x'), false, ConflictCode::Committed);
        $response = (new WikiStorageFailureConflictFormatter())->format($request, $context);

        self::assertSame('CONFLICT_002', json_decode((string) $response?->getContent(), true)['ECODE']);
        self::assertTrue(WikiApiRequestListener::wroteAnyway($request));
    }

    public function testAnInternalJsonEndpointGetsTheErrorShapeWithThePolicyStatus(): void
    {
        $request = new Request(attributes: ['_controller' => 'Nowo\\WikiBundle\\Controller\\WikiManageController::editPage']);
        $request->headers->set('Accept', 'application/json');
        $response = (new WikiStorageFailureConflictFormatter())->format($request, $this->context(503));

        self::assertSame(503, $response?->getStatusCode());
        self::assertSame('{"error":"unavailable","code":"CONFLICT_001"}', $response->getContent());
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function testACommittedRestWriteKeepsItsIdempotencyClaim(): void
    {
        $formatter = new WikiStorageFailureConflictFormatter();

        $committed = new Request(attributes: [WikiApiRequestListener::ROUTE_FLAG => true]);
        $formatter->format($committed, $this->context(409, true));
        self::assertTrue(WikiApiRequestListener::wroteAnyway($committed));

        $open = new Request(attributes: [WikiApiRequestListener::ROUTE_FLAG => true]);
        $formatter->format($open, $this->context(409));
        self::assertFalse(WikiApiRequestListener::wroteAnyway($open));
    }

    public function testPagesAndOtherRoutesFallThrough(): void
    {
        $formatter = new WikiStorageFailureConflictFormatter();

        $page = new Request(attributes: ['_controller' => 'Nowo\\WikiBundle\\Controller\\WikiManageController::editPage']);
        self::assertNull($formatter->format($page, $this->context(409)));

        $other = new Request(attributes: ['_controller' => 'App\\Controller\\HomeController::index']);
        $other->headers->set('Accept', 'application/json');
        self::assertNull($formatter->format($other, $this->context(409)));
    }

    private function context(int $status, bool $committed = false): ConflictResponseContext
    {
        $driver = new class('Deadlock') extends RuntimeException implements DriverExceptionInterface {
            public function getSQLState(): string
            {
                return '40001';
            }
        };

        return new ConflictResponseContext($status, 1, 'concurrency_conflict', 'Retry later.', 3, new DeadlockException($driver, null), $committed);
    }
}
