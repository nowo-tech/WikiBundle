<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Doctrine\DBAL\Connection;
use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Tests\Api\App\ApiAuditRecorder;
use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Nowo\WikiBundle\Tests\Support\RecordingQuotaListener;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function array_column;
use function array_map;
use function count;
use function file_put_contents;
use function str_repeat;
use function sys_get_temp_dir;
use function tempnam;

/**
 * 1.24 (spec 218 FR-021 – FR-030, SC-004): page writes through the API.
 */
final class ApiPageWriteTest extends ApiTestCase
{
    public function testCreateAnswers201WithThePageAndAudits(): void
    {
        $response = $this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Runbook', 'content_markdown' => '# Steps', 'colour' => 'red']);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $page = $this->json($response);
        self::assertSame('runbook', $page['slug']);
        self::assertSame(1, $page['revision']);
        self::assertSame('# Steps', $page['content_markdown']);
        self::assertSame([['field' => 'colour', 'reason' => 'unknown']], $page['warnings']);
        self::assertCount(1, ApiAuditRecorder::$events);
        self::assertSame('page.create', ApiAuditRecorder::$events[0]->operation);
        self::assertSame($page['id'], ApiAuditRecorder::$events[0]->pageId);
        self::assertSame(['title', 'content_markdown'], ApiAuditRecorder::$events[0]->changedFields);
        self::assertSame('page_create', RecordingQuotaListener::$seen[0]->action);
        // The page's events and the audit fire once nothing is open any more.
        self::assertSame([['page_saved', 0], ['api_write', 0]], ApiAuditRecorder::$heard);
    }

    public function testCreateRefusals(): void
    {
        $this->page($this->eng, 'Runbook', 'x');
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Other', 'slug' => 'runbook']), 409, 'WIKI_003');
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Other', 'slug' => 'Bad Slug']), 400, 'INPUT_002');
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => '  ']), 400, 'INPUT_002');
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'x', 'content_markdown' => 7]), 400, 'INPUT_002');
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, '{"title":'), 400, 'INPUT_001');
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, '["title"]'), 400, 'INPUT_001');
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'x', 'content_markdown' => str_repeat('*a* ', 20000)]), 413, 'WIKI_TOO_LARGE');
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'x', 'parent' => '00000000-0000-4000-8000-000000000000']), 404, 'ACCESS_001');

        RecordingQuotaListener::$denyWith = 'Your plan allows 10 pages.';
        $refused                          = $this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Over']);
        $this->assertRefusal($refused, 403, 'PLAN_002');
        self::assertSame('Your plan allows 10 pages.', $this->json($refused)['err']);
        self::assertSame([], ApiAuditRecorder::$events);
    }

    public function testLevelPersonAndArchivedWiki(): void
    {
        $this->token('reader', 'u-ana', WikiApiLevel::Read);
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', 'reader', ['title' => 'x']), 403, 'TOKEN_001');
        $this->token('bob', 'u-bob', WikiApiLevel::Edit);
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', 'bob', ['title' => 'x']), 403, 'ACCESS_002');
        ApiTestHost::$wikis['eng']['archived'] = true;
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'x']), 403, 'WIKI_002');
    }

    public function testAParentNeedsTheEditRight(): void
    {
        $guide = $this->page($this->eng, 'Guide', 'x');
        $child = $this->json($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Child', 'parent' => $guide->getId()]));
        self::assertSame($guide->getId(), $child['parent']);

        ApiTestHost::$grants['u-ana']['eng'] = [WikiAction::View, WikiAction::Create];
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Other', 'parent' => $guide->getId()]), 403, 'ACCESS_002');
    }

    public function testTheFirstWriteOfAWikiNobodyOpenedCreatesIt(): void
    {
        ApiTestHost::$wikis['fresh']           = ['unopened' => true];
        ApiTestHost::$grants['u-ana']['fresh'] = ApiTestHost::all();

        $page = $this->json($this->call('POST', '/wikis/fresh/pages', self::ANA, ['title' => 'First']));

        self::assertSame('fresh', $page['wiki']);
        self::assertSame(['fresh'], ApiTestHost::$provisioned);
    }

    public function testUpdateFromTheCurrentRevision(): void
    {
        $page = $this->page($this->eng, 'Runbook', 'one');
        $path = '/wikis/eng/pages/' . $page->getId();

        $saved = $this->json($this->call('PUT', $path, self::ANA, ['content_markdown' => 'two', 'base_revision' => 1]));
        self::assertSame(2, $saved['revision']);
        self::assertSame('two', $saved['content_markdown']);
        self::assertSame('Runbook', $saved['title']);
        self::assertSame(['content_markdown'], ApiAuditRecorder::$events[0]->changedFields);

        // The same title and body again: 200 with that revision, nothing added, nothing audited (FR-023).
        $same = $this->json($this->call('PUT', $path, self::ANA, ['content_markdown' => 'two', 'base_revision' => 2]));
        self::assertSame(2, $same['revision']);
        self::assertCount(1, ApiAuditRecorder::$events);

        $renamed = $this->json($this->call('PUT', $path, self::ANA, ['title' => 'Runbook v2', 'content_markdown' => 'two', 'base_revision' => 2]));
        self::assertSame(3, $renamed['revision']);
        self::assertSame(['title', 'content_markdown'], ApiAuditRecorder::$events[1]->changedFields);
    }

    public function testAStaleBaseRevisionIsAConflictNamingTheCurrentOne(): void
    {
        $page = $this->page($this->eng, 'Runbook', 'one');
        $em   = $this->em();
        $this->pages()->saveRevision($em->find(WikiPage::class, $page->getId()) ?? $page, 'Runbook', 'theirs', $em->find(TestUserEntity::class, 'u-bob') ?? $this->bob, WikiPageRevision::FORMAT_MARKDOWN);

        $response = $this->call('PUT', '/wikis/eng/pages/' . $page->getId(), self::ANA, ['content_markdown' => 'mine', 'base_revision' => 1]);

        $this->assertRefusal($response, 409, 'WIKI_REVISION_CONFLICT');
        $body = $this->json($response);
        self::assertSame(2, $body['current_revision']);
        self::assertNull($body['updated_by']['id']);
        self::assertStringStartsWith('Editor ', $body['updated_by']['name']);
        self::assertIsInt($body['updated_at']);
        self::assertSame([], ApiAuditRecorder::$events);
        self::assertSame('theirs', $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId()))['content_markdown']);
    }

    public function testUpdateRefusals(): void
    {
        $page = $this->page($this->eng, 'Runbook', 'one');
        $path = '/wikis/eng/pages/' . $page->getId();

        $this->assertRefusal($this->call('PUT', $path, self::ANA, ['content_markdown' => 'two']), 422, 'INPUT_004');
        $this->assertRefusal($this->call('PUT', $path, self::ANA, ['content_markdown' => 'two', 'base_revision' => '1']), 422, 'INPUT_004');
        $this->assertRefusal($this->call('PUT', $path, self::ANA, ['base_revision' => 1]), 400, 'INPUT_002');
        $this->assertRefusal($this->call('PUT', $path, self::ANA, ['content_markdown' => ' ', 'base_revision' => 1]), 422, 'WIKI_EMPTY_BODY');
        self::assertSame(2, $this->json($this->call('PUT', $path, self::ANA, ['content_markdown' => '', 'base_revision' => 1, 'allow_empty' => true]))['revision']);
        $this->token('bob', 'u-bob', WikiApiLevel::Edit);
        $this->assertRefusal($this->call('PUT', $path, 'bob', ['content_markdown' => 'x', 'base_revision' => 2]), 403, 'ACCESS_002');
    }

    public function testRacesUnderTheLock(): void
    {
        $page = $this->page($this->eng, 'Runbook', 'one');
        $path = '/wikis/eng/pages/' . $page->getId();
        $sql  = static fn (): Connection => static::getContainer()->get('doctrine.dbal.default_connection');

        // Archived by someone else between the checks and the lock.
        ApiTestHost::$race = static function (WikiPage $target) use ($sql): void {
            $sql()->executeStatement('UPDATE wiki_pages SET archived_at = ? WHERE id = ?', ['2026-11-08 10:00:00', $target->getId()]);
        };
        $this->assertRefusal($this->call('PUT', $path, self::ANA, ['content_markdown' => 'two', 'base_revision' => 1]), 409, 'WIKI_PAGE_ARCHIVED');
        $sql()->executeStatement('UPDATE wiki_pages SET archived_at = NULL WHERE id = ?', [$page->getId()]);

        // A revision number taken behind the page's back: the write loses the race and writes nothing.
        ApiTestHost::$race = static function (WikiPage $target) use ($sql): void {
            $sql()->insert('wiki_page_revisions', ['id' => '00000000-0000-4000-8000-0000000000aa', 'page_id' => $target->getId(), 'revision_number' => 2, 'content_html' => '<p>x</p>', 'author_id' => 'u-bob', 'created_at' => '2026-11-08 10:00:00', 'format' => 'html']);
        };
        $collision = $this->call('PUT', $path, self::ANA, ['content_markdown' => 'two', 'base_revision' => 1]);
        $this->assertRefusal($collision, 409, 'WIKI_SAVE_COLLISION');
        self::assertSame('1', $collision->headers->get('Retry-After'));
        self::assertSame([], ApiAuditRecorder::$events);
    }

    public function testRestoreAppendsTheOldBody(): void
    {
        $page = $this->page($this->eng, 'Runbook', 'one');
        $path = '/wikis/eng/pages/' . $page->getId();
        $this->call('PUT', $path, self::ANA, ['content_markdown' => 'two', 'base_revision' => 1]);

        $restored = $this->json($this->call('POST', $path . '/revisions/1/restore', self::ANA, ['base_revision' => 2]));
        self::assertSame(3, $restored['revision']);
        self::assertSame('one', $restored['content_markdown']);
        self::assertSame('page.restore', ApiAuditRecorder::$events[1]->operation);

        $this->assertRefusal($this->call('POST', $path . '/revisions/1/restore', self::ANA, ['base_revision' => 2]), 409, 'WIKI_REVISION_CONFLICT');
        $this->assertRefusal($this->call('POST', $path . '/revisions/1/restore', self::ANA, []), 422, 'INPUT_004');
        $this->assertRefusal($this->call('POST', $path . '/revisions/9/restore', self::ANA, ['base_revision' => 3]), 404, 'ACCESS_001');
        $this->token('editor', 'u-ana', WikiApiLevel::Edit);
        $this->assertRefusal($this->call('POST', $path . '/revisions/1/restore', 'editor', ['base_revision' => 3]), 403, 'TOKEN_001');
    }

    public function testMoveWithTheTreesRights(): void
    {
        $a     = $this->page($this->eng, 'A', 'x');
        $b     = $this->page($this->eng, 'B', 'x');
        $child = $this->page($this->eng, 'Child', 'x', $a);

        $moved = $this->json($this->call('POST', '/wikis/eng/pages/' . $child->getId() . '/move', self::ANA, ['parent' => $b->getId(), 'index' => 0]));
        self::assertSame($b->getId(), $moved['parent']);
        self::assertSame('page.move', ApiAuditRecorder::$events[0]->operation);

        $top = $this->json($this->call('POST', '/wikis/eng/pages/' . $child->getId() . '/move', self::ANA, ['parent' => null]));
        self::assertNull($top['parent']);
        // Already there: nothing to audit.
        $this->call('POST', '/wikis/eng/pages/' . $child->getId() . '/move', self::ANA, ['parent' => null, 'index' => 99]);
        self::assertCount(2, ApiAuditRecorder::$events);

        $this->call('POST', '/wikis/eng/pages/' . $b->getId() . '/move', self::ANA, ['parent' => $a->getId()]);
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages/' . $a->getId() . '/move', self::ANA, ['parent' => $b->getId()]), 409, 'WIKI_MOVE_001');
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages/' . $a->getId() . '/move', self::ANA, ['index' => -1]), 400, 'INPUT_002');
    }

    public function testArchiveOwnPagesAtEditAnyonesAtFull(): void
    {
        $mine   = $this->page($this->eng, 'Mine', 'x');
        $theirs = $this->page($this->eng, 'Theirs', 'x', null, $this->bob);
        $this->token('editor', 'u-ana', WikiApiLevel::Edit);

        $archived = $this->json($this->call('POST', '/wikis/eng/pages/' . $mine->getId() . '/archive', 'editor'));
        self::assertTrue($archived['archived']);
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages/' . $theirs->getId() . '/archive', 'editor'), 403, 'TOKEN_001');
        self::assertTrue($this->json($this->call('POST', '/wikis/eng/pages/' . $theirs->getId() . '/archive', self::ANA))['archived']);
        // Again: nothing changes, nothing audited.
        $this->call('POST', '/wikis/eng/pages/' . $theirs->getId() . '/archive', self::ANA);
        self::assertSame(['page.archive', 'page.archive'], array_map(static fn ($e): string => $e->operation, ApiAuditRecorder::$events));

        $restored = $this->json($this->call('POST', '/wikis/eng/pages/' . $mine->getId() . '/unarchive', 'editor'));
        self::assertFalse($restored['archived']);
        self::assertSame('page_unarchive', RecordingQuotaListener::$seen[count(RecordingQuotaListener::$seen) - 1]->action);
        RecordingQuotaListener::$denyWith = 'No.';
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages/' . $theirs->getId() . '/unarchive', self::ANA), 403, 'PLAN_002');
    }

    public function testAMoveRacedByTheNewParentLeaving(): void
    {
        $a     = $this->page($this->eng, 'A', 'x');
        $child = $this->page($this->eng, 'Child', 'x');
        // Archived once its Edit right was checked, before the tree's lock.
        ApiTestHost::$race = static function (WikiPage $target) use ($a): bool {
            if ($target->getId() !== $a->getId()) {
                return false;
            }
            static::getContainer()->get('doctrine.dbal.default_connection')->executeStatement('UPDATE wiki_pages SET archived_at = ? WHERE id = ?', ['2026-11-08 10:00:00', $a->getId()]);

            return true;
        };

        $this->assertRefusal($this->call('POST', '/wikis/eng/pages/' . $child->getId() . '/move', self::ANA, ['parent' => $a->getId()]), 404, 'ACCESS_001');
    }

    public function testAnAnswerTheIdempotencyStoreCannotKeepLeavesTheWrite(): void
    {
        $page              = $this->page($this->eng, 'Runbook', 'one');
        ApiTestHost::$race = static function (): void {
            static::getContainer()->get('doctrine.dbal.default_connection')->executeStatement('DROP TABLE wiki_api_idempotency');
        };

        $response = $this->call('PUT', '/wikis/eng/pages/' . $page->getId(), self::ANA, ['content_markdown' => 'two', 'base_revision' => 1], ['Idempotency-Key' => 'k']);

        self::assertSame(200, $response->getStatusCode());
        $logger = static::getContainer()->get('logger');
        self::assertInstanceOf(RecordingLogger::class, $logger);
        self::assertContains('error: Wiki API: the idempotency store failed to keep an answer.', $logger->lines());
    }

    public function testAnUploadIsPartOfTheRequestsFingerprint(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'wiki-api');
        file_put_contents((string) $file, 'bytes');
        $server = ['HTTP_AUTHORIZATION' => 'Bearer ' . self::ANA, 'HTTP_IDEMPOTENCY_KEY' => 'k-file'];

        $this->client->request('POST', '/api/wiki/v1/wikis/eng/pages?title=x', [], ['file' => new UploadedFile((string) $file, 'a.txt', 'text/plain', null, true)], $server, '{"title":"With file"}');
        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        $this->client->request('POST', '/api/wiki/v1/wikis/eng/pages?title=x', [], [], $server, '{"title":"With file"}');
        $this->assertRefusal($this->client->getResponse(), 409, 'IDEMP_002');
    }

    public function testTheBodyLimit(): void
    {
        $page = $this->page($this->eng, 'Runbook', 'x');

        $this->assertRefusal($this->call('PUT', '/wikis/eng/pages/' . $page->getId(), self::ANA, ['content_markdown' => str_repeat('x', 1_100_000), 'base_revision' => 1]), 413, 'INPUT_003');
        self::assertSame([], array_column(ApiAuditRecorder::$events, 'operation'));
    }
}
