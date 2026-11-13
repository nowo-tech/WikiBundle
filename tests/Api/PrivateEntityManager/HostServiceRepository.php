<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\PrivateEntityManager;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;

/**
 * A host ServiceEntityRepository that is not registered as a service: DoctrineBundle's container factory refuses it,
 * which proves the private manager asked that factory (audit NZ).
 *
 * @extends ServiceEntityRepository<TestUserEntity>
 */
final class HostServiceRepository extends ServiceEntityRepository
{
}
