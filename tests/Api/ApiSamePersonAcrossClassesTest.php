<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;
use Nowo\WikiBundle\Tests\Stub\WikiForeignAdminUser;
use Nowo\WikiBundle\Tests\Stub\WikiSecurityUserDto;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;

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

    /**
     * Audit OX-D1-L1, 2.0: the firewall loads a security adapter class that is not in `accepted_user_classes`, the API
     * authenticator returns a user of another class with the same id. 1.x let the request through with a notice ("this
     * will be refused in 2.0"); 2.0 refuses it as a configuration error (500): a look-alike id in another user class is
     * another person. Nothing is written.
     */
    public function testASecurityAdapterSharingOnlyTheIdIsRefused(): void
    {
        $page                                 = $this->page($this->eng, 'Guide', 'first');
        ApiTestHost::$tokens['adapted']       = ['user' => 'u-ana', 'level' => WikiApiLevel::Edit, 'where' => WikiApiWhere::everything(), 'adapter' => true];
        ApiTestHost::$tokens['admin']         = ['user' => 'u-ana', 'level' => WikiApiLevel::Edit, 'where' => WikiApiWhere::everything(), 'adapter' => true, 'foreign' => true];
        ApiTestHost::$grants['foreign-u-ana'] = ['eng' => ApiTestHost::all()];
        $logger                               = self::getContainer()->get('logger');
        self::assertInstanceOf(RecordingLogger::class, $logger);
        $logger->records = [];

        self::assertSame(500, $this->call('POST', '/wikis/eng/pages/' . $page->getId() . '/archive', 'admin')->getStatusCode());
        self::assertContains('error: Wiki API: configuration error: the firewall user (' . WikiSecurityUserDto::class . ') and the token\'s user (' . WikiForeignAdminUser::class . ') share only their id; 2.0 refuses this: add ' . WikiSecurityUserDto::class . ', ' . WikiForeignAdminUser::class . ' to nowo_wiki.accepted_user_classes only if it carries user_class ids (an adapter or wrapper); otherwise the firewall and the authenticator must return the same user.', $logger->lines());
        self::assertSame(500, $this->call('POST', '/wikis/eng/pages/' . $page->getId() . '/archive', 'adapted')->getStatusCode());
        self::assertSame(500, $this->call('GET', '/wikis/eng/pages/' . $page->getId(), 'adapted')->getStatusCode());
        self::assertNull($this->em()->getConnection()->fetchOne('SELECT archived_at FROM wiki_pages WHERE id = ?', [$page->getId()]), 'nothing was written');
    }
}
