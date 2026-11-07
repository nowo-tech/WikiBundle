<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Collaboration;

use DateTimeImmutable;
use Doctrine\DBAL\Driver\Exception as DriverExceptionInterface;
use Doctrine\DBAL\Exception\DeadlockException;
use LogicException;
use Nowo\WikiBundle\Collaboration\WikiCollabException;
use Nowo\WikiBundle\Collaboration\WikiCollabLockingStoreInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabPublisherInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Nowo\WikiBundle\Collaboration\WikiCollabState;
use Nowo\WikiBundle\Collaboration\WikiCollabStateStoreInterface;
use Nowo\WikiBundle\Collaboration\WikiCollabSubscriberAuthorizerInterface;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Service\WikiRevisionCollisionException;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Stub\TestUserBase;
use Nowo\WikiBundle\Tests\Support\CountingInMemoryCollabStateStore;
use Nowo\WikiBundle\Tests\Support\InMemoryCollabStateStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
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
        self::assertMatchesRegularExpression('/^Editor [0-9a-f]{4}$/', $service->editorOptions($this->page, $named, '/c')['user']['name'], '1.13.1: names only with show_names');
        $shown = $this->service(['show_names' => true]);
        self::assertSame('Ana Lopez', $shown->editorOptions($this->page, $named, '/c')['user']['name']);
        $email = new class extends TestUserBase {
            public function getDisplayName(): string
            {
                return 'ana.lopez@corp.example';
            }
        };
        self::assertMatchesRegularExpression('/^Editor [0-9a-f]{4}$/', $shown->editorOptions($this->page, $email, '/c')['user']['name'], 'CW-I2: never an e-mail');
        $login = new class extends TestUserBase {
            public function getName(): string
            {
                return 'U1';
            }

            public function getUserIdentifier(): string
            {
                return 'u1';
            }
        };
        self::assertMatchesRegularExpression('/^Editor [0-9a-f]{4}$/', $shown->editorOptions($this->page, $login, '/c')['user']['name'], 'CW-I2: never the identifier');
        $blank = new class extends TestUserBase {
            public function getName(): string
            {
                return ' ';
            }
        };
        self::assertStringStartsWith('Editor ', $shown->editorOptions($this->page, $blank, '/c')['user']['name']);
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

    public function testACountingStoreChecksAndAppendsInOneWrite(): void
    {
        $this->store = $store = new CountingInMemoryCollabStateStore();
        $service     = $this->service(['compact_after' => 10, 'max_log_entries' => 20, 'max_log_bytes' => 12]);

        $service->update($this->json(['c' => 1, 'd' => 'AQIDBA==']), $this->page, $this->user);
        self::assertSame(1, $store->checkedAppends);
        self::assertSame('{"t":"u","c":1,"d":"AQIDBA=="}', $this->published[0][1]);

        // Over the byte budget: the log is dropped and the editors reload.
        try {
            $service->update($this->json(['c' => 1, 'd' => 'AQIDBA==']), $this->page, $this->user);
            self::fail('accepted');
        } catch (WikiCollabException $exception) {
            self::assertSame([409, 'log_reset'], [$exception->status, $exception->reason]);
        }
        self::assertTrue($store->info($this->page->getId())->isEmpty());

        // A full log: 429, nothing stored.
        $full = $this->service(['compact_after' => 1, 'max_log_entries' => 1]);
        $full->update($this->json(['c' => 1, 'd' => 'AQ==']), $this->page, $this->user);
        try {
            $full->update($this->json(['c' => 1, 'd' => 'AQ==']), $this->page, $this->user);
            self::fail('accepted');
        } catch (WikiCollabException $exception) {
            self::assertSame([429, 'log_full'], [$exception->status, $exception->reason]);
        }
        self::assertSame(0, $store->loads, 'no log data is read');
    }

    public function testUpdatesAndPresenceHaveTheirOwnBudgets(): void
    {
        $limiter = static fn (string $id, int $limit): RateLimiterFactory => new RateLimiterFactory(['id' => $id, 'policy' => 'fixed_window', 'limit' => $limit, 'interval' => '1 minute'], new InMemoryStorage());
        $service = $this->service([], $limiter('shared', 100), null, 'test-secret', $limiter('update', 1), $limiter('awareness', 1));
        $this->store->append($this->page->getId(), 'AQ==');

        $service->update($this->json(['c' => 1, 'd' => 'AQ==']), $this->page, $this->user);
        $service->awareness($this->json(['c' => 1, 'd' => 'AQ==']), $this->page, $this->user);
        foreach (['update', 'awareness'] as $action) {
            try {
                $service->{$action}($this->json(['c' => 1, 'd' => 'AQ==']), $this->page, $this->user);
                self::fail($action . ' accepted');
            } catch (WikiCollabException $exception) {
                self::assertSame([429, 'rate_limited'], [$exception->status, $exception->reason]);
            }
        }
        // The shared budget still covers the other requests.
        $service->state($this->page, $this->user);
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

    public function testPseudonymsAreKeyedAndPerPage(): void
    {
        $service = $this->service([], null, null, 'kernel-secret');
        $other   = new WikiPage(new WikiSpace('ops', 'Ops', WikiSpaceOwnerScope::Team, 't'), 'q', 'Q');
        $a       = $service->editorOptions($this->page, $this->user, '/c')['user'];
        $b       = $service->editorOptions($other, $this->user, '/c')['user'];
        self::assertSame($a, $service->editorOptions($this->page, $this->user, '/c')['user'], 'stable on a page');
        self::assertNotSame($a['name'], $b['name'], 'DA-L2: not linkable across pages');
        self::assertNotSame('Editor ' . substr(hash('sha256', $this->user->getUserIdentifier()), 6, 4), $a['name'], 'DA-L2: not an unsalted hash');
        self::assertNotSame($a, $this->service([], null, null, 'other-secret')->editorOptions($this->page, $this->user, '/c')['user']);
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function names(): iterable
    {
        yield 'real name' => ['Ana López', 'u1@corp.example', true];
        yield 'other scripts fall back (conservative)' => ['张伟', 'zw@corp.example', false];
        yield 'accented login' => ['Alópez', 'alopez', false];
        yield 'bidi override' => ["Ana\u{202E}zepol", 'u1', false];
        yield 'mathematical letters' => ['𝐚𝐥𝐨𝐩𝐞𝐳', 'alopez', false];
        yield 'circled letters' => ['ⓐⓛⓞⓟⓔⓩ', 'alopez', false];
        yield 'small caps' => ['ᴀʟᴏᴘᴇᴢ', 'alopez', false];
        yield 'leet is not detected (documented)' => ['al0pez', 'alopez', true];
        yield 'apostrophe' => ['Ana O’Brien', 'u1@corp.example', true];
        yield 'e-mail' => ['ana.lopez@corp.example', 'u1', false];
        yield 'e-mail without a dot' => ['ana@corp', 'u1', false];
        yield 'fullwidth at' => ['ana＠corp', 'u1', false];
        yield 'small at' => ['ana﹫corp', 'u1', false];
        yield 'local part' => ['ana.lopez', 'ana.lopez@corp.example', false];
        yield 'login in brackets' => ['Ana (alopez)', 'alopez', false];
        yield 'login with nbsp' => ["alopez\u{00A0}", 'alopez', false];
        yield 'login in other case' => ['ALOPEZ', 'alopez', false];
        yield 'fullwidth login' => ['ａｌｏｐｅｚ', 'alopez', false];
        yield 'local part without dots' => ['Ana Lopez', 'ana.lopez@corp.example', false];
        yield 'zero-width joiner' => ["alo\u{200D}pez", 'alopez', false];
        yield 'cyrillic lookalike' => ['аlopez', 'alopez', false];
        yield 'blank' => ["\u{00A0} ", 'u1', false];
        yield 'short login is not a substring test' => ['Bob Brown', 'bo', true];
    }

    #[DataProvider('names')]
    public function testShownNamesNeverIdentifyTheLogin(string $name, string $login, bool $safe): void
    {
        self::assertSame($safe, WikiCollabService::isSafeName($name, $login));
    }

    public function testAnEmptySecretIsRefused(): void
    {
        $this->expectException(LogicException::class);
        $this->service([], null, null, '')->editorOptions($this->page, $this->user, '/c');
    }

    public function testRequestsAreAlsoLimitedPerUserAcrossPages(): void
    {
        $perUser = new RateLimiterFactory(['id' => 'wiki_collab_user', 'policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 minute'], new InMemoryStorage());
        $service = $this->service([], null, $perUser);
        $other   = new WikiPage(new WikiSpace('ops', 'Ops', WikiSpaceOwnerScope::Team, 't'), 'q', 'Q');
        $service->state($this->page, $this->user);
        $service->state($other, $this->user);
        $service->state($other, new TestUser('u2', 'bob@x.test'));

        try {
            $service->state($this->page, $this->user);
            self::fail('CW-I9: not limited across pages');
        } catch (WikiCollabException $exception) {
            self::assertSame([429, 'rate_limited'], [$exception->status, $exception->reason]);
        }
    }

    public function testThePurgeTellsLiveEditorsToReload(): void
    {
        $this->store->append('idle', 'AQ==');
        $this->store->pages['idle']['updated'] = new DateTimeImmutable('-30 days');
        $this->store->append('live', 'AQ==');
        $service = $this->service(['topic_prefix' => 't:']);

        self::assertSame(1, $service->purge(new DateTimeImmutable('-7 days')));
        self::assertSame([['t:idle', '{"t":"r","c":0,"d":""}']], $this->published);
        self::assertArrayHasKey('live', $this->store->pages);

        $plain = $this->createMock(WikiCollabStateStoreInterface::class);
        $plain->expects(self::once())->method('purge')->willReturn(2);
        $publisher = $this->createMock(WikiCollabPublisherInterface::class);
        $publisher->expects(self::never())->method('publish');
        $authorizer = $this->createMock(WikiCollabSubscriberAuthorizerInterface::class);
        self::assertSame(2, (new WikiCollabService($plain, $publisher, $authorizer))->purge(new DateTimeImmutable()));
    }

    /**
     * Audit HO-L1, forced interleaving: a peer's update arriving between the check and the drop waits for the lock, as a
     * row lock makes it, and is still there afterwards. Without the lock the drop takes it.
     */
    public function testAnUpdateArrivingDuringASaveOutsideTheSessionIsNotLost(): void
    {
        $store = new class extends InMemoryCollabStateStore implements WikiCollabLockingStoreInterface {
            /** @var list<string> */
            public array $waiting = [];
            public bool $locked   = false;

            public function whileLocked(string $pageId, callable $work): mixed
            {
                $this->locked = true;
                $result       = $work();
                $this->locked = false;
                foreach ($this->waiting as $update) {
                    parent::append($pageId, $update);
                }

                return $result;
            }

            public function append(string $pageId, string $update): int
            {
                if ($this->locked) {
                    $this->waiting[] = $update;

                    return 0;
                }

                return parent::append($pageId, $update);
            }
        };
        $collab = new WikiCollabService($store, $this->createStub(WikiCollabPublisherInterface::class), $this->createStub(WikiCollabSubscriberAuthorizerInterface::class), secret: 'test-secret');
        $page   = $this->page;
        $store->reset($page->getId(), 'rev-1');

        $saved = $collab->saveOutsideSession(new Request(), $page, static function () use ($store, $page): void {
            self::assertTrue($store->locked, 'The save runs inside the lock.');
            $store->append($page->getId(), 'cGVlcg==');
        });

        self::assertTrue($saved);
        self::assertTrue($store->info($page->getId())->hasUnsavedUpdates(), 'The update is in the log after the drop.');
    }

    public function testASaveOutsideTheSessionOverUnsavedUpdatesRunsNothing(): void
    {
        $collab = new WikiCollabService($this->store, $this->createStub(WikiCollabPublisherInterface::class), $this->createStub(WikiCollabSubscriberAuthorizerInterface::class), secret: 'test-secret');
        $this->store->append($this->page->getId(), 'dQ==');

        self::assertFalse($collab->saveOutsideSession(new Request(), $this->page, static fn () => self::fail('Must not save.')));
    }

    /** Audit HO-M1: live editors hear of the reset after the commit, so a slow hub holds no lock. */
    public function testTheResetIsPublishedAfterTheLockIsReleased(): void
    {
        $store = new class extends InMemoryCollabStateStore implements WikiCollabLockingStoreInterface {
            public bool $locked = false;

            public function whileLocked(string $pageId, callable $work): mixed
            {
                $this->locked = true;
                $result       = $work();
                $this->locked = false;

                return $result;
            }
        };
        $lockedAtPublish = [];
        $publisher       = $this->createMock(WikiCollabPublisherInterface::class);
        $publisher->expects(self::once())->method('publish')->willReturnCallback(static function () use ($store, &$lockedAtPublish): void {
            $lockedAtPublish[] = $store->locked;
        });
        $collab = new WikiCollabService($store, $publisher, $this->createStub(WikiCollabSubscriberAuthorizerInterface::class), secret: 'test-secret');
        $store->reset($this->page->getId(), 'rev-1');

        $request = new Request();
        self::assertTrue($collab->saveOutsideSession($request, $this->page, function () use ($request): void {
            self::assertSame($this->page->getId(), $request->attributes->get(WikiCollabService::LOG_HANDLED_ATTRIBUTE));
        }));

        self::assertSame([false], $lockedAtPublish);
        self::assertTrue($store->info($this->page->getId())->isEmpty(), 'The log is reset under the lock.');
        self::assertFalse($request->attributes->has(WikiCollabService::LOG_HANDLED_ATTRIBUTE));
    }

    /** The audit's surviving mutant: an outside save empties a log with saved updates, and does so under the lock. */
    public function testAnOutsideSaveEmptiesTheLogUnderTheLock(): void
    {
        $store = new class extends InMemoryCollabStateStore implements WikiCollabLockingStoreInterface {
            public ?bool $emptyAtUnlock = null;

            public function whileLocked(string $pageId, callable $work): mixed
            {
                $result              = $work();
                $this->emptyAtUnlock = $this->info($pageId)->isEmpty();

                return $result;
            }
        };
        $collab = new WikiCollabService($store, $this->createStub(WikiCollabPublisherInterface::class), $this->createStub(WikiCollabSubscriberAuthorizerInterface::class), secret: 'test-secret');
        $store->append($this->page->getId(), 'dQ==');
        $store->markSaved($this->page->getId(), 'rev-1');
        self::assertFalse($store->info($this->page->getId())->isEmpty());

        self::assertTrue($collab->saveOutsideSession(new Request(), $this->page, static function (): void {}));

        self::assertTrue($store->emptyAtUnlock, 'The log is empty when the lock is released.');
        self::assertSame($this->page->getCurrentRevision()?->getId(), $store->info($this->page->getId())->baseRevisionId);
    }

    /** A race lost under the lock rolled back, so it is a collision for the caller. */
    public function testADeadlockUnderTheLockIsACollision(): void
    {
        $driver = new class('deadlock') extends RuntimeException implements DriverExceptionInterface {
            public function getSQLState(): ?string
            {
                return null;
            }
        };
        $collab = new WikiCollabService($this->store, $this->createStub(WikiCollabPublisherInterface::class), $this->createStub(WikiCollabSubscriberAuthorizerInterface::class), secret: 'test-secret');

        $this->expectException(WikiRevisionCollisionException::class);
        $collab->saveOutsideSession(new Request(), $this->page, static function () use ($driver): void {
            throw new DeadlockException($driver, null);
        });
    }

    /** Audit HO-M1: a hub that fails does not undo the save, on any path that resets the log, and it is logged. */
    public function testAHubFailureDoesNotUndoTheSave(): void
    {
        $publisher = $this->createStub(WikiCollabPublisherInterface::class);
        $publisher->method('publish')->willThrowException(new RuntimeException('Failed to send an update.'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('error');
        $collab = new WikiCollabService($this->store, $publisher, $this->createStub(WikiCollabSubscriberAuthorizerInterface::class), secret: 'test-secret', logger: $logger);

        $this->store->reset($this->page->getId(), 'rev-1');
        self::assertTrue($collab->saveOutsideSession(new Request(), $this->page, static function (): void {}));

        // Confirmed Replace and the other writes: through the listener, with nothing locked.
        $this->store->reset($this->page->getId(), 'rev-1');
        $collab->replaced($this->page, new WikiPageRevision($this->page, 2, '<p>x</p>', $this->user, 'x'));
        self::assertTrue($this->store->info($this->page->getId())->isEmpty());
    }

    public function testASaveThatThrowsPublishesNothing(): void
    {
        $publisher = $this->createMock(WikiCollabPublisherInterface::class);
        $publisher->expects(self::never())->method('publish');
        $collab = new WikiCollabService($this->store, $publisher, $this->createStub(WikiCollabSubscriberAuthorizerInterface::class), secret: 'test-secret');
        $this->store->reset($this->page->getId(), 'rev-1');
        $request = new Request();

        try {
            $collab->saveOutsideSession($request, $this->page, static function (): void {
                throw new RuntimeException('listener failed');
            });
            self::fail('Expected the exception.');
        } catch (RuntimeException $e) {
            self::assertSame('listener failed', $e->getMessage());
        }
        self::assertFalse($request->attributes->has(WikiCollabService::LOG_HANDLED_ATTRIBUTE));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function service(array $options = [], ?RateLimiterFactory $limiter = null, ?RateLimiterFactory $userLimiter = null, string $secret = 'test-secret', ?RateLimiterFactory $updateLimiter = null, ?RateLimiterFactory $awarenessLimiter = null): WikiCollabService
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

        return new WikiCollabService($this->store, $publisher, $authorizer, $options, $limiter, $userLimiter, $secret, $updateLimiter, $awarenessLimiter);
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
