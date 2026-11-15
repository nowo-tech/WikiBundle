<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Tests\Api\App\ApiAuditRecorder;

use function array_column;
use function str_repeat;

/**
 * 1.38: the page icon in the REST API — read in every page payload, written by a create or an update with the same
 * rights as the other page fields, checked against `space_icon.allowed_prefixes` before anything is written.
 */
final class ApiPageIconTest extends ApiTestCase
{
    public function testCreateWithAnIconAndReadItBack(): void
    {
        $response = $this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Launch', 'content_markdown' => 'Go', 'icon' => 'tabler:rocket']);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $page = $this->json($response);
        self::assertSame('tabler:rocket', $page['icon']);
        self::assertSame(['title', 'content_markdown', 'icon'], ApiAuditRecorder::$events[0]->changedFields);

        self::assertSame('tabler:rocket', $this->json($this->call('GET', '/wikis/eng/pages/' . $page['id']))['icon']);
        $rows = $this->json($this->call('GET', '/wikis/eng/pages'))['pages'];
        self::assertIsArray($rows);
        self::assertContains('tabler:rocket', array_column($rows, 'icon'));

        // Without an icon: null, and nothing more audited.
        $plain = $this->json($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Plain']));
        self::assertNull($plain['icon']);
        self::assertSame(['title', 'content_markdown'], ApiAuditRecorder::$events[1]->changedFields);
    }

    public function testANotAllowedIconIsRefusedBeforeAnyWrite(): void
    {
        foreach (['bi:rocket', 'tabler:Rocket', '<script>alert(1)</script>', 'tabler:rocket"><img src=x>', '🚀', 7, ['tabler:rocket'], 'tabler:' . str_repeat('a', 70)] as $bad) {
            $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Bad', 'icon' => $bad]), 400, 'INPUT_002');
        }
        self::assertSame([], ApiAuditRecorder::$events);
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne("SELECT COUNT(*) FROM wiki_pages WHERE slug = 'bad'"));

        $page = $this->page($this->eng, 'Runbook', 'one');
        $this->assertRefusal($this->call('PUT', '/wikis/eng/pages/' . $page->getId(), self::ANA, ['content_markdown' => 'two', 'base_revision' => 1, 'icon' => 'nope:x']), 400, 'INPUT_002');
        self::assertSame(1, $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId()))['revision']);
    }

    public function testUpdateSetsKeepsAndClearsTheIcon(): void
    {
        $page = $this->page($this->eng, 'Runbook', 'one');
        $path = '/wikis/eng/pages/' . $page->getId();

        // The icon alone (same body): no new revision, audited as an icon write.
        $saved = $this->json($this->call('PUT', $path, self::ANA, ['content_markdown' => 'one', 'base_revision' => 1, 'icon' => 'tabler:tool']));
        self::assertSame(1, $saved['revision']);
        self::assertSame('tabler:tool', $saved['icon']);
        self::assertSame(['icon'], ApiAuditRecorder::$events[0]->changedFields);

        // Absent: kept.
        $kept = $this->json($this->call('PUT', $path, self::ANA, ['content_markdown' => 'two', 'base_revision' => 1]));
        self::assertSame('tabler:tool', $kept['icon']);
        self::assertSame(['content_markdown'], ApiAuditRecorder::$events[1]->changedFields);

        // The same icon with a new body: only the body is audited.
        $this->call('PUT', $path, self::ANA, ['content_markdown' => 'three', 'base_revision' => 2, 'icon' => 'tabler:tool']);
        self::assertSame(['content_markdown'], ApiAuditRecorder::$events[2]->changedFields);

        // null clears it, with the body.
        $cleared = $this->json($this->call('PUT', $path, self::ANA, ['content_markdown' => 'four', 'base_revision' => 3, 'icon' => null]));
        self::assertNull($cleared['icon']);
        self::assertSame(['content_markdown', 'icon'], ApiAuditRecorder::$events[3]->changedFields);
    }

    public function testTheIconNeedsTheSameRightsAsTheOtherFields(): void
    {
        $page = $this->page($this->eng, 'Runbook', 'one');
        $this->token('reader', 'u-ana', WikiApiLevel::Read);
        $this->assertRefusal($this->call('PUT', '/wikis/eng/pages/' . $page->getId(), 'reader', ['content_markdown' => 'one', 'base_revision' => 1, 'icon' => 'tabler:tool']), 403, 'TOKEN_001');
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', 'reader', ['title' => 'x', 'icon' => 'tabler:tool']), 403, 'TOKEN_001');
        self::assertNull($this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId()))['icon']);
    }
}
