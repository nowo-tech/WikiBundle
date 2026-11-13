<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\PrivateEntityManager;

use Doctrine\DBAL\Connection;
use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Doctrine\WikiEntityManager;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Tests\Api\ApiTestCase;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

use function assert;
use function gc_collect_cycles;
use function json_decode;
use function json_encode;
use function memory_get_usage;
use function sprintf;

/**
 * 1.33.0 (REQ-ARCH-001 rule 6 with REQ-RUNTIME-002): with `database.private_entity_manager: true`, one kept-alive kernel
 * (`http_kernel->handle()` + `terminate()`, no services resetter, the host's manager never cleared) serves two people in
 * turn. Every wiki write goes through the private manager: a change the application left pending in its own manager is
 * never flushed, the private manager is dropped at every request end, nobody sees the other's answers, and memory stays
 * flat.
 */
final class PrivateEntityManagerWorkerTest extends ApiTestCase
{
    private const ROUNDS = 40;

    private const MEMORY_MARGIN = 2 * 1024 * 1024;

    private const BOB = 'secret-bob';

    protected static bool $debug = false;

    /** @var array<string, mixed> */
    protected static array $wikiConfig = ['database' => ['private_entity_manager' => true]];

    public function testTwoPeopleWriteAndReadInTurnWithoutTouchingTheApplicationsUnitOfWork(): void
    {
        $this->token(self::BOB, 'u-bob', WikiApiLevel::Full);
        $container = static::getContainer();
        $provider  = $container->get(WikiEntityManagerProvider::class);
        assert($provider instanceof WikiEntityManagerProvider);
        $private = $provider->get();
        self::assertInstanceOf(WikiEntityManager::class, $private);
        $host = $this->em();
        $host->persist(new TestUserEntity('pending-host-user'));
        $logger = $container->get('logger');
        assert($logger instanceof RecordingLogger);
        $baseline = 0;

        for ($round = 0; $round < self::ROUNDS; ++$round) {
            $created = $this->raw('POST', '/wikis/eng/pages', self::ANA, ['title' => sprintf('Page %d', $round), 'content_markdown' => 'Ana ' . $round]);
            self::assertSame(201, $created->getStatusCode(), $round . ': ' . $created->getContent());
            self::assertFalse($private->isCreated(), sprintf('round %d: the private manager is dropped at the request end', $round));
            $page = json_decode((string) $created->getContent(), true);
            self::assertIsArray($page);

            $read = $this->raw('GET', '/wikis/eng/pages/' . $page['id'], self::BOB);
            self::assertSame(200, $read->getStatusCode(), (string) $read->getContent());
            self::assertStringContainsString('Ana ' . $round, (string) $read->getContent());
            $refused = $this->raw('POST', '/wikis/eng/pages', self::BOB, ['title' => 'Bob ' . $round]);
            self::assertSame(403, $refused->getStatusCode(), 'Bob only reads eng: never Ana\'s rights');
            self::assertFalse($private->isCreated());

            $logger->records = [];
            if ($round === 5) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }
        }

        gc_collect_cycles();
        self::assertLessThan(self::MEMORY_MARGIN, memory_get_usage() - $baseline, 'flat memory over the run');
        self::assertCount(1, $host->getUnitOfWork()->getScheduledEntityInsertions(), 'still pending in the application\'s manager');
        $connection = $container->get('doctrine.dbal.default_connection');
        assert($connection instanceof Connection);
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM test_users WHERE id = ?', ['pending-host-user']), 'the application\'s pending change was never flushed by a wiki write');
        self::assertSame(self::ROUNDS, (int) $connection->fetchOne('SELECT COUNT(*) FROM wiki_pages'));
        $host->clear();
    }

    /**
     * One request straight through the container's `http_kernel`: no kernel boot check, no services resetter.
     *
     * @param array<string, mixed>|null $body
     */
    private function raw(string $method, string $path, string $secret, ?array $body = null): Response
    {
        $kernel = static::getContainer()->get('http_kernel');
        assert($kernel instanceof HttpKernelInterface && $kernel instanceof TerminableInterface);
        $server = ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $secret];
        if ($body !== null) {
            $server['CONTENT_TYPE'] = 'application/json';
        }
        $request  = Request::create('/api/wiki/v1' . $path, $method, [], [], [], $server, $body === null ? null : (string) json_encode($body));
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    }
}
