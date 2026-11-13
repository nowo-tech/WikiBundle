<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\User;

use Nowo\WikiBundle\ValueObject\WikiUserRef;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The security user the bundle works with when a framework-free host hands it a {@see WikiUserRef} without a Symfony
 * user object (1.34.0: a twin port such as the REST API authenticator, the id operations). Its id is the reference's;
 * with `user_reference: entity` it is stored as `user_class` by that id when the reference names `user_class` (or an
 * accepted class), else refused like any other foreign user (audit JX-W1).
 *
 * @internal
 */
final readonly class WikiRefUser implements UserInterface
{
    /**
     * @param WikiUserRef $ref the reference
     */
    public function __construct(private WikiUserRef $ref)
    {
    }

    /**
     * @return WikiUserRef the reference this user stands for
     */
    public function ref(): WikiUserRef
    {
        return $this->ref;
    }

    /**
     * @return string|null the referenced user's id
     */
    public function getId(): ?string
    {
        return $this->ref->id;
    }

    /**
     * @return string the reference's class
     */
    public function getUserClass(): string
    {
        return $this->ref->userClass;
    }

    /**
     * @return non-empty-string the security identifier, else the id, else a placeholder that matches no grant
     */
    public function getUserIdentifier(): string
    {
        $identifier = $this->ref->identifier !== '' ? $this->ref->identifier : (string) $this->ref->id;

        return $identifier !== '' ? $identifier : "\0nowo-wiki-anonymous";
    }

    /**
     * @return list<string> the reference's roles
     */
    public function getRoles(): array
    {
        return $this->ref->roles;
    }

    /**
     * Nothing to erase: the reference holds no credential.
     */
    public function eraseCredentials(): void
    {
    }
}
