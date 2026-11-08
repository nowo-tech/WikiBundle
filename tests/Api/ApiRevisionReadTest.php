<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiRevisionLabel;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;

use function array_column;

/**
 * 1.24 (spec 218 FR-015, FR-016): history, one revision in the page's view, and the diff for whoever gets the source.
 */
final class ApiRevisionReadTest extends ApiTestCase
{
    public function testHistoryNewestFirstWithLabelsAndAuthors(): void
    {
        $page = $this->twoRevisions();
        $em   = $this->em();
        $em->persist(new WikiRevisionLabel($em->getRepository(WikiPageRevision::class)->findOneBy(['page' => $page->getId(), 'revisionNumber' => 1]) ?? self::fail(), 'First'));
        $em->flush();

        $body = $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId() . '/revisions'));
        self::assertSame([2, 1], array_column($body['revisions'], 'number'));
        self::assertSame([null, 'First'], array_column($body['revisions'], 'label'));
        self::assertTrue($body['last_page']);
        self::assertSame('api-u-ana', $body['revisions'][1]['author']['id']);
        self::assertNull($body['revisions'][0]['author']['id']);
    }

    public function testOneRevisionFollowsThePagesView(): void
    {
        $page = $this->twoRevisions();
        $this->token('bob', 'u-bob', WikiApiLevel::Read);

        $source = $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId() . '/revisions/1'));
        self::assertSame(['number' => 1, 'content_view' => 'source', 'content_markdown' => 'first'], ['number' => $source['number'], 'content_view' => $source['content_view'], 'content_markdown' => $source['content_markdown']]);
        self::assertSame('viewer', $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId() . '/revisions/2', 'bob'))['content_view']);
        $this->assertRefusal($this->call('GET', '/wikis/eng/pages/' . $page->getId() . '/revisions/3'), 404, 'ACCESS_001');
    }

    public function testTheDiffNeedsTheSource(): void
    {
        $page = $this->twoRevisions();
        $path = '/wikis/eng/pages/' . $page->getId() . '/revisions/2/diff';

        $diff = $this->json($this->call('GET', $path . '?against=1'));
        self::assertSame(1, $diff['from']);
        self::assertSame(2, $diff['to']);
        self::assertContains(['kind' => 'add', 'line' => 'second'], $diff['lines']);

        $this->assertRefusal($this->call('GET', $path), 400, 'INPUT_002');
        $this->token('reader', 'u-ana', WikiApiLevel::Read);
        $this->assertRefusal($this->call('GET', $path . '?against=1', 'reader'), 403, 'TOKEN_001');
        $this->token('bob', 'u-bob', WikiApiLevel::Edit);
        $this->assertRefusal($this->call('GET', $path . '?against=1', 'bob'), 403, 'ACCESS_002');
        $this->assertRefusal($this->call('GET', $path . '?against=9'), 404, 'ACCESS_001');
    }

    public function testHistoryNeedsTheHistoryRight(): void
    {
        $page                                = $this->twoRevisions();
        ApiTestHost::$grants['u-bob']['eng'] = [WikiAction::View];
        $this->token('bob', 'u-bob', WikiApiLevel::Full);

        $this->assertRefusal($this->call('GET', '/wikis/eng/pages/' . $page->getId() . '/revisions', 'bob'), 403, 'ACCESS_002');
    }

    private function twoRevisions(): WikiPage
    {
        $page = $this->page($this->eng, 'Guide', 'first');
        $em   = $this->em();
        $this->pages()->saveRevision($em->find(WikiPage::class, $page->getId()) ?? $page, 'Guide', "first\nsecond", $em->find(TestUserEntity::class, 'u-bob') ?? $this->bob, WikiPageRevision::FORMAT_MARKDOWN);

        return $page;
    }
}
