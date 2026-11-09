<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\User;

use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use function class_exists;
use function get_debug_type;
use function is_object;
use function ltrim;
use function sprintf;

/**
 * Default user reference resolver: `user_class` instances unchanged; an instance of a class listed in
 * `nowo_wiki.accepted_user_classes` with a usable `getId()` becomes `getReference(user_class, id)` on the wiki entity
 * manager (no query); any other user is refused (AccessDeniedException). Without an entity manager the user is
 * returned unchanged.
 */
final readonly class DoctrineWikiUserReferenceResolver implements WikiUserReferenceResolverInterface
{
    /**
     * @param string $userClass `nowo_wiki.user_class`
     * @param WikiEntityManagerProvider|null $entityManagers wiki entity manager (`database.entity_manager`)
     * @param WikiUserClassPolicy|null $classes accepted security-user classes (null = `user_class` only)
     */
    public function __construct(
        private string $userClass,
        private ?WikiEntityManagerProvider $entityManagers = null,
        private ?WikiUserClassPolicy $classes = null,
    ) {
    }

    /**
     * The user itself when it is a `user_class` instance (or without an entity manager); a reference of `user_class`
     * with its raw `getId()` for an instance of an `accepted_user_classes` entry with a usable id.
     *
     * @throws AccessDeniedException for any other user (fail closed, audit JX-W1)
     */
    public function reference(object $user): object
    {
        $class = ltrim($this->userClass, '\\');
        if ($user instanceof $class || !$this->entityManagers instanceof WikiEntityManagerProvider || !class_exists($class)) {
            return $user;
        }

        // JX-W1: only listed wrapper classes are mapped by id. Any other user fails closed: passing it on would let
        // Doctrine bind another entity class by its id (Admin#5 written or matched as user_class#5).
        $id = ($this->classes ?? new WikiUserClassPolicy($class))->idOf($user);
        if ($id === null) {
            throw new AccessDeniedException(sprintf('nowo_wiki: a %s is not a wiki user (not nowo_wiki.user_class nor listed in nowo_wiki.accepted_user_classes with a usable getId()).', get_debug_type($user)));
        }

        $reference = $this->entityManagers->get()->getReference($class, $id);

        return is_object($reference) ? $reference : $user;
    }
}
