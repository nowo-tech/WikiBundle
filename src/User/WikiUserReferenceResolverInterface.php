<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\User;

/**
 * Port (1.27): turns the user handed to the wiki (the security-token user, an `$actor` / `$author` argument) into the
 * object stored in the user relations (revision author, link creator, uploader, comment author, reaction user,
 * thread creator / resolver).
 *
 * The default ({@see DoctrineWikiUserReferenceResolver}) keeps `nowo_wiki.user_class` instances and turns any other
 * user exposing `getId()` (a security user that is no Doctrine entity, a DTO, a read model) into
 * `getReference(user_class, id)` on the wiki entity manager, without a query. Alias the interface to your own service
 * when the ids differ.
 */
interface WikiUserReferenceResolverInterface
{
    /**
     * The object to store / query as the user relation (the user itself when it already is a `user_class` instance).
     */
    public function reference(object $user): object;
}
