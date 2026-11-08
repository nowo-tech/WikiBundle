<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

/**
 * 1.24 (owner Q-O6): at most `api.search_candidates` rows read per request; `truncated` says so.
 */
final class ApiSearchCapTest extends ApiTestCase
{
    protected static array $apiConfig = ['search_candidates' => 100];

    public function testTheCandidateCap(): void
    {
        for ($i = 0; $i < 105; ++$i) {
            $this->page($this->eng, 'Note ' . $i, 'common');
        }

        $first = $this->json($this->call('GET', '/search?q=common'));
        self::assertSame(100, $first['total']);
        self::assertTrue($first['truncated']);
        self::assertCount(100, $first['hits']);
        self::assertTrue($first['last_page']);
        self::assertSame([], $this->json($this->call('GET', '/search?q=common&page=1'))['hits']);
    }
}
