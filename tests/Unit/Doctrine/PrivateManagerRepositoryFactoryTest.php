<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Repository\RepositoryFactory;
use Nowo\WikiBundle\Doctrine\PrivateManagerRepositoryFactory;
use Nowo\WikiBundle\Tests\Api\PrivateEntityManager\HostServiceRepository;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use PHPUnit\Framework\TestCase;

/**
 * 1.33.0 (audit NZ pattern): a service repository is the source factory's answer, unchanged.
 */
final class PrivateManagerRepositoryFactoryTest extends TestCase
{
    public function testAServiceRepositoryComesFromTheSourceFactory(): void
    {
        $metadata                            = new ClassMetadata(TestUserEntity::class);
        $metadata->customRepositoryClassName = HostServiceRepository::class;
        $manager                             = $this->createStub(EntityManagerInterface::class);
        $manager->method('getClassMetadata')->willReturn($metadata);
        $repository = $this->createStub(EntityRepository::class);
        $source     = $this->createMock(RepositoryFactory::class);
        $source->expects(self::once())->method('getRepository')->with($manager, TestUserEntity::class)->willReturn($repository);

        self::assertSame($repository, (new PrivateManagerRepositoryFactory($source))->getRepository($manager, TestUserEntity::class));
    }
}
