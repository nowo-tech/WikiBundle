<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\SearchKitBundle\Model\SearchQuery;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Search\SearchKit\WikiPageSearchKitProvider;
use Nowo\WikiBundle\Tests\Api\App\ApiTeamMembership;
use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

require_once __DIR__ . '/../Stub/SearchKit/SearchKit.php';

/**
 * 1.44 (audit TI-M1): a search made for another identifier inside someone's session (SearchKit's `searchFor()`) passes
 * the wiki's `list` role gate on the viewer's own roles, not on the session's. The wiki lists only for ROLE_WIKI_LIST
 * here; the test people (u-ana, u-bob) hold ROLE_USER only, the session user `u-carol` holds ROLE_WIKI_LIST.
 */
final class SearchKitProviderSessionTest extends ApiTestCase
{
    /** @var array<string, mixed> */
    protected static array $wikiConfig = [
        'search_kit_provider'      => true,
        'team_membership_resolver' => 'app.teams',
        'security'                 => ['access_roles' => ['ROLE_USER', 'ROLE_WIKI_LIST'], 'roles' => ['list' => ['ROLE_WIKI_LIST']]],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        ApiTeamMembership::$teams              = ['u-ana' => ['owner-1'], 'u-bob' => ['owner-1'], 'u-carol' => ['owner-1']];
        ApiTestHost::$grants['u-carol']['eng'] = [WikiAction::View];
    }

    public function testTheRoleGateIsDecidedForTheViewerNotForTheSession(): void
    {
        $this->page($this->eng, 'Pulsar', 'pulsar');
        $provider = self::getContainer()->get('test.wiki_search_kit_provider');
        self::assertInstanceOf(WikiPageSearchKitProvider::class, $provider);
        $tokens = self::getContainer()->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $tokens);
        $query = new SearchQuery('pulsar', null, 10, userIdentifier: 'u-bob');

        // Carol's session holds the list role; Bob does not: a search for Bob finds nothing (before 1.44 it found Pulsar).
        $carol = new InMemoryUser('u-carol', null, ['ROLE_WIKI_LIST']);
        $tokens->setToken(new UsernamePasswordToken($carol, 'main', $carol->getRoles()));
        self::assertNull($provider->search($query));

        // Bob in his own session: the token decides (it lacks the role too).
        $bob = new InMemoryUser('u-bob', null, ['ROLE_USER']);
        $tokens->setToken(new UsernamePasswordToken($bob, 'main', $bob->getRoles()));
        self::assertNull($provider->search($query));
    }
}
