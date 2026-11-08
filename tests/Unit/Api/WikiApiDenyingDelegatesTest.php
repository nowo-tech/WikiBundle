<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Api;

use Nowo\WikiBundle\Api\Hook\DenyingWikiApiFiles;
use Nowo\WikiBundle\Api\Hook\DenyingWikiApiWikiManagement;
use Nowo\WikiBundle\Api\Value\WikiApiNewWiki;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\Value\WikiApiWikiPatch;
use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * 1.24 (spec 218): without the host's hooks, no wiki is managed and no file moves through the API.
 */
final class WikiApiDenyingDelegatesTest extends TestCase
{
    public function testEveryDefaultDenies(): void
    {
        $principal  = new WikiApiPrincipal(new TestUser(), WikiApiLevel::Full, WikiApiWhere::everything(), 't', 'n');
        $wiki       = new WikiApiWiki('eng', null, 'Eng');
        $management = new DenyingWikiApiWikiManagement();
        foreach ([
            static fn () => $management->create($principal, new WikiApiNewWiki('project', 'p', 'X')),
            static fn () => $management->update($principal, $wiki, new WikiApiWikiPatch('Y')),
            static fn () => $management->archive($principal, $wiki),
            static fn () => $management->unarchive($principal, $wiki),
        ] as $call) {
            try {
                $call();
                self::fail('Expected WIKI_001');
            } catch (WikiApiRefusal $refusal) {
                self::assertSame([403, 'WIKI_001'], [$refusal->status, $refusal->ecode]);
            }
        }

        $files = new DenyingWikiApiFiles();
        self::assertSame([], $files->list($principal, $wiki, new WikiPage(new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't'), 'p', 'P')));
        self::assertNull($files->download($principal, $wiki, 'x'));
        $this->expectExceptionMessage('Files are not available through this API');
        $files->upload($principal, $wiki, new UploadedFile(__FILE__, 'a.php', null, null, true));
    }
}
