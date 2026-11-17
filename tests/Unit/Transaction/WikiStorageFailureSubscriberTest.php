<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Transaction;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverExceptionInterface;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\TransientFailureKind;
use Nowo\TransactionDoctrine\WriteUnit\ConcurrencyConflict;
use Nowo\WikiBundle\Api\Security\WikiApiRequestListener;
use Nowo\WikiBundle\Doctrine\WikiFlusher;
use Nowo\WikiBundle\Transaction\WikiStorageFailureSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

use function json_decode;

/**
 * 1.37 (Beacon spec 239): storage failures of the wiki's routes answer 409 + Retry-After (the shared policy's status; 503 opt-in), never SQL.
 */
final class WikiStorageFailureSubscriberTest extends TestCase
{
    public function testItListensBeforeTheApiAndAfterTheApplication(): void
    {
        self::assertSame(['kernel.exception' => [['onApiException', 32], ['onException', -64]]], WikiStorageFailureSubscriber::getSubscribedEvents());
    }

    public function testTheApiAnswersATransientFailureInItsOwnFormat(): void
    {
        $event = $this->event($this->apiRequest(), new TransientStorageFailure(3, 'Deadlock on SELECT secret'));
        (new WikiStorageFailureSubscriber())->onApiException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(409, $response->getStatusCode());
        self::assertSame('3', $response->headers->get('Retry-After'));
        self::assertSame(['err' => 'Service temporarily unavailable', 'ECODE' => 'SERVER_002'], json_decode((string) $response->getContent(), true));
    }

    public function testTheApiAnswersAUniqueConflictWith409(): void
    {
        $event = $this->event($this->apiRequest(), new UniqueConstraintViolationException(self::driver(), null));
        (new WikiStorageFailureSubscriber())->onApiException($event);

        self::assertSame(409, $event->getResponse()?->getStatusCode());
        self::assertSame('WIKI_CONFLICT', json_decode((string) $event->getResponse()?->getContent(), true)['ECODE'] ?? null);
    }

    public function testTheApiListenerLeavesOtherFailuresAndRoutesAlone(): void
    {
        $subscriber = new WikiStorageFailureSubscriber();
        $event      = $this->event($this->apiRequest(), new LogicException('bug'));
        $subscriber->onApiException($event);
        self::assertNull($event->getResponse());

        $event = $this->event($this->wikiRequest(), new TransientStorageFailure());
        $subscriber->onApiException($event);
        self::assertNull($event->getResponse());
    }

    /**
     * @return iterable<string, array{0: Throwable, 1: string}>
     */
    public static function transientFailures(): iterable
    {
        yield 'runner' => [new TransientStorageFailure(), '1'];
        yield 'dbal' => [new LockWaitTimeoutException(self::driver(), null), '1'];
    }

    #[DataProvider('transientFailures')]
    public function testAnInternalJsonEndpointAnswers409(Throwable $failure, string $retryAfter): void
    {
        $request = $this->wikiRequest();
        $request->headers->set('Accept', 'application/json');
        $event = $this->event($request, $failure);
        (new WikiStorageFailureSubscriber())->onException($event);

        self::assertSame(409, $event->getResponse()?->getStatusCode());
        self::assertSame($retryAfter, $event->getResponse()?->headers->get('Retry-After'));
        self::assertSame('{"error":"unavailable"}', $event->getResponse()?->getContent());
    }

    public function testAnInternalJsonEndpointAnswersAConflictWith409(): void
    {
        $request = $this->wikiRequest();
        $request->headers->set('Content-Type', 'application/json');
        $event = $this->event($request, new UniqueConstraintViolationException(self::driver(), null));
        (new WikiStorageFailureSubscriber())->onException($event);

        self::assertSame(409, $event->getResponse()?->getStatusCode());
        self::assertSame('{"error":"conflict"}', $event->getResponse()?->getContent());
    }

    public function testAPageGetsAnHttpExceptionWithTheStorageErrorAsPrevious(): void
    {
        $subscriber = new WikiStorageFailureSubscriber();
        $failure    = new TransientStorageFailure(2);
        $event      = $this->event($this->wikiRequest(), $failure);
        $subscriber->onException($event);
        $mapped = $event->getThrowable();
        self::assertInstanceOf(HttpException::class, $mapped);
        self::assertSame('2', $mapped->getHeaders()['Retry-After'] ?? null);
        self::assertSame($failure, $mapped->getPrevious());

        $conflict = new UniqueConstraintViolationException(self::driver(), null);
        $event    = $this->event($this->wikiRequest(), $conflict);
        $subscriber->onException($event);
        self::assertInstanceOf(ConflictHttpException::class, $event->getThrowable());
        self::assertStringNotContainsString('SQL', $event->getThrowable()->getMessage());
    }

    public function testOtherRoutesAndFailuresAreLeftAlone(): void
    {
        $subscriber = new WikiStorageFailureSubscriber();
        $failure    = new TransientStorageFailure();
        $event      = $this->event(new Request(attributes: ['_controller' => 'App\\Controller\\HomeController::index']), $failure);
        $subscriber->onException($event);
        self::assertSame($failure, $event->getThrowable());

        $api = $this->event($this->apiRequest(), $failure);
        $subscriber->onException($api);
        self::assertSame($failure, $api->getThrowable());

        $bug = new LogicException('bug');
        $own = $this->event($this->wikiRequest(), $bug);
        $subscriber->onException($own);
        self::assertSame($bug, $own->getThrowable());
    }

    public function testThe503OptInOfThePolicyKeepsTheOldStatusEverywhere(): void
    {
        $subscriber = new WikiStorageFailureSubscriber(new ConflictResponsePolicy(ConflictResponsePolicy::STATUS_SERVICE_UNAVAILABLE, 4));

        $api = $this->event($this->apiRequest(), new TransientStorageFailure());
        $subscriber->onApiException($api);
        self::assertSame(503, $api->getResponse()?->getStatusCode());
        self::assertSame('4', $api->getResponse()->headers->get('Retry-After'));
        self::assertSame('SERVER_002', json_decode((string) $api->getResponse()->getContent(), true)['ECODE']);

        $request = $this->wikiRequest();
        $request->headers->set('Accept', 'application/json');
        $json = $this->event($request, new TransientStorageFailure(8));
        $subscriber->onException($json);
        self::assertSame(503, $json->getResponse()?->getStatusCode());
        self::assertSame('8', $json->getResponse()->headers->get('Retry-After'));

        $page = $this->event($this->wikiRequest(), new TransientStorageFailure(6));
        $subscriber->onException($page);
        self::assertInstanceOf(HttpException::class, $page->getThrowable());
        self::assertSame(503, $page->getThrowable()->getStatusCode());
        self::assertSame('6', $page->getThrowable()->getHeaders()['Retry-After'] ?? null);

        // The unique-key answer is unchanged.
        $unique = $this->event($this->apiRequest(), new UniqueConstraintViolationException(self::driver(), null));
        $subscriber->onApiException($unique);
        self::assertSame(409, $unique->getResponse()?->getStatusCode());
    }

    public function testACommittedConflictHasNoRetryAfterOnApiJsonAndHtml(): void
    {
        $conflict   = new ConcurrencyConflict(3, TransientFailureKind::Deadlock, null, new LockWaitTimeoutException(self::driver(), null), true);
        $subscriber = new WikiStorageFailureSubscriber();

        $api = $this->event($this->apiRequest(), new RuntimeException('wrapped', 0, $conflict));
        $subscriber->onApiException($api);
        self::assertSame(409, $api->getResponse()?->getStatusCode());
        self::assertFalse($api->getResponse()->headers->has('Retry-After'));
        $body = json_decode((string) $api->getResponse()->getContent(), true);
        self::assertSame('SERVER_002', $body['ECODE']);
        self::assertStringContainsString('Reload', $body['err']);

        $request = $this->wikiRequest();
        $request->headers->set('Accept', 'application/json');
        $json = $this->event($request, $conflict);
        $subscriber->onException($json);
        self::assertSame(409, $json->getResponse()?->getStatusCode());
        self::assertFalse($json->getResponse()->headers->has('Retry-After'));
        self::assertSame('{"error":"unavailable"}', $json->getResponse()->getContent());

        $page = $this->event($this->wikiRequest(), $conflict);
        $subscriber->onException($page);
        self::assertInstanceOf(HttpException::class, $page->getThrowable());
        self::assertSame(409, $page->getThrowable()->getStatusCode());
        self::assertArrayNotHasKey('Retry-After', $page->getThrowable()->getHeaders());
        self::assertSame($conflict, $page->getThrowable()->getPrevious());

        // Not committed: the retry is invited, with the policy's fallback.
        $open  = new ConcurrencyConflict(3, TransientFailureKind::Deadlock, null, new LockWaitTimeoutException(self::driver(), null), false);
        $retry = $this->event($this->apiRequest(), $open);
        (new WikiStorageFailureSubscriber(new ConflictResponsePolicy(409, 7)))->onApiException($retry);
        self::assertSame('7', $retry->getResponse()?->headers->get('Retry-After'));
    }

    public function testTheFlusherRefusesAClosedManagerAndAbandonsCleanly(): void
    {
        $closed = $this->createMock(EntityManagerInterface::class);
        $closed->method('isOpen')->willReturn(false);
        $closed->expects(self::never())->method('flush');
        try {
            WikiFlusher::flush($closed);
            self::fail('Expected the transient failure.');
        } catch (TransientStorageFailure $failure) {
            self::assertSame(1, $failure->retryAfterSeconds());
        }

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('close');
        $lost = $this->createMock(EntityManagerInterface::class);
        $lost->method('rollback')->willThrowException(new RuntimeException('SAVEPOINT DOCTRINE_2 does not exist'));
        $lost->method('getConnection')->willReturn($connection);
        WikiFlusher::abandon($lost);
    }

    private function apiRequest(): Request
    {
        return new Request(attributes: [WikiApiRequestListener::ROUTE_FLAG => true, '_controller' => 'Nowo\\WikiBundle\\Api\\Controller\\WikiApiWriteController::create']);
    }

    private function wikiRequest(): Request
    {
        return new Request(attributes: ['_controller' => 'Nowo\\WikiBundle\\Controller\\WikiManageController::editPage']);
    }

    private function event(Request $request, Throwable $failure): ExceptionEvent
    {
        return new ExceptionEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $failure);
    }

    private static function driver(): DriverExceptionInterface
    {
        return new class('Duplicate entry for key wiki_page_space_slug', 1062) extends RuntimeException implements DriverExceptionInterface {
            public function getSQLState(): string
            {
                return '23000';
            }
        };
    }
}
