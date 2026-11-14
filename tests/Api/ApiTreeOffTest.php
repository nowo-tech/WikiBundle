<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

/**
 * 1.24 (spec 218): without the tree (`tree.enabled: false`), there is no move: 404 like an unknown path.
 */
final class ApiTreeOffTest extends ApiTestCase
{
    protected static array $wikiConfig = ['tree' => ['enabled' => false]];

    public function testMoveIsNotFound(): void
    {
        $page = $this->page($this->eng, 'A', 'x');

        $this->assertRefusal($this->call('POST', '/wikis/eng/pages/' . $page->getId() . '/move', self::ANA, ['parent' => null]), 404, 'ACCESS_001');
    }

    public function testACreateWithAPositionNeedsTheTree(): void
    {
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'B', 'position' => 1]), 400, 'INPUT_002');
        // Without position: as before.
        self::assertSame(201, $this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'B'])->getStatusCode());
    }
}
