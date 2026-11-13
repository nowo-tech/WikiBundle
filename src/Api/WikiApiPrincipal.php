<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api;

use Nowo\WikiBundle\Api\Hook\WikiApiRequestAuthenticatorInterface;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * 1.24 (spec 218): who a request is, as the host's authenticator answers it, for this request only. It holds no secret:
 * `$tokenId` is an opaque id.
 */
final readonly class WikiApiPrincipal
{
    /**
     * @param UserInterface $user the person behind the token; deprecated since 1.33 as a public member (a Symfony
     *                            type): read {@see self::userRef()}, and build principals with {@see self::of()}
     * @param string $channel `api`, or `mcp` for an in-process caller (spec 219), passed on to the audit event
     */
    public function __construct(
        public UserInterface $user,
        public WikiApiLevel $level,
        public WikiApiWhere $where,
        public string $tokenId,
        public string $tokenName,
        public string $channel = 'api',
    ) {
    }

    /**
     * 1.33: a principal for a framework-free authenticator
     * ({@see WikiApiRequestAuthenticatorInterface}): the person as a {@see WikiUserRef}.
     * The bundle works with the Symfony user the reference was built from, else with a user standing for it (stored by
     * its id).
     *
     * @param WikiUserRef $user the person behind the token
     * @param WikiApiLevel $level the token's level
     * @param WikiApiWhere $where the token's reach
     * @param string $tokenId token id (audit)
     * @param string $tokenName token name (audit)
     * @param string $channel `api` or `mcp`
     *
     * @return self the principal
     */
    public static function of(WikiUserRef $user, WikiApiLevel $level, WikiApiWhere $where, string $tokenId, string $tokenName, string $channel = 'api'): self
    {
        return new self(WikiUserRefs::securityUser($user), $level, $where, $tokenId, $tokenName, $channel);
    }

    /**
     * 1.33: the person as a framework-free reference (use it instead of {@see self::$user}).
     *
     * @return WikiUserRef the person
     */
    public function userRef(): WikiUserRef
    {
        return WikiUserRefs::fromUser($this->user);
    }
}
