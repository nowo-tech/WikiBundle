<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

use function sprintf;

/**
 * Resolves application user entities for CLI import operations.
 */
final readonly class WikiAuthorResolver
{
    /**
     * Stores the entity manager and the application user class.
     *
     * @param EntityManagerInterface $entityManager entity manager used to query users
     * @param string $userClass FQCN of the application user entity
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $userClass,
    ) {
    }

    /**
     * Finds a user by primary key, then by email when the user class has {@code getEmail()}.
     *
     * @param string $identifier user id or email
     *
     * @throws InvalidArgumentException when the user class does not exist or no user matches
     *
     * @return object the application user entity
     */
    public function resolveByIdentifier(string $identifier): object
    {
        if (!class_exists($this->userClass)) {
            throw new InvalidArgumentException(sprintf('User class "%s" does not exist.', $this->userClass));
        }

        $repository = $this->entityManager->getRepository($this->userClass);

        $user = $repository->find($identifier);
        if ($user !== null) {
            return $user;
        }

        if (method_exists($this->userClass, 'getEmail')) {
            $user = $repository->findOneBy(['email' => $identifier]);
            if ($user !== null) {
                return $user;
            }
        }

        throw new InvalidArgumentException(sprintf('User "%s" not found.', $identifier));
    }
}
