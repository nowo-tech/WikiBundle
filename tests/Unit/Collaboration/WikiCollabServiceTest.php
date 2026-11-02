<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Collaboration;

use DateTimeImmutable;
use Nowo\WikiBundle\Collaboration\WikiCollabException;
use Nowo\WikiBundle\Collaboration\WikiCollabPublisherInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Nowo\WikiBundle\Collaboration\WikiCollabSubscriberAuthorizerInterface;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\InMemoryCollabStateStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

use function base64_encode;
use function json_encode;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

final class WikiCollabServiceTest extends TestCase
{
    private WikiPage $page;

    private InMemoryCollabStateStore $store;

    /** @var list<array{0: string, 1: string}> */
    private array $published = [];

    /** @var list<string> */
    private array $authorized = [];

    protected function setUp(): void
    {
        $this->page = new WikiPage(new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 't'), 'p', 'P');
        $this->page->setCurrentRevision(new WikiPageRevision($this->page, 1, '<p>x</p>', new TestUser(), 'x'));
        $this->store = new InMemoryCollabStateStore();
    }

    public function testTopicTokenAndEditorOptions(): void
    {
        $service = $this->service(['topic_prefix' => 'https://wiki.test/pages/', 'hub_url' => '/hub']);
        $service->token(new Request(), $this->page);

        self::assertSame(['https://wiki.test/pages/' . $this->page->getId()], $this->authorized);
        $options = $service->editorOptions($this->page, new TestUser('u', 'ana@x.test'), '/collab/');
        self::assertSame('/collab', $options['url']);
        self::assertSame('/hub', $options['hub_url']);
        self::assertSame('https://wiki.test/pages/' . $this->page->getId(), $options['topic']);
        self::assertSame('ana@x.test', $options['user']['name']);
        self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $options['user']['color']);
    }

    public function testTheFirstClientSeedsAnEmptyLogAndOthersWait(): void
    {
        $service = $this->service();

        $first = $service->state($this->page);
        self::assertSame(['state' => WikiCollabService::EMPTY_STATE, 'updates' => [], 'seq' => 0, 'compact' => false, 'seed' => true], $first);
        self::assertFalse($service->state($this->page)['seed']);
        self::assertSame($this->page->getCurrentRevision()?->getId(), $this->store->load($this->page->getId())->baseRevisionId);
    }

    public function testUpdatesAreStoredRelayedAndCompactedOnRequest(): void
    {
        $service = $this->service(['compact_after' => 10, 'max_log_entries' => 20]);
        $service->state($this->page);
        for ($i = 0; $i < 10; ++$i) {
            $service->update($this->json(['c' => 7, 'd' => 'AQI=']), $this->page, new TestUser());
        }

        $service->awareness($this->json(['c' => 7, 'd' => 'Aw==']), $this->page, new TestUser());
        self::assertCount(11, $this->published);
        self::assertSame('{"t":"u","c":7,"d":"AQI="}', $this->published[0][1]);
        self::assertSame('{"t":"a","c":7,"d":"Aw=="}', $this->published[10][1]);
        self::assertCount(10, $this->store->load($this->page->getId())->updates);

        $state = $service->state($this->page);
        self::assertTrue($state['compact']);
        self::assertFalse($state['seed']);
        self::assertSame(10, $state['seq']);

        $service->compact($this->json(['c' => 7, 'd' => 'BAU=', 's' => 10]), $this->page, new TestUser());
        $state = $service->state($this->page);
        self::assertSame('BAU=', $state['state']);
        self::assertSame([], $state['updates']);
    }

    public function testAFullLogRefusesUpdatesUntilCompacted(): void
    {
        $service = $this->service(['compact_after' => 10, 'max_log_entries' => 20]);
        for ($i = 0; $i < 20; ++$i) {
            $this->store->append($this->page->getId(), 'AQ==');
        }

        $this->expectExceptionObject(new WikiCollabException(429, 'log_full'));
        $service->update($this->json(['c' => 1, 'd' => 'AQ==']), $this->page, new TestUser());
    }

    public function testAStaleLogIsResetOnlyWhenIdle(): void
    {
        $id = $this->page->getId();
        $this->store->append($id, 'AQ==');
        $this->store->markSaved($id, 'old-revision');

        $busy = $this->service(['reset_after_idle' => 300])->state($this->page);
        self::assertSame(['AQ=='], $busy['updates']);

        $this->store->pages[$id]['updated'] = new DateTimeImmutable('-10 minutes');
        $idle                               = $this->service(['reset_after_idle' => 300])->state($this->page);
        self::assertSame([], $idle['updates']);
        self::assertTrue($idle['seed']);

        $service = $this->service();
        $service->saved($this->page, $this->page->getCurrentRevision() ?? self::fail('revision'));
        $this->store->append($id, 'Ag==');
        $this->store->pages[$id]['updated'] = new DateTimeImmutable('-1 day');
        self::assertSame(['Ag=='], $service->state($this->page)['updates']);
    }

    /**
     * @return iterable<string, array{0: Request, 1: int, 2: string}>
     */
    public static function invalidRequests(): iterable
    {
        yield 'not json' => [Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'text/plain'], '{"c":1,"d":"AQ=="}'), 415, 'json_required'];
        yield 'broken json' => [self::request('{"c":'), 400, 'invalid_payload'];
        yield 'not an object' => [self::request('"x"'), 400, 'invalid_payload'];
        yield 'negative client' => [self::request('{"c":-1,"d":"AQ=="}'), 400, 'invalid_payload'];
        yield 'string client' => [self::request('{"c":"1","d":"AQ=="}'), 400, 'invalid_payload'];
        yield 'empty data' => [self::request('{"c":1,"d":""}'), 400, 'invalid_payload'];
        yield 'not base64' => [self::request('{"c":1,"d":"%%%"}'), 400, 'invalid_payload'];
        yield 'bad length' => [self::request('{"c":1,"d":"A"}'), 400, 'invalid_payload'];
        yield 'bad padding' => [self::request('{"c":1,"d":"A==="}'), 400, 'invalid_payload'];
        yield 'too deep' => [self::request('{"c":1,"d":"AQ==","x":{"y":{"z":{"w":{}}}}}'), 400, 'invalid_payload'];
        yield 'decoded too large' => [self::request(json_encode(['c' => 1, 'd' => base64_encode(str_repeat('a', 1050))], JSON_THROW_ON_ERROR)), 413, 'too_large'];
        yield 'body too large' => [self::request(str_repeat(' ', 2000) . '{"c":1,"d":"AQ=="}'), 413, 'too_large'];
    }

    #[DataProvider('invalidRequests')]
    public function testInvalidRequestsAreRefused(Request $request, int $status, string $reason): void
    {
        try {
            $this->service(['max_update_bytes' => 1024])->update($request, $this->page, new TestUser());
            self::fail('accepted');
        } catch (WikiCollabException $exception) {
            self::assertSame([$status, $reason], [$exception->status, $exception->reason]);
        }

        self::assertSame([], $this->published);
    }

    public function testCompactNeedsASequenceNumberOfTheLog(): void
    {
        $service = $this->service();
        $this->store->append($this->page->getId(), 'AQ==');

        foreach ([['c' => 1, 'd' => 'AQ=='], ['c' => 1, 'd' => 'AQ==', 's' => 0], ['c' => 1, 'd' => 'AQ==', 's' => 2], ['c' => 1, 'd' => 'AQ==', 's' => '1']] as $body) {
            try {
                $service->compact($this->json($body), $this->page, new TestUser());
                self::fail('accepted');
            } catch (WikiCollabException $exception) {
                self::assertSame(400, $exception->status);
            }
        }
    }

    public function testRequestsAreRateLimitedPerUserAndPage(): void
    {
        $limiter = new RateLimiterFactory(['id' => 'wiki_collab', 'policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 minute'], new InMemoryStorage());
        $service = $this->service([], $limiter);
        $service->update($this->json(['c' => 1, 'd' => 'AQ==']), $this->page, new TestUser());
        $service->awareness($this->json(['c' => 1, 'd' => 'AQ==']), $this->page, new TestUser());

        $this->expectExceptionObject(new WikiCollabException(429, 'rate_limited'));
        $service->update($this->json(['c' => 1, 'd' => 'AQ==']), $this->page, new TestUser());
    }

    /**
     * @param array<string, mixed> $options
     */
    private function service(array $options = [], ?RateLimiterFactory $limiter = null): WikiCollabService
    {
        $publisher = new class($this->published) implements WikiCollabPublisherInterface {
            /** @param list<array{0: string, 1: string}> $published */
            public function __construct(private array &$published)
            {
            }

            public function publish(string $topic, string $message): void
            {
                $this->published[] = [$topic, $message];
            }
        };
        $authorizer = new class($this->authorized) implements WikiCollabSubscriberAuthorizerInterface {
            /** @param list<string> $authorized */
            public function __construct(private array &$authorized)
            {
            }

            public function authorize(Request $request, string $topic): void
            {
                $this->authorized[] = $topic;
            }
        };

        return new WikiCollabService($this->store, $publisher, $authorizer, $options, $limiter);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function json(array $body): Request
    {
        return self::request(json_encode($body, JSON_THROW_ON_ERROR));
    }

    private static function request(string $content): Request
    {
        return Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $content);
    }
}
