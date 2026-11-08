<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;

use function array_column;

/**
 * 1.24 (spec 218 FR-009, FR-011, FR-018): `GET /wikis` and `GET /wikis/{wiki}`.
 */
final class ApiWikiReadTest extends ApiTestCase
{
    public function testListsWhatTheDirectoryAnswersWithinWhere(): void
    {
        $body = $this->json($this->call('GET', '/wikis'));

        self::assertTrue($body['last_page']);
        self::assertSame(['eng', 'ops'], array_column($body['wikis'], 'slug'));
        self::assertSame([
            'id'          => $this->eng->getId(),
            'slug'        => 'eng',
            'name'        => 'Engineering',
            'description' => 'About eng',
            'owner_type'  => 'project',
            'owner_id'    => 'owner-1',
            'archived'    => false,
            'restricted'  => false,
            'can_open'    => true,
            'created_at'  => $body['wikis'][0]['created_at'],
            'updated_at'  => $body['wikis'][0]['updated_at'],
            'page_count'  => 3,
        ], $body['wikis'][0]);
        self::assertIsInt($body['wikis'][0]['created_at']);

        $this->token('eng-only', 'u-ana', WikiApiLevel::Read, WikiApiWhere::limitedTo(['owner-1'], []));
        self::assertSame(['eng'], array_column($this->json($this->call('GET', '/wikis', 'eng-only'))['wikis'], 'slug'));
        $this->assertRefusal($this->call('GET', '/wikis/ops', 'eng-only'), 404, 'ACCESS_001');
    }

    public function testFiltersAreValidatedAndPassedOn(): void
    {
        ApiTestHost::$wikis['old'] = ['archived' => true];
        self::assertSame(['old'], array_column($this->json($this->call('GET', '/wikis?archived=true'))['wikis'], 'slug'));
        self::assertSame(['ops'], array_column($this->json($this->call('GET', '/wikis?owner_id=owner-2'))['wikis'], 'slug'));
        $this->assertRefusal($this->call('GET', '/wikis?archived=maybe'), 400, 'INPUT_002');
        $this->assertRefusal($this->call('GET', '/wikis?page=-1'), 400, 'INPUT_002');
        $this->assertRefusal($this->call('GET', '/wikis?page[]=1'), 400, 'INPUT_002');
        $this->assertRefusal($this->call('GET', '/wikis?owner_id='), 400, 'INPUT_002');
    }

    public function testRestrictedWikisOnlyWhenNamedAndNameOnlyWithoutAGrant(): void
    {
        ApiTestHost::$wikis['hr'] = ['restricted' => true, 'can_open' => false];
        self::assertSame(['eng', 'ops'], array_column($this->json($this->call('GET', '/wikis'))['wikis'], 'slug'));
        $this->assertRefusal($this->call('GET', '/wikis/hr'), 404, 'ACCESS_001');

        $this->token('named', 'u-ana', WikiApiLevel::Full, WikiApiWhere::limitedTo([], ['hr']));
        $listed = $this->json($this->call('GET', '/wikis', 'named'))['wikis'];
        self::assertSame(['hr'], array_column($listed, 'slug'));
        self::assertFalse($listed[0]['can_open']);
        self::assertArrayNotHasKey('page_count', $listed[0]);
        self::assertNull($listed[0]['id']);
    }

    public function testAWikiNobodyOpenedIsListedWithoutAnIdAndNothingIsCreated(): void
    {
        ApiTestHost::$wikis['new-project'] = ['unopened' => true];

        $wiki = $this->json($this->call('GET', '/wikis/new-project'));

        self::assertNull($wiki['id']);
        self::assertNull($wiki['created_at']);
        self::assertSame([], ApiTestHost::$provisioned);
    }

    public function testThePlanIsAskedAfterReachAndListsLeaveItsRefusalsOut(): void
    {
        ApiTestHost::$noPlan = ['ops'];

        self::assertSame(['eng'], array_column($this->json($this->call('GET', '/wikis'))['wikis'], 'slug'));
        $this->assertRefusal($this->call('GET', '/wikis/ops'), 403, 'PLAN_001');
        // A wiki out of reach is 404 whatever the plan says.
        $this->assertRefusal($this->call('GET', '/wikis/missing'), 404, 'ACCESS_001');
    }

    public function testAWikiThePersonMayNotViewIsAbsent(): void
    {
        $this->token('bob', 'u-bob', WikiApiLevel::Full);

        self::assertSame(['eng'], array_column($this->json($this->call('GET', '/wikis', 'bob'))['wikis'], 'slug'));
        $this->assertRefusal($this->call('GET', '/wikis/ops', 'bob'), 404, 'ACCESS_001');
    }

    public function testListsArePagedByAHundred(): void
    {
        for ($i = 0; $i < 120; ++$i) {
            ApiTestHost::$wikis['w' . $i] = ['unopened' => true];
        }

        $first  = $this->json($this->call('GET', '/wikis'));
        $second = $this->json($this->call('GET', '/wikis?page=1'));

        self::assertCount(100, $first['wikis']);
        self::assertFalse($first['last_page']);
        self::assertCount(22, $second['wikis']);
        self::assertTrue($second['last_page']);
    }
}
