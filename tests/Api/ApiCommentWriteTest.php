<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Tests\Api\App\ApiAuditRecorder;
use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;

use function array_column;
use function array_map;
use function str_repeat;

/**
 * 1.24 (spec 218 FR-034 – FR-036): comment writes. Bob comments on eng; Ana moderates.
 */
final class ApiCommentWriteTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ApiTestHost::$grants['u-bob']['eng'] = [WikiAction::View, WikiAction::ViewComments, WikiAction::Comment, WikiAction::ResolveComment];
        $this->token('bob', 'u-bob', WikiApiLevel::Edit);
        $this->token('bob-read', 'u-bob', WikiApiLevel::Read);
        $this->token('ana-edit', 'u-ana', WikiApiLevel::Edit);
    }

    public function testThreadsRepliesAndResolve(): void
    {
        $page = $this->page($this->eng, 'Guide', 'x');
        $path = '/wikis/eng/pages/' . $page->getId() . '/threads';

        $created = $this->call('POST', $path, 'bob', ['body' => 'Question?', 'anchor' => ['v' => 1]]);
        self::assertSame(201, $created->getStatusCode(), (string) $created->getContent());
        $thread = $this->json($created);
        self::assertNull($thread['anchor']);
        self::assertSame([['field' => 'anchor', 'reason' => 'not_supported']], $thread['warnings']);
        self::assertSame('Question?', $thread['comments'][0]['body_markdown']);

        $replied = $this->json($this->call('POST', $path . '/' . $thread['id'] . '/comments', self::ANA, ['body' => 'Answer.']));
        self::assertCount(2, $replied['comments']);

        $resolved = $this->json($this->call('PATCH', $path . '/' . $thread['id'], 'bob', ['resolved' => true]));
        self::assertTrue($resolved['resolved']);
        self::assertSame(['thread.create', 'comment.create', 'thread.resolve'], array_map(static fn ($e): string => $e->operation, ApiAuditRecorder::$events));
        self::assertFalse($this->json($this->call('PATCH', $path . '/' . $thread['id'], 'bob', ['resolved' => false]))['resolved']);
        $this->assertRefusal($this->call('PATCH', $path . '/' . $thread['id'], 'bob', []), 400, 'INPUT_002');
    }

    public function testOwnCommentsAtEditAnyonesDeletedAtFull(): void
    {
        $page   = $this->page($this->eng, 'Guide', 'x');
        $path   = '/wikis/eng/pages/' . $page->getId();
        $thread = $this->json($this->call('POST', $path . '/threads', 'bob', ['body' => 'Mine']));
        $mine   = $thread['comments'][0]['id'];
        $reply  = $this->json($this->call('POST', $path . '/threads/' . $thread['id'] . '/comments', self::ANA, ['body' => 'Hers']));
        $hers   = $reply['comments'][1]['id'];

        self::assertSame('Edited', $this->json($this->call('PATCH', $path . '/comments/' . $mine, 'bob', ['body' => 'Edited']))['comments'][0]['body_markdown']);
        $this->assertRefusal($this->call('PATCH', $path . '/comments/' . $hers, 'bob', ['body' => 'x']), 403, 'ACCESS_002');
        $this->assertRefusal($this->call('PATCH', $path . '/comments/' . $hers, self::ANA . '-none', ['body' => 'x']), 401, 'OAUTH_019');
        $this->assertRefusal($this->call('DELETE', $path . '/comments/' . $hers, 'bob'), 403, 'TOKEN_001');
        // A moderator's Edit token deletes only its own; Full deletes anyone's.
        $this->assertRefusal($this->call('DELETE', $path . '/comments/' . $mine, 'ana-edit'), 403, 'TOKEN_001');
        self::assertSame(204, $this->call('DELETE', $path . '/comments/' . $mine, self::ANA)->getStatusCode());
        self::assertSame(204, $this->call('DELETE', $path . '/comments/' . $hers, 'ana-edit')->getStatusCode());
        $this->assertRefusal($this->call('DELETE', $path . '/comments/' . $hers, self::ANA), 404, 'ACCESS_001');
        $this->assertRefusal($this->call('DELETE', $path . '/comments/missing', self::ANA), 404, 'ACCESS_001');
    }

    public function testDeletingAThreadNeedsFullAndModeration(): void
    {
        $page   = $this->page($this->eng, 'Guide', 'x');
        $path   = '/wikis/eng/pages/' . $page->getId() . '/threads/';
        $thread = $this->json($this->call('POST', '/wikis/eng/pages/' . $page->getId() . '/threads', 'bob', ['body' => 'x']))['id'];

        $this->assertRefusal($this->call('DELETE', $path . $thread, 'bob'), 403, 'TOKEN_001');
        $this->assertRefusal($this->call('DELETE', $path . $thread, 'ana-edit'), 403, 'TOKEN_001');
        self::assertSame(204, $this->call('DELETE', $path . $thread, self::ANA)->getStatusCode());
        self::assertSame([], $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId() . '/threads'))['threads']);
        self::assertSame('thread.delete', ApiAuditRecorder::$events[1]->operation);
    }

    public function testAListenerThatFailsAfterTheSaveLeavesTheCommentAndItsAudit(): void
    {
        $page                        = $this->page($this->eng, 'Guide', 'x');
        ApiAuditRecorder::$failSaved = true;

        $created = $this->call('POST', '/wikis/eng/pages/' . $page->getId() . '/threads', 'bob', ['body' => 'Hi @ana']);

        self::assertSame(201, $created->getStatusCode(), (string) $created->getContent());
        self::assertSame(['thread.create'], array_map(static fn ($e): string => $e->operation, ApiAuditRecorder::$events));
        self::assertContains(['comment', 0], ApiAuditRecorder::$heard);
    }

    public function testRefusals(): void
    {
        $page = $this->page($this->eng, 'Guide', 'x');
        $path = '/wikis/eng/pages/' . $page->getId() . '/threads';

        $this->assertRefusal($this->call('POST', $path, 'bob-read', ['body' => 'x']), 403, 'TOKEN_001');
        $this->assertRefusal($this->call('POST', $path, 'bob', []), 400, 'INPUT_002');
        $this->assertRefusal($this->call('POST', $path, 'bob', ['body' => str_repeat('x', 10001)]), 413, 'INPUT_003');
        $this->assertRefusal($this->call('POST', $path . '/missing/comments', 'bob', ['body' => 'x']), 404, 'ACCESS_001');
        ApiTestHost::$grants['u-bob']['eng'] = [WikiAction::View, WikiAction::ViewComments];
        $this->assertRefusal($this->call('POST', $path, 'bob', ['body' => 'x']), 403, 'ACCESS_002');
        ApiTestHost::$wikis['eng']['archived'] = true;
        $this->assertRefusal($this->call('POST', $path, self::ANA, ['body' => 'x']), 403, 'WIKI_002');
        self::assertSame([], array_column(ApiAuditRecorder::$events, 'operation'));
    }
}
