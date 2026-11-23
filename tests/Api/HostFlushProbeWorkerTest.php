<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Doctrine\DBAL\Connection;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

use function array_filter;
use function array_values;
use function assert;
use function json_encode;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function str_contains;

use const E_USER_DEPRECATED;

/**
 * 1.49 (REQ-ARCH-001 rule 6 deprecation layer, REQ-RUNTIME-002): the host-flush probe in a kept-alive kernel
 * (`http_kernel->handle()` + `terminate()`, no services resetter, the host's manager never cleared). With the shared
 * manager every request that flushes a pending change of the application reports it once: nothing the probe remembered
 * survives the request. With the private manager nothing is reported and the pending change stays pending.
 */
final class HostFlushProbeWorkerTest extends ApiTestCase
{
    private const ROUNDS = 5;

    protected static bool $debug = false;

    /** @var array<string, mixed> */
    protected static array $wikiConfig = ['database' => ['private_entity_manager' => false]];

    public function testEachRequestReportsTheApplicationsPendingChangeAgain(): void
    {
        $provider = self::getContainer()->get(WikiEntityManagerProvider::class);
        self::assertInstanceOf(WikiEntityManagerProvider::class, $provider);
        self::assertNotNull($provider->hostFlushProbe(), 'the shared manager runs the probe');
        $host = $this->em();
        $seen = $this->capture(function () use ($host): void {
            for ($round = 0; $round < self::ROUNDS; ++$round) {
                $host->persist(new TestUserEntity(sprintf('pending-%d', $round)));
                $created = $this->raw('POST', '/wikis/eng/pages', ['title' => sprintf('Page %d', $round), 'content_markdown' => 'x']);
                self::assertSame(201, $created->getStatusCode(), (string) $created->getContent());
            }
        });

        $probe = array_values(array_filter($seen, static fn (string $message): bool => str_contains($message, 'Your pending changes to ' . TestUserEntity::class . ' were flushed by NowoWikiBundle')));
        self::assertCount(self::ROUNDS, $probe, 'one notice per request: the probe forgets at every request boundary');
        $ownPaths = array_values(array_filter($seen, static fn (string $message): bool => !str_contains($message, TestUserEntity::class) && (str_contains($message, 'nowo-tech/wiki-bundle 1.49') || str_contains($message, 'flushed by NowoWikiBundle'))));
        self::assertSame([], $ownPaths, 'the bundle\'s own code paths trigger none of the 1.49 notices');
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        assert($connection instanceof Connection);
        self::assertSame(self::ROUNDS, (int) $connection->fetchOne("SELECT COUNT(*) FROM test_users WHERE id LIKE 'pending-%'"), 'host mode is unchanged in 1.x');
    }

    /**
     * @return list<string> the deprecations triggered while `$work` ran
     */
    private function capture(callable $work): array
    {
        $seen = [];
        set_error_handler(static function (int $level, string $message) use (&$seen): bool {
            $seen[] = $message;

            return true;
        }, E_USER_DEPRECATED);
        try {
            $work();
        } finally {
            restore_error_handler();
        }

        return $seen;
    }

    /**
     * One request straight through the container's `http_kernel`: no kernel boot check, no services resetter.
     *
     * @param array<string, mixed> $body
     */
    private function raw(string $method, string $path, array $body): Response
    {
        $kernel = self::getContainer()->get('http_kernel');
        assert($kernel instanceof HttpKernelInterface && $kernel instanceof TerminableInterface);
        $server   = ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . self::ANA, 'CONTENT_TYPE' => 'application/json'];
        $request  = Request::create('/api/wiki/v1' . $path, $method, [], [], [], $server, (string) json_encode($body));
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    }
}
