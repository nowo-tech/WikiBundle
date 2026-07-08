<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Service;

use InvalidArgumentException;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Service\WikiSpaceService;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;

final class WikiSpaceServiceTest extends TestCase
{
    public function testCreateSpace(): void
    {
        $repo = $this->createMock(WikiSpaceRepositoryInterface::class);
        $repo->method('findBySlug')->willReturn(null);
        $repo->expects(self::once())->method('save');

        $space = (new WikiSpaceService($repo, new WikiSlugger()))->create('Engineering', WikiSpaceOwnerScope::Team, 'team-1');

        self::assertSame('engineering', $space->getSlug());
    }

    public function testRejectsDuplicateSpaceSlug(): void
    {
        $repo = $this->createMock(WikiSpaceRepositoryInterface::class);
        $repo->method('findBySlug')->willReturn(new WikiSpace('x', 'X', WikiSpaceOwnerScope::Team, 'team-1'));

        $this->expectException(InvalidArgumentException::class);
        (new WikiSpaceService($repo, new WikiSlugger()))->create('X', WikiSpaceOwnerScope::Team, 'team-1');
    }

    public function testRejectsInvalidSlug(): void
    {
        $repo = $this->createMock(WikiSpaceRepositoryInterface::class);

        $this->expectException(InvalidArgumentException::class);
        (new WikiSpaceService($repo, new WikiSlugger()))->create('X', WikiSpaceOwnerScope::Team, 'team-1', '!!!');
    }

    public function testFindBySlugDelegatesToRepository(): void
    {
        $space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 'team-1');
        $repo  = $this->createMock(WikiSpaceRepositoryInterface::class);
        $repo->method('findBySlug')->willReturn($space);

        $found = (new WikiSpaceService($repo, new WikiSlugger()))
            ->findBySlug(WikiSpaceOwnerScope::Team, 'team-1', 'eng');

        self::assertSame($space, $found);
    }

    public function testCreateWithExplicitSlug(): void
    {
        $repo = $this->createMock(WikiSpaceRepositoryInterface::class);
        $repo->method('findBySlug')->willReturn(null);
        $repo->expects(self::once())->method('save');

        $space = (new WikiSpaceService($repo, new WikiSlugger()))
            ->create('Docs', WikiSpaceOwnerScope::Team, 'team-1', 'docs');

        self::assertSame('docs', $space->getSlug());
    }
}
