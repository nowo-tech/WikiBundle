<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\WikiBundle\Entity\WikiPage;

/**
 * 1.24 (audit IF-L4): reading archived pages asks the role gate's `canArchive()`, as the UI does, even when the host's
 * space checker grants `Archive` without it.
 */
final class ApiArchiveRoleTest extends ApiTestCase
{
    protected static array $wikiConfig = ['security' => ['access_roles' => ['ROLE_USER'], 'roles' => ['archive' => ['ROLE_ARCHIVER']]]];

    public function testArchivedPagesNeedTheArchiveRole(): void
    {
        $old = $this->page($this->eng, 'Old', 'gone');
        $this->pages()->archive($this->wikiEm()->find(WikiPage::class, $old->getId()) ?? $old);

        $this->assertRefusal($this->call('GET', '/wikis/eng/pages/' . $old->getId()), 404, 'ACCESS_001');
        self::assertSame([], $this->json($this->call('GET', '/wikis/eng/pages?archived=true'))['pages']);
    }
}
