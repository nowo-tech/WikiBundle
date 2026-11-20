<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\Space\WikiSpaceAccessDeniedException;
use Nowo\WikiBundle\Space\WikiSpaceLifecycle;
use Nowo\WikiBundle\Space\WikiSpaceLifecycleInterface;
use Nowo\WikiBundle\Space\WikiSpaceNotFoundException;

/**
 * 1.50: the space lifecycle in a host kernel — the public service, the archived space's rule around the host's own
 * space checker (readers meet it as a missing wiki, writes answer `WIKI_002`, the wiki reads `archived`), and a purge
 * after which the wiki's pages are gone.
 */
final class ApiSpaceLifecycleTest extends ApiTestCase
{
    private const BOB = 'secret-bob';

    public function testArchiveUnarchiveRenameAndPurgeThroughTheService(): void
    {
        $lifecycle = self::getContainer()->get(WikiSpaceLifecycleInterface::class);
        self::assertInstanceOf(WikiSpaceLifecycle::class, $lifecycle);
        $page = $this->page($this->eng, 'Plan', 'The plan');
        $this->token(self::BOB, 'u-bob', WikiApiLevel::Read);
        $path = '/wikis/eng/pages/' . $page->getId();
        self::assertSame(200, $this->call('GET', $path, self::BOB)->getStatusCode());

        try {
            $lifecycle->archiveSpace($this->eng->getId(), WikiUserRefs::fromUser($this->bob));
            self::fail('A reader may not archive.');
        } catch (WikiSpaceAccessDeniedException) {
        }
        try {
            $lifecycle->archiveSpace($this->ops->getId(), WikiUserRefs::fromUser($this->bob));
            self::fail('Another owner\'s wiki reads as unknown.');
        } catch (WikiSpaceNotFoundException) {
        }

        $archived = $lifecycle->archiveSpace($this->eng->getId(), WikiUserRefs::fromUser($this->ana), 1);

        self::assertTrue($archived->isArchived());
        $this->assertRefusal($this->call('GET', $path, self::BOB), 404, 'ACCESS_001');
        self::assertSame(200, $this->call('GET', $path)->getStatusCode(), 'The manager still reads it.');
        self::assertTrue($this->json($this->call('GET', '/wikis/eng'))['archived']);
        $this->assertRefusal($this->call('PUT', $path, self::ANA, ['content_markdown' => 'edit', 'base_revision' => 1]), 403, 'WIKI_002');

        $back = $lifecycle->unarchiveSpace($this->eng->getId(), WikiUserRefs::fromUser($this->ana), $archived->version);
        self::assertFalse($back->isArchived());
        self::assertSame(200, $this->call('GET', $path, self::BOB)->getStatusCode());
        self::assertSame('Engineering 2', $lifecycle->renameSpace($this->eng->getId(), 'Engineering 2', null, WikiUserRefs::fromUser($this->ana))->name);

        $result = $lifecycle->purgeSpace($this->eng->getId(), WikiUserRefs::fromUser($this->ana), true);

        self::assertTrue($result->finished);
        self::assertSame(1, $result->pages);
        self::assertNull($this->wikiEm()->find(WikiPage::class, $page->getId()));
        self::assertNull($this->wikiEm()->find(WikiSpace::class, $this->eng->getId()));
        $this->assertRefusal($this->call('GET', $path), 404, 'ACCESS_001');
    }
}
