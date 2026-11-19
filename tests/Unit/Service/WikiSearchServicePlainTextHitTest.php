<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Service\WikiSearchService;
use PHPUnit\Framework\TestCase;

/**
 * 1.46: {@see WikiSearchService::plainTextHit()}, the per-page rule shared by the plain-text search and the SearchKit group.
 */
final class WikiSearchServicePlainTextHitTest extends TestCase
{
    public function testOneRuleForTitleTextAndMarkup(): void
    {
        $search = new WikiSearchService($this->createStub(EntityManagerInterface::class));
        $page   = new WikiPage(new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'owner-1'), 'release-notes', 'Release notes');

        self::assertNull($search->plainTextHit($page, null, 'anything', null, '   '));
        self::assertSame('Release notes', $search->plainTextHit($page, null, '', null, 'release')['excerpt'] ?? null);
        self::assertSame('Ship on Friday.', $search->plainTextHit($page, null, 'Ship on Friday.', null, 'friday')['excerpt'] ?? null);
        // A revision without plain text: the text of its HTML, never its markup.
        self::assertNull($search->plainTextHit($page, '<p class="zinc">Words</p>', null, null, 'zinc'));
        self::assertSame('Words', $search->plainTextHit($page, '<p class="zinc">Words</p>', null, null, 'words')['excerpt'] ?? null);
    }
}
