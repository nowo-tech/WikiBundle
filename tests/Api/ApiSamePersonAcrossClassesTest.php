<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;

/**
 * Audit OX-L1 (JX-W1 on the REST API; 1.24.0 to 1.35.x): a back-office user of another identity domain whose id
 * equals Ana's (`user_class`) is not Ana. With an Edit-level token and the space's Archive right it cannot archive or
 * unarchive Ana's pages ("one's own pages at Edit"), and it never gets Ana's host API id. Ana herself still can.
 */
final class ApiSamePersonAcrossClassesTest extends ApiTestCase
{
    public function testAnotherIdentityDomainWithTheSameIdIsNotThePageOwner(): void
    {
        $page                                 = $this->page($this->eng, 'Guide', 'first');
        ApiTestHost::$tokens['foreign']       = ['user' => 'u-ana', 'level' => WikiApiLevel::Edit, 'where' => WikiApiWhere::everything(), 'foreign' => true];
        ApiTestHost::$grants['foreign-u-ana'] = ['eng' => ApiTestHost::all()];
        $this->token('editor', 'u-ana', WikiApiLevel::Edit);

        $this->assertRefusal($this->call('POST', '/wikis/eng/pages/' . $page->getId() . '/archive', 'foreign'), 403, 'TOKEN_001');
        $head = $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId(), 'foreign'));
        self::assertNull($head['created_by']['id'], 'never Ana\'s host API id');
        self::assertNull($head['updated_by']['id']);
        self::assertNull($this->json($this->call('GET', '/wikis/eng/pages', 'foreign'))['pages'][0]['created_by']['id']);

        // Ana (user_class), with the same level, owns the page.
        self::assertTrue($this->json($this->call('POST', '/wikis/eng/pages/' . $page->getId() . '/archive', 'editor'))['archived']);
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages/' . $page->getId() . '/unarchive', 'foreign'), 403, 'TOKEN_001');
        self::assertSame('api-u-ana', $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId(), 'editor'))['created_by']['id']);
        self::assertContains(WikiAction::Archive, ApiTestHost::$grants['foreign-u-ana']['eng'], 'the checker grants Archive: only the owner rule refuses');
    }
}
