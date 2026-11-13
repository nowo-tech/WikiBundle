<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use JsonException;
use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Tests\Api\App\ApiAuditRecorder;
use Nowo\WikiBundle\Tests\Api\App\ApiCollabHub;
use Nowo\WikiBundle\Tests\Api\App\ApiCollabStore;
use Nowo\WikiBundle\Tests\Api\App\ApiKernel;
use Nowo\WikiBundle\Tests\Api\App\ApiPageRepository;
use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\RecordingQuotaListener;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

use function is_array;
use function json_decode;
use function json_encode;
use function str_replace;
use function strtoupper;

use const JSON_THROW_ON_ERROR;

/**
 * The API's functional tests: a host kernel with the firewall, a fresh schema per test, and a scripted host. Two
 * people: `u-ana` and `u-bob`; wikis `eng` (Ana edits, Bob reads) and `ops`.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected const ANA = 'secret-ana';

    protected KernelBrowser $client;

    protected WikiSpace $eng;

    protected WikiSpace $ops;

    protected TestUserEntity $ana;

    protected TestUserEntity $bob;

    /** @var array<string, mixed> */
    protected static array $apiConfig = [];

    /** @var array<string, mixed> */
    protected static array $wikiConfig = [];

    protected static bool $withFirewall = true;

    /** 1.29.1: the bundle's legacy space checker decides instead of the test host. */
    protected static bool $legacySpaceChecker = false;

    /** 1.29.1: false for memory measurements (debug collectors keep every request until kernel.reset). */
    protected static bool $debug = true;

    /** Whether this process already dropped the kernels' caches (they are reused across runs otherwise). */
    private static bool $freshCaches = false;

    public static function setUpBeforeClass(): void
    {
        // A container or router cached by an earlier run would skip the compile-time code (loader, subscribers), so the
        // coverage of a second run in the same job would miss it.
        if (!self::$freshCaches) {
            (new Filesystem())->remove((new ApiKernel())->getProjectDir());
            self::$freshCaches = true;
        }
    }

    protected function setUp(): void
    {
        ApiTestHost::reset();
        ApiAuditRecorder::$events         = [];
        ApiAuditRecorder::$heard          = [];
        ApiAuditRecorder::$failSaved      = false;
        ApiPageRepository::$missSlugs     = false;
        ApiPageRepository::$lockFails     = false;
        ApiCollabStore::$failReset        = false;
        ApiCollabHub::$published          = [];
        ApiCollabHub::$down               = false;
        RecordingQuotaListener::$seen     = [];
        RecordingQuotaListener::$denyWith = null;
        $this->client                     = static::createClient();
        $this->client->disableReboot();
        $em   = $this->em();
        $tool = new SchemaTool($em);
        $tool->dropDatabase();
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());

        $this->ana = new TestUserEntity('u-ana');
        $this->bob = new TestUserEntity('u-bob');
        $this->eng = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'owner-1');
        $this->ops = new WikiSpace('ops', 'Operations', WikiSpaceOwnerScope::Team, 'owner-2');
        foreach ([$this->ana, $this->bob, $this->eng, $this->ops] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        ApiTestHost::$wikis  = ['eng' => ['name' => 'Engineering'], 'ops' => ['name' => 'Operations', 'owner' => 'owner-2']];
        ApiTestHost::$grants = [
            'u-ana' => ['eng' => ApiTestHost::all(), 'ops' => [WikiAction::View, WikiAction::History, WikiAction::ViewComments]],
            'u-bob' => ['eng' => [WikiAction::View, WikiAction::History, WikiAction::ViewComments]],
        ];
        $this->token(self::ANA, 'u-ana', WikiApiLevel::Full);
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new ApiKernel(static::$apiConfig, static::$wikiConfig, static::$withFirewall, static::$legacySpaceChecker, static::$debug);
    }

    protected function token(string $secret, string $user, ?WikiApiLevel $level, ?WikiApiWhere $where = null): void
    {
        ApiTestHost::$tokens[$secret] = ['user' => $user, 'level' => $level, 'where' => $where ?? WikiApiWhere::everything()];
    }

    protected function em(): EntityManagerInterface
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }

    /** The manager the wiki reads and writes through (`WikiEntityManagerProvider`). */
    protected function wikiEm(): EntityManagerInterface
    {
        $provider = static::getContainer()->get(WikiEntityManagerProvider::class);
        self::assertInstanceOf(WikiEntityManagerProvider::class, $provider);

        return $provider->get();
    }

    protected function pages(): WikiPageService
    {
        $service = static::getContainer()->get(WikiPageService::class);
        self::assertInstanceOf(WikiPageService::class, $service);

        return $service;
    }

    protected function page(WikiSpace $space, string $title, string $markdown, ?WikiPage $parent = null, ?TestUserEntity $author = null): WikiPage
    {
        // 1.34: the wiki rows through the wiki's own manager (the private one with WIKI_TEST_PRIVATE_EM=1), as a host
        // does with database.private_entity_manager: wiki entities are loaded through the wiki, never the host manager.
        $em = $this->wikiEm();

        return $this->pages()->create(
            $em->find(WikiSpace::class, $space->getId()) ?? $space,
            $title,
            $markdown,
            $em->find(TestUserEntity::class, ($author ?? $this->ana)->getUserIdentifier()) ?? $this->ana,
            $parent === null ? null : $em->find(WikiPage::class, $parent->getId()),
            null,
            WikiPageRevision::FORMAT_MARKDOWN,
        );
    }

    /**
     * @param array<string, mixed>|string|null $body
     * @param array<string, string> $headers
     */
    protected function call(string $method, string $path, ?string $secret = self::ANA, array|string|null $body = null, array $headers = []): Response
    {
        $server = ['HTTP_ACCEPT' => 'application/json'];
        if ($secret !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $secret;
        }
        foreach ($headers as $name => $value) {
            $key                                                     = strtoupper(str_replace('-', '_', $name));
            $server[$key === 'CONTENT_TYPE' ? $key : 'HTTP_' . $key] = $value;
        }
        $content = is_array($body) ? json_encode($body, JSON_THROW_ON_ERROR) : $body;
        if ($content !== null && !isset($server['CONTENT_TYPE'])) {
            $server['CONTENT_TYPE'] = 'application/json';
        }
        $this->client->request($method, '/api/wiki/v1' . $path, [], [], $server, $content);
        $this->em()->clear();

        return $this->client->getResponse();
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(Response $response): array
    {
        try {
            $decoded = json_decode((string) $response->getContent(), true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            self::fail('Not JSON: ' . $response->getContent());
        }
        self::assertIsArray($decoded);

        /* @var array<string, mixed> $decoded */
        return $decoded;
    }

    protected function assertRefusal(Response $response, int $status, string $ecode): void
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame($ecode, $this->json($response)['ECODE'] ?? null, (string) $response->getContent());
    }
}
