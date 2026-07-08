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
    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $userClass,
    ) {
    }

    public function resolveByIdentifier(string $identifier): object
    {
        /** @phpstan-ignore argument.templateType (user_class is configured at runtime) */
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
