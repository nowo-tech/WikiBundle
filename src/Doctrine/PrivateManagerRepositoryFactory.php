<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Repository\RepositoryFactory;

use function is_a;

/**
 * Since 1.33.0: the repository factory of ONE private manager ({@see WikiEntityManager}; audit NZ's corrected
 * pattern, as Yopass 1.20.2).
 *
 * DoctrineBundle's shared ContainerRepositoryFactory (and ORM's DefaultRepositoryFactory) keep one repository per manager
 * instance for the life of the process, so every replaced private manager would stay in memory in a kept-alive worker.
 * This factory belongs to one manager, so what it keeps is collected with that manager:
 *
 * - a service repository (`ServiceEntityRepositoryInterface`: a host entity mapped on the same configuration that a host
 *   listener loads through the private manager) is resolved by the source configuration's factory: the container
 *   service, not keyed by manager, so it does not leak (and `DefaultRepositoryFactory` could not build it: its
 *   constructor takes a registry);
 * - any other repository is built here, with the mapped `repositoryClass` or the configuration's default one, and kept.
 *
 * @internal
 */
final class PrivateManagerRepositoryFactory implements RepositoryFactory
{
    /** @var array<class-string, EntityRepository<object>> */
    private array $repositories = [];

    /**
     * @param RepositoryFactory $source the repository factory of the source configuration (the application's)
     */
    public function __construct(
        private readonly RepositoryFactory $source,
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $entityName
     *
     * @return EntityRepository<T>
     */
    public function getRepository(EntityManagerInterface $entityManager, string $entityName): EntityRepository
    {
        $metadata = $entityManager->getClassMetadata($entityName);
        $class    = $metadata->customRepositoryClassName;
        if ($class !== null && is_a($class, ServiceEntityRepositoryInterface::class, true)) {
            /** @var EntityRepository<T> $service */
            $service = $this->source->getRepository($entityManager, $entityName);

            return $service;
        }

        /** @var EntityRepository<T> $repository */
        // @igor-ignore - Per-manager cache: this factory belongs to one private manager and is collected with it.
        $repository = $this->repositories[$metadata->getName()] ??= self::create($entityManager, $metadata, $class ?? $entityManager->getConfiguration()->getDefaultRepositoryClassName());

        return $repository;
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param class-string<EntityRepository<object>> $class
     *
     * @return EntityRepository<object>
     */
    public static function create(EntityManagerInterface $entityManager, ClassMetadata $metadata, string $class): EntityRepository
    {
        return new $class($entityManager, $metadata);
    }
}
