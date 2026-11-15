<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;

use function array_column;

/**
 * 1.24 (spec 218 FR-034, audit IF-I5): threads read through the comment service's rights, bodies in the page's view,
 * people per the history policy.
 */
final class ApiThreadReadTest extends ApiTestCase
{
    public function testThreadsAndTheirComments(): void
    {
        $hidden                   = $this->page($this->eng, 'Hidden', 'x');
        ApiTestHost::$hiddenPages = ['hidden'];
        $page                     = $this->page($this->eng, 'Guide', 'x');
        $thread                   = $this->comment($page, 'See [[wiki:' . $hidden->getId() . ']]');

        $this->token('bob', 'u-bob', WikiApiLevel::Read);
        $read = $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId() . '/threads', 'bob'));
        self::assertFalse($read['truncated']);
        self::assertSame([$thread], array_column($read['threads'], 'id'));
        $one = $read['threads'][0];
        self::assertNull($one['anchor']);
        self::assertNull($one['quote']);
        self::assertFalse($one['resolved']);
        self::assertSame('viewer', $one['comments'][0]['content_view']);
        self::assertStringNotContainsString($hidden->getId(), $one['comments'][0]['body_markdown']);
        self::assertNull($one['comments'][0]['author']['id']);

        $source = $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId() . '/threads/' . $thread));
        self::assertSame('source', $source['comments'][0]['content_view']);
        self::assertSame('See [[wiki:' . $hidden->getId() . ']]', $source['comments'][0]['body_markdown']);
        self::assertSame('api-u-ana', $source['comments'][0]['author']['id']);
    }

    public function testRefusals(): void
    {
        $page   = $this->page($this->eng, 'Guide', 'x');
        $other  = $this->page($this->eng, 'Other', 'x');
        $thread = $this->comment($page, 'Hello');

        $this->assertRefusal($this->call('GET', '/wikis/eng/pages/' . $other->getId() . '/threads/' . $thread), 404, 'ACCESS_001');
        $this->assertRefusal($this->call('GET', '/wikis/eng/pages/' . $page->getId() . '/threads/missing'), 404, 'ACCESS_001');
        ApiTestHost::$grants['u-bob']['eng'] = [WikiAction::View];
        $this->token('bob', 'u-bob', WikiApiLevel::Read);
        $this->assertRefusal($this->call('GET', '/wikis/eng/pages/' . $page->getId() . '/threads', 'bob'), 403, 'ACCESS_002');
    }

    private function comment(WikiPage $page, string $body): string
    {
        $em      = $this->em();
        $service = static::getContainer()->get(WikiCommentService::class);
        self::assertInstanceOf(WikiCommentService::class, $service);
        $stored = $this->wikiEm()->find(WikiPage::class, $page->getId()) ?? $page;
        $thread = $service->create($em->find(TestUserEntity::class, 'u-ana') ?? $this->ana, $em->find(WikiSpace::class, $stored->getSpace()->getId()) ?? $stored->getSpace(), $stored, null, $body);

        return (string) $thread['id'];
    }
}
