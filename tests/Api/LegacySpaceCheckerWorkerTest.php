<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Doctrine\DBAL\Connection;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Security\LegacyWikiSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

use function assert;
use function gc_collect_cycles;
use function is_string;
use function memory_get_usage;
use function sprintf;

/**
 * 1.29.1 (REQ-RUNTIME-002, FrankenPHP worker mode with kernel reset false): one kernel serves every request straight
 * through the container's `http_kernel` (`handle()` + `terminate()`, the session cookie by hand), so neither the kernel's
 * boot check nor the services resetter (`kernel.reset`) ever runs between requests, as in a FrankenPHP worker with
 * `FRANKENPHP_RESET_KERNEL=false`. The host's EntityManager is never cleared either, so the firewall refreshes the
 * signed-in user to the same managed object on every request.
 *
 * The bundle's {@see LegacyWikiSpaceAccessChecker} decides alone (a host locator finds spaces
 * by slug only; `space_scope: user`). Before 1.29.1 its membership memo, keyed by that user object and cleared only by
 * `kernel.reset`, kept granting a space after the membership was revoked in the database.
 */
final class LegacySpaceCheckerWorkerTest extends ApiTestCase
{
    /** Rounds of the two-person run (4 requests each); the revocation happens half-way. */
    private const ROUNDS = 60;

    /** Memory the steady part of the run may add (bytes): the run must not keep anything per request. */
    private const MEMORY_MARGIN = 2 * 1024 * 1024;

    protected static bool $legacySpaceChecker = true;

    /** No debug collectors: they keep every request until kernel.reset, which this worker never runs. */
    protected static bool $debug = false;

    /** @var array<string, mixed> */
    protected static array $wikiConfig = ['space_scope' => 'user'];

    protected function setUp(): void
    {
        parent::setUp();
        $em = $this->em();
        $em->persist(new WikiSpace('ana-notes', 'Ana notes', WikiSpaceOwnerScope::User, 'u-ana'));
        $em->persist(new WikiSpace('bob-notes', 'Bob notes', WikiSpaceOwnerScope::User, 'u-bob'));
        $em->flush();
    }

    public function testARevokedMembershipIsRefusedOnTheNextRequestWithoutTheServicesResetter(): void
    {
        $ana = $this->signIn($this->ana);

        self::assertSame(200, $this->raw('/tools/wiki/spaces/ana-notes', $ana)->getStatusCode(), 'granted and served');
        self::assertSame(200, $this->raw('/tools/wiki/spaces/ana-notes', $ana)->getStatusCode(), 'still granted');

        $this->transfer('ana-notes', 'u-nobody');

        self::assertSame(404, $this->raw('/tools/wiki/spaces/ana-notes', $ana)->getStatusCode(), 'revoked in the database: refused on the next request');
    }

    public function testTwoPeopleInTurnNeverSeeEachOthersSpacesAndARevocationLandsAtOnce(): void
    {
        $people = ['ana' => $this->signIn($this->ana), 'bob' => $this->signIn($this->bob)];
        $logger = static::getContainer()->get('logger');
        assert($logger instanceof RecordingLogger);
        $baseline = 0;

        for ($round = 0; $round < self::ROUNDS; ++$round) {
            $moved = $round >= self::ROUNDS / 2;
            if ($round === self::ROUNDS / 2) {
                // Ana's space goes to Bob (direct SQL: no entity, no event, nothing the bundle could hear).
                $this->transfer('ana-notes', 'u-bob');
            }
            $expected = [
                'ana' => ['ana-notes' => $moved ? 404 : 200, 'bob-notes' => 404],
                'bob' => ['ana-notes' => $moved ? 200 : 404, 'bob-notes' => 200],
            ];
            foreach ($expected as $who => $spaces) {
                foreach ($spaces as $slug => $status) {
                    $response = $this->raw('/tools/wiki/spaces/' . $slug, $people[$who]);
                    self::assertSame($status, $response->getStatusCode(), sprintf('round %d: %s on %s', $round, $who, $slug));
                    if ($status === 200) {
                        self::assertStringContainsString($slug === 'ana-notes' ? 'Ana notes' : 'Bob notes', (string) $response->getContent());
                    }
                }
            }
            $logger->records = [];
            if ($round === 5) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }
        }

        gc_collect_cycles();
        self::assertLessThan(self::MEMORY_MARGIN, memory_get_usage() - $baseline, 'flat memory over the run');
    }

    /** Moves a space to another owner with plain SQL. */
    private function transfer(string $slug, string $owner): void
    {
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        assert($connection instanceof Connection);
        $table = static::getContainer()->getParameter('nowo_wiki.spaces_table');
        assert(is_string($table));
        self::assertSame(1, (int) $connection->executeStatement('UPDATE ' . $table . ' SET owner_scope_id = ? WHERE slug = ?', [$owner, $slug]));
    }

    /**
     * Signs a person in on the `main` firewall (a session cookie) without a request through the browser.
     *
     * @return array<string, string>
     */
    private function signIn(TestUserEntity $user): array
    {
        $this->client->getCookieJar()->clear();
        $this->client->loginUser($user, 'main');
        $cookies = [];
        foreach ($this->client->getCookieJar()->all() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }
        self::assertNotSame([], $cookies);

        return $cookies;
    }

    /**
     * One request straight through the container's `http_kernel`: no kernel boot check, no services resetter.
     *
     * @param array<string, string> $cookies
     */
    private function raw(string $uri, array $cookies): Response
    {
        $kernel = static::getContainer()->get('http_kernel');
        assert($kernel instanceof HttpKernelInterface && $kernel instanceof TerminableInterface);
        $request  = Request::create($uri, Request::METHOD_GET, [], $cookies);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    }
}
