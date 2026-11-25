<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use LogicException;
use Nowo\WikiBundle\Doctrine\WikiEntityManager;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use PHPUnit\Framework\TestCase;
use stdClass;

final class WikiEntityManagerProviderTest extends TestCase
{
    public function testReturnsOpenManagerOnEveryCall(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('isOpen')->willReturn(true);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::exactly(2))->method('getManager')->with('wiki')->willReturn($em);
        $registry->expects(self::never())->method('resetManager');

        $provider = new WikiEntityManagerProvider($registry, 'wiki');

        self::assertSame($em, $provider->get());
        self::assertSame($em, $provider->get());
    }

    /** 1.37 (audit QD-M1): a closed manager is returned as it is; the runner replaces it at the next boundary. */
    public function testNeverResetsAClosedManagerInTheMiddleOfARequest(): void
    {
        $closed = $this->createMock(EntityManagerInterface::class);
        $closed->method('isOpen')->willReturn(false);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->with('default')->willReturn($closed);
        $registry->expects(self::never())->method('resetManager');

        self::assertSame($closed, (new WikiEntityManagerProvider($registry))->get());
    }

    public function testRejectsNonOrmManager(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($this->createMock(ObjectManager::class));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Doctrine manager "odm" is not an ORM entity manager.');

        (new WikiEntityManagerProvider($registry, 'odm'))->get();
    }

    public function testRevisionRepositoryResolvesManagerPerCall(): void
    {
        $revision = $this->createMock(WikiPageRevision::class);
        $em       = $this->createMock(EntityManagerInterface::class);
        $em->method('isOpen')->willReturn(true);
        $em->expects(self::once())->method('find')->with(WikiPageRevision::class, 'r1')->willReturn($revision);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::once())->method('getManager')->willReturn($em);

        $repository = new DoctrineOrmWikiPageRevisionRepository(new WikiEntityManagerProvider($registry));

        self::assertSame($revision, $repository->findById('r1'));
    }

    /** 2.0: the bundle's provider always answers its private manager; the 1.49 probe argument is ignored. */
    public function testThePrivateManagerWinsAndTheProbeIsGone(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManager');
        $private  = new WikiEntityManager($registry);
        $provider = new WikiEntityManagerProvider($registry, 'default', $private, new stdClass());

        self::assertSame($private, $provider->get());
        self::assertNull($provider->hostFlushProbe());
    }
}
