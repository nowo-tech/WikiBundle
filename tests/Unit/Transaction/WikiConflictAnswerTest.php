<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Transaction;

use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\TransientFailureKind;
use Nowo\TransactionDoctrine\WriteUnit\ConcurrencyConflict;
use Nowo\WikiBundle\Transaction\WikiConflictAnswer;
use Nowo\WikiBundle\Transaction\WikiStorageFailureSubscriber;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function json_decode;

/**
 * 1.45: {@see WikiConflictAnswer} — REQ-RUNTIME-003 rule 6 for the routes added in 1.45.
 */
final class WikiConflictAnswerTest extends TestCase
{
    public function testNothingCommittedIsConflict001WithRetryAfter(): void
    {
        $answer = (new WikiConflictAnswer(new ConflictResponsePolicy(409, 3)))->answer($this->json(), new TransientStorageFailure(null, 'lock'));

        self::assertInstanceOf(Response::class, $answer);
        self::assertSame(409, $answer->getStatusCode());
        self::assertSame('CONFLICT_001', $answer->headers->get('X-Conflict-Code'));
        self::assertSame('3', $answer->headers->get('Retry-After'));
        self::assertStringContainsString('no-store', (string) $answer->headers->get('Cache-Control'));
        self::assertSame(['error' => 'conflict', 'code' => 'CONFLICT_001', 'message' => 'A concurrent request changed the same data. Retry later.'], json_decode((string) $answer->getContent(), true));

        // The failure's own hint wins (at least 1 s); a DBAL retryable exception takes the policy's.
        $answer = (new WikiConflictAnswer())->answer(Request::create('/'), new RuntimeException('wrapped', 0, new TransientStorageFailure(0, 'lock')));
        self::assertInstanceOf(Response::class, $answer);
        self::assertSame('1', $answer->headers->get('Retry-After'));
        self::assertStringContainsString('text/plain', (string) $answer->headers->get('Content-Type'));
        $answer = (new WikiConflictAnswer(new ConflictResponsePolicy(503, 7)))->answer(Request::create('/'), new DeadlockException($this->createStub(DriverException::class), null));
        self::assertInstanceOf(Response::class, $answer);
        self::assertSame(503, $answer->getStatusCode());
        self::assertSame('7', $answer->headers->get('Retry-After'));
    }

    public function testPartCommittedIsConflict002WithoutRetryAfter(): void
    {
        $failure = new ConcurrencyConflict(1, TransientFailureKind::Deadlock, 2, new RuntimeException('deadlock'), true);
        $answer  = (new WikiConflictAnswer())->answer($this->json(), $failure);

        self::assertInstanceOf(Response::class, $answer);
        self::assertSame('CONFLICT_002', $answer->headers->get('X-Conflict-Code'));
        self::assertNull($answer->headers->get('Retry-After'));
        self::assertSame(WikiStorageFailureSubscriber::PARTIAL_MESSAGE, json_decode((string) $answer->getContent(), true)['message']);
    }

    public function testUniqueViolationsAndOtherFailuresAreNoConflict(): void
    {
        self::assertNull((new WikiConflictAnswer())->answer($this->json(), new UniqueConstraintViolationException($this->createStub(DriverException::class), null)));
        self::assertNull((new WikiConflictAnswer())->answer($this->json(), new RuntimeException('boom')));
    }

    private function json(): Request
    {
        $request = Request::create('/');
        $request->headers->set('Accept', 'application/json');

        return $request;
    }
}
