<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;

use function array_column;
use function str_repeat;

/**
 * 1.24 (spec 218 FR-017, owner Q-O6, audit IF-L1 / IF-I3): search within reach, each hit readable, never the source.
 */
final class ApiSearchTest extends ApiTestCase
{
    public function testHitsWithinReachAndReadable(): void
    {
        $this->page($this->eng, 'Backup policy', 'Weekly backups.');
        $this->page($this->eng, 'Secret backup', 'x');
        $this->page($this->ops, 'Ops backup', 'x');
        ApiTestHost::$hiddenPages = ['secret-backup'];

        $all = $this->json($this->call('GET', '/search?q=backup'));
        self::assertEqualsCanonicalizing(['Backup policy', 'Ops backup'], array_column(array_column($all['hits'], 'page'), 'title'));
        self::assertSame(2, $all['total']);
        self::assertFalse($all['truncated']);
        self::assertTrue($all['last_page']);

        self::assertSame(['Backup policy'], array_column(array_column($this->json($this->call('GET', '/search?q=backup&wiki=eng'))['hits'], 'page'), 'title'));
        $this->token('eng-only', 'u-ana', WikiApiLevel::Read, WikiApiWhere::limitedTo(['owner-1'], []));
        self::assertSame(['Backup policy'], array_column(array_column($this->json($this->call('GET', '/search?q=backup', 'eng-only'))['hits'], 'page'), 'title'));
        $this->assertRefusal($this->call('GET', '/search?q=backup&wiki=ops', 'eng-only'), 404, 'ACCESS_001');
    }

    public function testTheSourceIsNeverSearchedNorAnOversizedPagesText(): void
    {
        $hidden                   = $this->page($this->eng, 'Hidden', 'x');
        ApiTestHost::$hiddenPages = ['hidden'];
        $this->page($this->eng, 'Links', 'See [[wiki:' . $hidden->getId() . ']].');
        $this->page($this->eng, 'Huge', '[[wiki:' . $hidden->getId() . ']] <!-- note --> ' . str_repeat('*a* ', 20000));

        self::assertSame(0, $this->json($this->call('GET', '/search?q=' . $hidden->getId()))['total']);
        self::assertSame(0, $this->json($this->call('GET', '/search?q=note'))['total']);
        $huge = $this->json($this->call('GET', '/search?q=huge'));
        self::assertSame(['Huge'], array_column(array_column($huge['hits'], 'page'), 'title'));
        self::assertSame('Huge', $huge['hits'][0]['excerpt']);
    }

    public function testQueryValidation(): void
    {
        $this->assertRefusal($this->call('GET', '/search'), 400, 'INPUT_002');
        $this->assertRefusal($this->call('GET', '/search?q=' . str_repeat('x', 201)), 400, 'INPUT_002');
        self::assertSame(0, $this->json($this->call('GET', '/search?q=nothing&wiki=eng&wiki=ops'))['total']);
    }

    public function testNothingOpenNothingSearched(): void
    {
        ApiTestHost::$wikis = ['new' => ['unopened' => true]];

        self::assertSame(['hits' => [], 'total' => 0, 'truncated' => false, 'last_page' => true], $this->json($this->call('GET', '/search?q=x')));
        self::assertSame(0, $this->json($this->call('GET', '/search?q=x&wiki=new'))['total']);
    }
}
