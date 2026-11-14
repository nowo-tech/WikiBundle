<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Nowo\WikiBundle\User\WikiUsers;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;

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
     * @param WikiUsers|null $users 1.36 (`database.user_reference: id`): the id mode (a user is then resolved to
     *                              their stored id, {@see WikiStoredUserRef})
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $userClass,
        private ?WikiUsers $users = null,
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
        if ($this->users?->idMode() === true) {
            // 1.36 (database.user_reference: id): the stored id itself. The rows name the user by this id only, so an
            // erasure reaches them whether the host still has the user or not, and whatever object its directory
            // returns (audit OX-L2: a read model is not a wiki user and would be refused).
            if (!WikiStoredUserRef::isValid($identifier)) {
                throw new InvalidArgumentException(sprintf('User id "%s" cannot be matched exactly (whitespace at an edge, control or format characters, or more than 128 characters).', $identifier));
            }

            return new WikiStoredUserRef($identifier);
        }

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
