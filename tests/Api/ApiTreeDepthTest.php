<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\WikiBundle\Entity\WikiPage;

/**
 * 1.24 (spec 218 FR-027): a move deeper than `tree.max_depth` is 422 `WIKI_MOVE_002`.
 */
final class ApiTreeDepthTest extends ApiTestCase
{
    protected static array $wikiConfig = ['tree' => ['enabled' => true, 'max_depth' => 2]];

    public function testTooDeep(): void
    {
        $a = $this->page($this->eng, 'A', 'x');
        $b = $this->page($this->eng, 'B', 'x', $a);
        $c = $this->page($this->eng, 'C', 'x');

        $this->assertRefusal($this->call('POST', '/wikis/eng/pages/' . $c->getId() . '/move', self::ANA, ['parent' => $b->getId()]), 422, 'WIKI_MOVE_002');
    }

    public function testACreateWithAPositionTooDeepWritesNothing(): void
    {
        $a = $this->page($this->eng, 'A', 'x');
        $b = $this->page($this->eng, 'B', 'x', $a);

        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'D', 'parent' => $b->getId(), 'position' => 1]), 422, 'WIKI_MOVE_002');
        self::assertNull($this->em()->getRepository(WikiPage::class)->findOneBy(['slug' => 'd']));
    }
}
