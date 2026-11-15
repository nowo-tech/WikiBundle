<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Markdown\WikiViewerMarkdownView;
use Nowo\WikiBundle\Tests\Api\App\ApiTestFiles;
use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;

use function array_column;
use function array_search;
use function str_repeat;
use function substr_count;

/**
 * 1.24 (spec 218 FR-010, FR-012 – FR-014, SC-002): page reads, the source for editors with an Edit token, the reader's
 * Markdown for everyone else, nothing hidden in either.
 */
final class ApiPageReadTest extends ApiTestCase
{
    public function testListsCarryMetadataOnlyAndOnlyReadablePages(): void
    {
        $guide   = $this->page($this->eng, 'Guide', 'one');
        $install = $this->page($this->eng, 'Install', 'two', $guide);
        $this->page($this->eng, 'Secret', 'x');
        $this->page($this->ops, 'Elsewhere', 'x');
        ApiTestHost::$hiddenPages = ['secret'];

        $body = $this->json($this->call('GET', '/wikis/eng/pages'));
        self::assertTrue($body['last_page']);
        self::assertEqualsCanonicalizing(['Guide', 'Install'], array_column($body['pages'], 'title'));
        $row = $body['pages'][array_search('Install', array_column($body['pages'], 'title'), true)];
        self::assertSame($install->getId(), $row['id']);
        self::assertSame('eng', $row['wiki']);
        self::assertSame($guide->getId(), $row['parent']);
        self::assertSame(1, $row['revision']);
        self::assertSame(['id' => 'api-u-ana', 'name' => $row['updated_by']['name']], $row['updated_by']);
        self::assertStringStartsWith('Editor ', $row['updated_by']['name']);
        self::assertArrayNotHasKey('content_markdown', $row);

        self::assertSame(['Install'], array_column($this->json($this->call('GET', '/wikis/eng/pages?parent=' . $guide->getId()))['pages'], 'title'));
        self::assertSame(['Guide'], array_column($this->json($this->call('GET', '/wikis/eng/pages?parent=root'))['pages'], 'title'));
        self::assertSame([], $this->json($this->call('GET', '/wikis/eng/pages?updated_since=4102444800000'))['pages']);
        $this->assertRefusal($this->call('GET', '/wikis/eng/pages?parent=00000000-0000-4000-8000-000000000000'), 404, 'ACCESS_001');
        $this->assertRefusal($this->call('GET', '/wikis/eng/pages?updated_since=soon'), 400, 'INPUT_002');
    }

    public function testAnotherPersonsIdIsNeverShown(): void
    {
        $this->page($this->eng, 'Guide', 'one');
        $this->token('bob', 'u-bob', WikiApiLevel::Read);

        $row = $this->json($this->call('GET', '/wikis/eng/pages', 'bob'))['pages'][0];
        self::assertNull($row['updated_by']['id']);
        self::assertStringStartsWith('Editor ', $row['updated_by']['name']);
    }

    public function testAWikiNobodyOpenedHasNoPages(): void
    {
        ApiTestHost::$wikis['new'] = ['unopened' => true];

        self::assertSame(['pages' => [], 'last_page' => true], $this->json($this->call('GET', '/wikis/new/pages')));
        $this->assertRefusal($this->call('GET', '/wikis/new/pages/00000000-0000-4000-8000-000000000000'), 404, 'ACCESS_001');
        self::assertSame([], ApiTestHost::$provisioned);
    }

    public function testArchivedPagesNeedAnEditTokenAndTheArchiveRight(): void
    {
        $old = $this->page($this->eng, 'Old', 'gone');
        $this->pages()->archive($this->wikiEm()->find(WikiPage::class, $old->getId()) ?? $old);
        $this->token('reader', 'u-ana', WikiApiLevel::Read);
        $this->token('bob-edit', 'u-bob', WikiApiLevel::Edit);

        self::assertSame(['Old'], array_column($this->json($this->call('GET', '/wikis/eng/pages?archived=true'))['pages'], 'title'));
        self::assertTrue($this->json($this->call('GET', '/wikis/eng/pages/' . $old->getId()))['archived']);
        $this->assertRefusal($this->call('GET', '/wikis/eng/pages?archived=true', 'reader'), 403, 'TOKEN_001');
        $this->assertRefusal($this->call('GET', '/wikis/eng/pages/' . $old->getId(), 'reader'), 404, 'ACCESS_001');
        self::assertSame([], $this->json($this->call('GET', '/wikis/eng/pages?archived=true', 'bob-edit'))['pages']);
        $this->assertRefusal($this->call('GET', '/wikis/eng/pages/' . $old->getId(), 'bob-edit'), 404, 'ACCESS_001');
    }

    public function testPagesBelongToTheirWiki(): void
    {
        $elsewhere = $this->page($this->ops, 'Elsewhere', 'x');

        $this->assertRefusal($this->call('GET', '/wikis/eng/pages/' . $elsewhere->getId()), 404, 'ACCESS_001');
        $this->assertRefusal($this->call('GET', '/wikis/eng/pages/not-a-uuid'), 404, 'ACCESS_001');
        self::assertSame(200, $this->call('GET', '/wikis/ops/pages/' . $elsewhere->getId())->getStatusCode());
    }

    public function testSourceForAnEditorsEditTokenAndTheReadersViewForEveryoneElse(): void
    {
        $open                     = $this->page($this->eng, 'Open', 'x');
        $hidden                   = $this->page($this->eng, 'Hidden', 'x');
        $ops                      = $this->page($this->ops, 'Ops page', 'x');
        ApiTestHost::$hiddenPages = ['hidden'];
        $source                   = "# Runbook\n\nSee [[wiki:" . $open->getId() . ']], [[wiki:' . $hidden->getId() . ']] and [[wiki:' . $ops->getId() . "]].\n\n[unused]: https://internal.example/admin";
        $page                     = $this->page($this->eng, 'Runbook', $source);

        $this->token('ana-edit', 'u-ana', WikiApiLevel::Edit);
        $edit = $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId(), 'ana-edit'));
        self::assertSame('source', $edit['content_view']);
        self::assertSame($source, $edit['content_markdown']);
        self::assertSame('markdown', $edit['format']);

        // The same editor with a Read token, and a reader: the reader's Markdown.
        $this->token('ana-read', 'u-ana', WikiApiLevel::Read);
        $this->token('bob', 'u-bob', WikiApiLevel::Edit);
        foreach (['ana-read', 'bob'] as $secret) {
            $viewer = $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId(), $secret));
            self::assertSame('viewer', $viewer['content_view'], $secret);
            self::assertStringContainsString('[[wiki:' . $open->getId() . ']]', $viewer['content_markdown']);
            self::assertStringNotContainsString($hidden->getId(), $viewer['content_markdown']);
            self::assertStringNotContainsString('Hidden', $viewer['content_markdown']);
            self::assertStringNotContainsString('internal.example', $viewer['content_markdown']);
        }
        // Bob cannot open the ops wiki: its page reads as not visible.
        self::assertStringNotContainsString($ops->getId(), $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId(), 'bob'))['content_markdown']);
        // Ana can, but not through a token limited to eng (contract F3, owner Q-O3).
        $this->token('ana-eng', 'u-ana', WikiApiLevel::Read, WikiApiWhere::limitedTo(['owner-1'], []));
        $limited = $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId(), 'ana-eng'))['content_markdown'];
        self::assertStringNotContainsString($ops->getId(), $limited);
        self::assertStringNotContainsString('Ops page', $limited);
        self::assertStringContainsString('[[wiki:' . $open->getId() . ']]', $limited);
    }

    /**
     * 1.25 (Beacon, Appendix IF follow-up): a file referenced by plain URL belongs to a wiki; a token whose *where* does not
     * reach that wiki gets the neutral placeholder — no uuid, no alt text, no link text — even when its person could open it.
     */
    public function testAFileOutsideTheWhereIsNeverShownByUrlOrText(): void
    {
        $opsFile             = '22222222-2222-4222-8222-000000000001';
        $engFile             = '22222222-2222-4222-8222-000000000002';
        ApiTestFiles::$wikis = [$opsFile => 'ops', $engFile => 'eng'];
        $source              = "![secret org chart](/files/{$opsFile}/preview)\n\nSee [salaries 2026.pdf](/files/{$opsFile}/original) and ![eng diagram](/files/{$engFile}/preview).\n\n"
            . "![secret thumb](/files/{$opsFile}/thumb) [secret absolute](https://beacon.example/files/{$opsFile}/original?download=1) <https://beacon.example/files/{$opsFile}/s128>\n\n"
            . "Pasted: https://beacon.example/files/{$opsFile}/original";
        $page = $this->page($this->eng, 'Runbook', $source);

        $this->token('ana-read', 'u-ana', WikiApiLevel::Read);
        $everything = $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId(), 'ana-read'))['content_markdown'];
        self::assertStringContainsString('![secret org chart](/files/' . $opsFile . '/preview)', $everything);
        self::assertStringContainsString('[salaries 2026.pdf](/files/' . $opsFile . '/original)', $everything);

        $this->token('ana-eng', 'u-ana', WikiApiLevel::Read, WikiApiWhere::limitedTo(['owner-1'], []));
        $limited = $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId(), 'ana-eng'))['content_markdown'];
        self::assertStringNotContainsString($opsFile, $limited);
        self::assertStringNotContainsString('secret org chart', $limited);
        self::assertStringNotContainsString('salaries', $limited);
        // Image, link, thumb variant, absolute link, angle autolink and GFM bare autolink (audit IJ-L3).
        self::assertSame(6, substr_count($limited, 'Content not visible'));
        self::assertStringContainsString('![eng diagram](/files/' . $engFile . '/preview)', $limited);

        // The source is still the source for an editor's Edit token within the where.
        $this->token('ana-edit', 'u-ana', WikiApiLevel::Edit, WikiApiWhere::limitedTo(['owner-1'], []));
        self::assertSame($source, $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId(), 'ana-edit'))['content_markdown']);
    }

    public function testAPageOverTheLimitsIsNeverShownToAReader(): void
    {
        $hidden                   = $this->page($this->eng, 'Hidden', 'x');
        ApiTestHost::$hiddenPages = ['hidden'];
        $page                     = $this->page($this->eng, 'Huge', '[[wiki:' . $hidden->getId() . ']] ' . str_repeat('*a* ', 20000));
        $this->token('bob', 'u-bob', WikiApiLevel::Read);

        self::assertSame(WikiViewerMarkdownView::TOO_LARGE, $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId(), 'bob'))['content_markdown']);
    }

    public function testAnHtmlRevisionHasNoMarkdown(): void
    {
        $em   = $this->em();
        $page = $this->pages()->create($em->find(WikiSpace::class, $this->eng->getId()) ?? $this->eng, 'Old style', '<p>Plain <strong>old</strong> text</p>', $em->find(TestUserEntity::class, 'u-ana') ?? $this->ana);

        $body = $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId()));
        self::assertSame('html', $body['format']);
        self::assertNull($body['content_markdown']);
        self::assertSame('Plain old text', $body['content_text']);
    }

    public function testAPageWithoutRevisionHasAnEmptyBody(): void
    {
        $em   = $this->em();
        $bare = new WikiPage($em->find(WikiSpace::class, $this->eng->getId()) ?? $this->eng, 'bare', 'Bare');
        $em->persist($bare);
        $em->flush();

        $body = $this->json($this->call('GET', '/wikis/eng/pages/' . $bare->getId()));
        self::assertNull($body['revision']);
        self::assertNull($body['updated_by']);
        self::assertSame('', $body['content_markdown']);
    }

    public function testThePersonsViewRightAndTheLevelTable(): void
    {
        $page                                = $this->page($this->eng, 'Guide', 'x');
        ApiTestHost::$grants['u-ana']['eng'] = [WikiAction::Edit];

        $this->assertRefusal($this->call('GET', '/wikis/eng/pages/' . $page->getId()), 404, 'ACCESS_001');
        $this->assertRefusal($this->call('GET', '/wikis/eng/pages'), 404, 'ACCESS_001');
    }
}
