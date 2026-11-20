<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Doctrine\DBAL\Connection;
use Nowo\TransactionDoctrine\TransientFailureKind;
use Nowo\TransactionDoctrine\WriteUnit\ConcurrencyConflict;
use Nowo\WikiBundle\Api\Command\WikiApiPurgeIdempotencyCommand;
use Nowo\WikiBundle\Api\Hook\WikiApiIdempotencyStoreInterface;
use Nowo\WikiBundle\Api\Security\WikiApiIdempotencyListener;
use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Tests\Api\App\ApiAuditRecorder;
use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

use function hash;

/**
 * 1.24 (spec 218 FR-032): `Idempotency-Key` on writes.
 */
final class ApiIdempotencyTest extends ApiTestCase
{
    public function testARetryGetsTheFirstAnswerWithoutRunningTwice(): void
    {
        $first  = $this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Runbook'], ['Idempotency-Key' => 'k-1']);
        $replay = $this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Runbook'], ['Idempotency-Key' => 'k-1']);

        self::assertSame(201, $replay->getStatusCode());
        self::assertSame((string) $first->getContent(), (string) $replay->getContent());
        self::assertSame('true', $replay->headers->get('Idempotency-Replayed'));
        self::assertNull($first->headers->get('Idempotency-Replayed'));
        self::assertCount(1, ApiAuditRecorder::$events);
        self::assertSame('99', $replay->headers->get('X-RateLimit-Remaining'));

        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Other'], ['Idempotency-Key' => 'k-1']), 409, 'IDEMP_002');
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Other'], ['Idempotency-Key' => '']), 400, 'IDEMP_003');
        // Not JSON: fingerprinted as sent, refused by the endpoint, and not kept.
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, '{"title":', ['Idempotency-Key' => 'k-bad']), 400, 'INPUT_001');
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, '{"title":', ['Idempotency-Key' => 'k-bad']), 400, 'INPUT_001');
    }

    public function testARefusalIsNotKept(): void
    {
        ApiTestHost::$grants['u-ana']['eng'] = [];
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Runbook'], ['Idempotency-Key' => 'k-2']), 404, 'ACCESS_001');
        ApiTestHost::$grants['u-ana']['eng'] = ApiTestHost::all();

        self::assertSame(201, $this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Runbook'], ['Idempotency-Key' => 'k-2'])->getStatusCode());
    }

    public function testAReplayChecksReachAgain(): void
    {
        $page                     = $this->json($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Runbook'], ['Idempotency-Key' => 'k-3']));
        ApiTestHost::$hiddenPages = ['runbook'];

        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Runbook'], ['Idempotency-Key' => 'k-3']), 404, 'ACCESS_001');
        self::assertNotNull($page['id']);
    }

    public function testInFlightAbandonedAndForgottenClaims(): void
    {
        $scope = WikiApiIdempotencyListener::scope(new WikiApiPrincipal(new TestUserEntity('u-ana'), WikiApiLevel::Full, WikiApiWhere::everything(), 'tok-u-ana', 'n'));
        $store = static::getContainer()->get(WikiApiIdempotencyStoreInterface::class);
        self::assertInstanceOf(WikiApiIdempotencyStoreInterface::class, $store);
        $this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Runbook'], ['Idempotency-Key' => 'k-4']);
        $sql = $this->sql();
        $sql->executeStatement('UPDATE wiki_api_idempotency SET status = NULL, body = NULL WHERE key_hash = ?', [hash('sha256', 'k-4')]);

        $busy = $this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Runbook'], ['Idempotency-Key' => 'k-4']);
        $this->assertRefusal($busy, 409, 'IDEMP_001');
        self::assertSame('1', $busy->headers->get('Retry-After'));

        // A claim nobody finished for five minutes is taken over.
        $sql->executeStatement('UPDATE wiki_api_idempotency SET created_at = ? WHERE key_hash = ?', ['2020-01-01 00:00:00', hash('sha256', 'k-4')]);
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Runbook'], ['Idempotency-Key' => 'k-4']), 409, 'WIKI_003');

        $store->forget($scope);
        self::assertSame(0, (int) $sql->fetchOne('SELECT COUNT(*) FROM wiki_api_idempotency'));
    }

    /**
     * Audit SR2-WK-L1: a REST write that already committed before a conflict keeps its claim and a retry with the same
     * key replays the stored 409 (no second write); one that did not commit releases it and the retry runs.
     */
    public function testACommittedConflictKeepsTheClaimAndTheRetryReplaysIt(): void
    {
        $page = $this->page($this->eng, 'Runbook', 'one');
        $path = '/wikis/eng/pages/' . $page->getId();
        $body = ['content_markdown' => 'two', 'base_revision' => 1];
        $fail = static function (bool $committed): void {
            ApiTestHost::$race = static function () use ($committed): never {
                throw new ConcurrencyConflict(2, TransientFailureKind::Deadlock, null, new RuntimeException('SQL secret'), $committed);
            };
        };

        $fail(true);
        $first = $this->call('PUT', $path, self::ANA, $body, ['Idempotency-Key' => 'k-c']);
        $this->assertRefusal($first, 409, 'CONFLICT_002');
        self::assertSame('CONFLICT_002', $first->headers->get('X-Conflict-Code'));
        self::assertNull($first->headers->get('Retry-After'));
        self::assertStringContainsString('Reload', (string) $first->getContent());

        $retry = $this->call('PUT', $path, self::ANA, $body, ['Idempotency-Key' => 'k-c']);
        self::assertSame(409, $retry->getStatusCode());
        self::assertSame('true', $retry->headers->get('Idempotency-Replayed'));
        self::assertSame('CONFLICT_002', $retry->headers->get('X-Conflict-Code'), 'the replay keeps the conflict code');
        self::assertNull($retry->headers->get('Retry-After'));
        self::assertSame((string) $first->getContent(), (string) $retry->getContent());

        // Not committed: the claim is released and the same key runs again.
        $fail(false);
        $open = $this->call('PUT', $path, self::ANA, $body, ['Idempotency-Key' => 'k-u']);
        $this->assertRefusal($open, 409, 'CONFLICT_001');
        self::assertSame('CONFLICT_001', $open->headers->get('X-Conflict-Code'));
        self::assertSame('1', $open->headers->get('Retry-After'));
        self::assertSame(200, $this->call('PUT', $path, self::ANA, $body, ['Idempotency-Key' => 'k-u'])->getStatusCode());
    }

    public function testExpiredAnswersArePurged(): void
    {
        $this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Runbook'], ['Idempotency-Key' => 'k-5']);
        $this->sql()->executeStatement('UPDATE wiki_api_idempotency SET expires_at = ?', ['2020-01-01 00:00:00']);

        $tester = new CommandTester(static::getContainer()->get(WikiApiPurgeIdempotencyCommand::class));
        $tester->execute([]);

        self::assertStringContainsString('Deleted 1 expired answer(s).', $tester->getDisplay());
        self::assertSame(0, (int) $this->sql()->fetchOne('SELECT COUNT(*) FROM wiki_api_idempotency'));
    }

    public function testAStoreFailureRefusesTheWrite(): void
    {
        $this->sql()->executeStatement('DROP TABLE wiki_api_idempotency');

        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Runbook'], ['Idempotency-Key' => 'k-6']), 503, 'IDEMP_004');
        self::assertSame([], ApiAuditRecorder::$events);
    }

    private function sql(): Connection
    {
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }
}
