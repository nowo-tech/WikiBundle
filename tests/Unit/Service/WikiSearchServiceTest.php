<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Service\WikiSearchService;
use PHPUnit\Framework\TestCase;

final class WikiSearchServiceTest extends TestCase
{
    public function testEmptyQueryReturnsNoResults(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('createQueryBuilder');

        $space   = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 't1');
        $service = new WikiSearchService($em);

        self::assertSame([], $service->search($space, '   '));
        self::assertSame([], $service->searchAcrossSpaces([$space], ''));
    }

    public function testEmptySpaceListReturnsNoResults(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('createQueryBuilder');

        $service = new WikiSearchService($em);

        self::assertSame([], $service->searchAcrossSpaces([], 'deploy'));
    }
}
