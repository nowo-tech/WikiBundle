<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use DateTimeImmutable;
use Nowo\WikiBundle\Collaboration\WikiCollabStateStoreInterface;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Tests\Api\App\ApiAuditRecorder;
use Nowo\WikiBundle\Tests\Api\App\ApiCollabHub;
use Nowo\WikiBundle\Tests\Api\App\ApiCollabStore;

/**
 * 1.24 (spec 218 FR-025, audit IF-L2): with co-editing on, an API save outside the live session is refused over
 * updates no revision has, resets the log otherwise, and its events wait for the log's commit: a failure before it
 * leaves no event, no audit and no revision.
 */
final class ApiCollabWriteTest extends ApiTestCase
{
    protected static array $wikiConfig = ['collaboration' => [
        'enabled'               => true,
        'rate_limiter'          => 'limiter.wiki_comments',
        'store'                 => 'app.collab_store',
        'publisher'             => 'app.collab_hub',
        'subscriber_authorizer' => 'app.collab_hub',
    ]];

    public function testUnsavedLiveUpdatesRefuseTheSave(): void
    {
        $page = $this->page($this->eng, 'Runbook', 'one');
        $this->store()->claimSeed($page->getId(), new DateTimeImmutable(), 'u-bob');
        $this->store()->append($page->getId(), 'AAAA');

        $this->assertRefusal($this->call('PUT', '/wikis/eng/pages/' . $page->getId(), self::ANA, ['content_markdown' => 'two', 'base_revision' => 1]), 409, 'WIKI_LIVE_SESSION');
        self::assertSame(1, $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId()))['revision']);
        self::assertSame([], ApiAuditRecorder::$events);
    }

    public function testASaveResetsTheLogAndItsEventsComeAfterTheCommit(): void
    {
        $page = $this->savedLog();

        $saved = $this->json($this->call('PUT', '/wikis/eng/pages/' . $page->getId(), self::ANA, ['content_markdown' => 'two', 'base_revision' => 1]));

        self::assertSame(2, $saved['revision']);
        self::assertSame([['page_saved', 0], ['api_write', 0]], ApiAuditRecorder::$heard);
        self::assertNotSame([], ApiCollabHub::$published);
    }

    public function testAFailureBeforeTheCommitLeavesNoEventNoAuditAndNoRevision(): void
    {
        $page                      = $this->savedLog();
        ApiCollabStore::$failReset = true;

        $this->assertRefusal($this->call('PUT', '/wikis/eng/pages/' . $page->getId(), self::ANA, ['content_markdown' => 'two', 'base_revision' => 1]), 500, 'SERVER_001');

        self::assertSame([], ApiAuditRecorder::$heard);
        ApiCollabStore::$failReset = false;
        self::assertSame(1, $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId()))['revision']);
    }

    /** A page with a co-editing log whose updates are all saved. */
    private function savedLog(): WikiPage
    {
        $page  = $this->page($this->eng, 'Runbook', 'one');
        $store = $this->store();
        $store->claimSeed($page->getId(), new DateTimeImmutable(), 'u-bob');
        $store->append($page->getId(), 'AAAA');
        $store->markSaved($page->getId(), (string) $page->getCurrentRevision()?->getId());
        ApiAuditRecorder::$heard = [];

        return $page;
    }

    private function store(): WikiCollabStateStoreInterface
    {
        $store = static::getContainer()->get('app.collab_store');
        self::assertInstanceOf(WikiCollabStateStoreInterface::class, $store);

        return $store;
    }
}
