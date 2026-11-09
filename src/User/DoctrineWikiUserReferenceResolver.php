<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\User;

use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;

use function class_exists;
use function is_object;
use function ltrim;
use function method_exists;

/**
 * Default user reference resolver: `user_class` instances unchanged; any other user with a non-empty `getId()` becomes
 * `getReference(user_class, id)` on the wiki entity manager (no query). Without an entity manager or an id the user is
 * returned unchanged (the 1.26 behaviour).
 */
final readonly class DoctrineWikiUserReferenceResolver implements WikiUserReferenceResolverInterface
{
    /**
     * @param string $userClass `nowo_wiki.user_class`
     * @param WikiEntityManagerProvider|null $entityManagers wiki entity manager (`database.entity_manager`)
     */
    public function __construct(
        private string $userClass,
        private ?WikiEntityManagerProvider $entityManagers = null,
    ) {
    }

    /**
     * The user itself when it is a `user_class` instance, else a reference of `user_class` with its raw `getId()`.
     */
    public function reference(object $user): object
    {
        $class = ltrim($this->userClass, '\\');
        if ($user instanceof $class || !$this->entityManagers instanceof WikiEntityManagerProvider || !class_exists($class)) {
            return $user;
        }

        $id = method_exists($user, 'getId') ? $user->getId() : null;
        if ($id === null || $id === '') {
            return $user;
        }

        $reference = $this->entityManagers->get()->getReference($class, $id);

        return is_object($reference) ? $reference : $user;
    }
}
