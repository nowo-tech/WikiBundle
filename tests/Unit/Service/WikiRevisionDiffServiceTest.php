<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Service;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Service\WikiRevisionDiffService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

final class WikiRevisionDiffServiceTest extends TestCase
{
    public function testDiffDetectsAddedLine(): void
    {
        $space  = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $page   = new WikiPage($space, 'page', 'Page');
        $author = new stdClass();
        $from   = new WikiPageRevision($page, 1, '<p>Hello</p>', $author);
        $to     = new WikiPageRevision($page, 2, '<p>Hello</p><p>World</p>', $author);

        $repo = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $diff = (new WikiRevisionDiffService($repo))->diff($from, $to);

        self::assertNotEmpty($diff);
        self::assertContains('add', array_column($diff, 'kind'));
    }

    public function testDiffDetectsRemovedLine(): void
    {
        $space  = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $page   = new WikiPage($space, 'page', 'Page');
        $author = new stdClass();
        $from   = new WikiPageRevision($page, 1, '<p>Hello</p><p>World</p>', $author);
        $to     = new WikiPageRevision($page, 2, '<p>Hello</p>', $author);

        $repo = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $diff = (new WikiRevisionDiffService($repo))->diff($from, $to);

        self::assertContains('remove', array_column($diff, 'kind'));
    }

    public function testFindRevisionOrFailThrowsWhenMissing(): void
    {
        $repo = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $this->expectException(RuntimeException::class);
        (new WikiRevisionDiffService($repo))->findRevisionOrFail('missing');
    }

    public function testFindRevisionOrFailReturnsRevision(): void
    {
        $space    = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $page     = new WikiPage($space, 'page', 'Page');
        $revision = new WikiPageRevision($page, 1, '<p>x</p>', new stdClass());

        $repo = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $repo->method('findById')->willReturn($revision);

        $found = (new WikiRevisionDiffService($repo))->findRevisionOrFail($revision->getId());

        self::assertSame($revision, $found);
    }
}
