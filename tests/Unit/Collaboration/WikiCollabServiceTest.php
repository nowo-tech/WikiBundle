<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Collaboration;

use DateTimeImmutable;
use Nowo\WikiBundle\Collaboration\WikiCollabException;
use Nowo\WikiBundle\Collaboration\WikiCollabPublisherInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Nowo\WikiBundle\Collaboration\WikiCollabState;
use Nowo\WikiBundle\Collaboration\WikiCollabSubscriberAuthorizerInterface;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Stub\TestUserBase;
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

    private TestUser $user;

    /** @var list<array{0: string, 1: string}> */
    private array $published = [];

    /** @var list<string> */
    private array $authorized = [];

    protected function setUp(): void
    {
        $this->page = new WikiPage(new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 't'), 'p', 'P');
        $this->page->setCurrentRevision(new WikiPageRevision($this->page, 1, '<p>x</p>', new TestUser(), 'x'));
        $this->store = new InMemoryCollabStateStore();
        $this->user  = new TestUser('u1', 'ana@x.test');
    }

    public function testTopicTokenAndEditorOptions(): void
    {
        $service = $this->service(['topic_prefix' => 'https://wiki.test/pages/', 'hub_url' => '/hub']);
        $service->token(new Request(), $this->page, $this->user);

        self::assertSame(['https://wiki.test/pages/' . $this->page->getId()], $this->authorized);
        $options = $service->editorOptions($this->page, new TestUser('u', 'ana@x.test'), '/collab/');
        self::assertSame('/collab', $options['url']);
        self::assertSame('/hub', $options['hub_url']);
        self::assertSame('https://wiki.test/pages/' . $this->page->getId(), $options['topic']);
        self::assertMatchesRegularExpression('/^Editor [0-9a-f]{4}$/', $options['user']['name'], 'CW-I2: never the identifier');
        self::assertStringNotContainsString('ana@', $options['user']['name']);
        self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $options['user']['color']);

        $named = new class extends TestUserBase {
            public function getDisplayName(): string
            {
                return ' Ana Lopez ';
            }
        };
        self::assertSame('Ana Lopez', $service->editorOptions($this->page, $named, '/c')['user']['name']);
        $blank = new class extends TestUserBase {
            public function getName(): string
            {
                return ' ';
            }
        };
        self::assertStringStartsWith('Editor ', $service->editorOptions($this->page, $blank, '/c')['user']['name']);
    }

    public function testTheTokenClaimsTheSeedAndTheStateOnlyReads(): void
    {
        $service = $this->service();
        $other   = new TestUser('u2', 'bob@x.test');

        self::assertFalse($service->state($this->page, $this->user)['seed'], 'no claim before the token');
        self::assertNull($this->store->info($this->page->getId())->updatedAt, 'GET state has no side effect');

        $service->token(new Request(), $this->page, $this->user);
        $service->token(new Request(), $this->page, $other);
        self::assertSame(['state' => WikiCollabService::EMPTY_STATE, 'updates' => [], 'seq' => 0, 'compact' => false, 'seed' => true], $service->state($this->page, $this->user));
        self::assertFalse($service->state($this->page, $other)['seed']);
        self::assertSame($this->page->getCurrentRevision()?->getId(), $this->store->info($this->page->getId())->baseRevisionId);

        $this->store->pages[$this->page->getId()]['seededAt'] = new DateTimeImmutable('-1 hour');
        self::assertFalse($service->state($this->page, $this->user)['seed'], 'a stale claim does not seed');
        $service->token(new Request(), $this->page, $other);
        self::assertTrue($service->state($this->page, $other)['seed'], 'a stale claim is taken again');
    }

    public function testUpdatesAreStoredRelayedAndCompactedOnRequest(): void
    {
        $service = $this->service(['compact_after' => 10, 'max_log_entries' => 20]);
        $service->token(new Request(), $this->page, $this->user);
        for ($i = 0; $i < 10; ++$i) {
            $service->update($this->json(['c' => 7, 'd' => 'AQI=']), $this->page, $this->user);
        }

        $service->awareness($this->json(['c' => 7, 'd' => 'Aw==']), $this->page, $this->user);
        self::assertCount(11, $this->published);
        self::assertSame('{"t":"u","c":7,"d":"AQI="}', $this->published[0][1]);
        self::assertSame('{"t":"a","c":7,"d":"Aw=="}', $this->published[10][1]);

        $state = $service->state($this->page, $this->user);
        self::assertTrue($state['compact']);
        self::assertFalse($state['seed']);
        self::assertSame(10, $state['seq']);

        $service->compact($this->json(['c' => 7, 'd' => 'BAU=', 's' => 6]), $this->page, $this->user);
        $state = $service->state($this->page, $this->user);
        self::assertSame('BAU=', $state['state']);
        self::assertCount(4, $state['updates']);

        try {
            $service->compact($this->json(['c' => 7, 'd' => 'BgY=', 's' => 5]), $this->page, $this->user);
            self::fail('CW-M1: a snapshot covering less than the current one is refused');
        } catch (WikiCollabException $exception) {
            self::assertSame(400, $exception->status);
        }

        $service->compact($this->json(['c' => 7, 'd' => 'BwY=', 's' => 10]), $this->page, $this->user);
        self::assertSame(['state' => 'BwY=', 'updates' => [], 'seq' => 10, 'compact' => false, 'seed' => false], $service->state($this->page, $this->user));
    }

    public function testAFullLogRefusesUpdatesUntilCompacted(): void
    {
        $service = $this->service(['compact_after' => 10, 'max_log_entries' => 20]);
        for ($i = 0; $i < 20; ++$i) {
            $this->store->append($this->page->getId(), 'AQ==');
        }

        $loads = $this->store->loads;
        try {
            $service->update($this->json(['c' => 1, 'd' => 'AQ==']), $this->page, $this->user);
            self::fail('accepted');
        } catch (WikiCollabException $exception) {
            self::assertSame([429, 'log_full'], [$exception->status, $exception->reason]);
        }

        self::assertSame($loads, $this->store->loads, 'CW-M1: budget checks never read the log data');
    }

    public function testALogOverItsByteBudgetIsDroppedAndEditorsReload(): void
    {
        $service = $this->service(['max_log_bytes' => 10]);
        $service->update($this->json(['c' => 1, 'd' => 'AQIDBA==']), $this->page, $this->user);

        try {
            $service->update($this->json(['c' => 1, 'd' => 'AQIDBA==']), $this->page, $this->user);
            self::fail('accepted');
        } catch (WikiCollabException $exception) {
            self::assertSame([409, 'log_reset'], [$exception->status, $exception->reason]);
        }

        self::assertTrue($this->store->info($this->page->getId())->isEmpty());
        self::assertSame('{"t":"r","c":0,"d":""}', $this->published[1][1]);
    }

    public function testEverySaveDropsTheLogWithoutReadingIt(): void
    {
        $service  = $this->service();
        $revision = $this->page->getCurrentRevision() ?? self::fail('revision');
        $service->replaced($this->page, $revision);
        self::assertSame([], $this->published, 'no session yet: nothing to reset');

        $this->store->append($this->page->getId(), 'AQ==');
        $loads = $this->store->loads;
        $service->replaced($this->page, $revision);
        self::assertTrue($this->store->info($this->page->getId())->isEmpty());
        self::assertSame($revision->getId(), $this->store->info($this->page->getId())->baseRevisionId);
        self::assertSame([['urn:nowo-wiki:page:' . $this->page->getId(), '{"t":"r","c":0,"d":""}']], $this->published);
        self::assertSame($loads, $this->store->loads);
    }

    public function testOneTabSeedsWhenTheClientSendsItsId(): void
    {
        $service = $this->service();
        $tab     = static fn (string $id): Request => Request::create('/', 'POST', [], [], [], ['HTTP_X_NOWO_COLLAB_CLIENT' => $id]);

        $service->token($tab('111'), $this->page, $this->user);
        $service->token($tab('222'), $this->page, $this->user);

        self::assertTrue($service->state($this->page, $this->user, $tab('111'))['seed']);
        self::assertFalse($service->state($this->page, $this->user, $tab('222'))['seed'], 'same user, other tab');
        self::assertFalse($service->state($this->page, $this->user, $tab('x1'))['seed'], 'an invalid id is ignored');
        self::assertFalse($service->state($this->page, $this->user)['seed']);
    }

    public function testACorruptReportResetsOnceWithinTheCap(): void
    {
        $service = $this->service(['corrupt_resets' => 2, 'corrupt_window' => 600]);
        $id      = $this->page->getId();
        $report  = fn (int $seq): Request => $this->json(['c' => 1, 's' => $seq]);

        $service->corrupt($report(0), $this->page, $this->user);
        self::assertSame([], $this->published, 'an empty log: nothing to reset');

        $seq = $this->store->append($id, 'AQ==');
        $service->corrupt($report($seq + 5), $this->page, $this->user);
        self::assertSame([], $this->published, 'a report about another log changes nothing');

        $service->corrupt($report($seq), $this->page, $this->user);
        self::assertTrue($this->store->info($id)->isEmpty());
        self::assertSame('{"t":"r","c":0,"d":""}', $this->published[0][1]);
        $service->corrupt($report($seq), $this->page, $this->user);
        self::assertCount(1, $this->published, 'idempotent: the log is already gone');

        $service->corrupt($report($this->store->append($id, 'AQ==')), $this->page, $this->user);
        self::assertCount(2, $this->published);

        try {
            $service->corrupt($report($this->store->append($id, 'AQ==')), $this->page, $this->user);
            self::fail('no cap');
        } catch (WikiCollabException $exception) {
            self::assertSame([429, 'reset_capped'], [$exception->status, $exception->reason]);
        }

        self::assertFalse($this->store->info($id)->isEmpty());
        self::assertCount(2, $this->published, 'no reset loop');
    }

    public function testACorruptReportNeedsAClientAndASequence(): void
    {
        foreach ([['s' => 1], ['c' => 1], ['c' => 1, 's' => -1], ['c' => '1', 's' => 1]] as $body) {
            try {
                $this->service()->corrupt($this->json($body), $this->page, $this->user);
                self::fail('accepted');
            } catch (WikiCollabException $exception) {
                self::assertSame(400, $exception->status);
            }
        }

        $this->expectExceptionObject(new WikiCollabException(400, 'invalid_payload'));
        $this->service()->corrupt(self::request('"x"'), $this->page, $this->user);
    }

    public function testTheManualResetDropsTheLogAndSavesKeepItFromTheSession(): void
    {
        $service = $this->service();
        $id      = $this->page->getId();
        $current = $this->page->getCurrentRevision() ?? self::fail('revision');
        $next    = new WikiPageRevision($this->page, 2, '<p>y</p>', new TestUser(), 'y');
        $this->store->reset($id, $current->getId());
        $this->store->append($id, 'AQ==');

        $service->saved($this->page, $next, $current);
        self::assertSame(['AQ=='], $this->store->load($id)->updates);
        self::assertSame($next->getId(), $this->store->info($id)->baseRevisionId);
        self::assertSame([], $this->published);

        $service->reset($this->page, $this->user);
        self::assertTrue($this->store->info($id)->isEmpty());
        self::assertSame('{"t":"r","c":0,"d":""}', $this->published[0][1]);
        $service->reset($this->page, $this->user);
        self::assertCount(1, $this->published, 'CW-L3: an empty log is not broadcast again');
        self::assertSame('wiki_collab_reset_' . $id, WikiCollabService::resetTokenId($this->page));
    }

    public function testManualResetsAreCappedLikeCorruptReports(): void
    {
        $service = $this->service(['corrupt_resets' => 2]);
        for ($i = 0; $i < 2; ++$i) {
            $this->store->append($this->page->getId(), 'AQ==');
            $service->reset($this->page, $this->user);
        }

        $this->store->append($this->page->getId(), 'AQ==');
        try {
            $service->reset($this->page, $this->user);
            self::fail('no cap');
        } catch (WikiCollabException $exception) {
            self::assertSame([429, 'reset_capped'], [$exception->status, $exception->reason]);
        }

        self::assertCount(2, $this->published, 'CW-L3: no reload loop');
    }

    public function testTheBudgetIsCheckedAgainAfterTheAppend(): void
    {
        $store = new class extends InMemoryCollabStateStore {
            public bool $stale = true;

            public function info(string $pageId): WikiCollabState
            {
                if ($this->stale) {
                    $this->stale = false;

                    return new WikiCollabState();
                }

                return parent::info($pageId);
            }
        };
        $store->append($this->page->getId(), str_repeat('A', 12));
        $this->store = $store;

        try {
            $this->service(['max_log_bytes' => 16])->update($this->json(['c' => 1, 'd' => 'AQIDBA==']), $this->page, $this->user);
            self::fail('accepted');
        } catch (WikiCollabException $exception) {
            self::assertSame([409, 'log_reset'], [$exception->status, $exception->reason]);
        }

        self::assertTrue($store->info($this->page->getId())->isEmpty());
        self::assertSame(['{"t":"r","c":0,"d":""}'], array_column($this->published, 1), 'CW-I8: the update is not relayed');
    }

    public function testAStaleLogIsResetOnTheNextTokenOnlyWhenIdle(): void
    {
        $id = $this->page->getId();
        $this->store->reset($id, 'old-revision');
        $this->store->append($id, 'AQ==');

        $service = $this->service(['reset_after_idle' => 300]);
        $service->token(new Request(), $this->page, $this->user);
        self::assertSame(['AQ=='], $service->state($this->page, $this->user)['updates']);

        $this->store->pages[$id]['updated'] = new DateTimeImmutable('-10 minutes');
        $service->token(new Request(), $this->page, $this->user);
        $state = $service->state($this->page, $this->user);
        self::assertSame([], $state['updates']);
        self::assertTrue($state['seed']);
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
            $this->service(['max_update_bytes' => 1024])->update($request, $this->page, $this->user);
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
                $service->compact($this->json($body), $this->page, $this->user);
                self::fail('accepted');
            } catch (WikiCollabException $exception) {
                self::assertSame(400, $exception->status);
            }
        }
    }

    public function testEveryRequestIsRateLimitedPerUserAndPage(): void
    {
        $limiter = new RateLimiterFactory(['id' => 'wiki_collab', 'policy' => 'fixed_window', 'limit' => 4, 'interval' => '1 minute'], new InMemoryStorage());
        $service = $this->service([], $limiter);
        $service->token(new Request(), $this->page, $this->user);
        $service->state($this->page, $this->user);
        $service->update($this->json(['c' => 1, 'd' => 'AQ==']), $this->page, $this->user);
        $service->awareness($this->json(['c' => 1, 'd' => 'AQ==']), $this->page, $this->user);
        $service->state($this->page, new TestUser('other', 'other@x.test'));

        foreach ([fn (): array => $service->state($this->page, $this->user), fn () => $service->token(new Request(), $this->page, $this->user)] as $call) {
            try {
                $call();
                self::fail('not limited');
            } catch (WikiCollabException $exception) {
                self::assertSame([429, 'rate_limited'], [$exception->status, $exception->reason]);
            }
        }
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
