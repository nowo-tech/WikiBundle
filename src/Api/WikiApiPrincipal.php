<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api;

use Error;
use Nowo\WikiBundle\Api\Hook\WikiApiRequestAuthenticatorInterface;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use Symfony\Component\Security\Core\User\UserInterface;

use function property_exists;
use function sprintf;
use function trigger_deprecation;
use function trigger_error;

use const E_USER_WARNING;

/**
 * 1.24 (spec 218): who a request is, as the host's authenticator answers it, for this request only. It holds no secret:
 * `$tokenId` is an opaque id.
 *
 * @property UserInterface $user the person behind the token
 *                               (deprecated since 1.36, a runtime notice since 1.49, removed in 2.0): read
 *                               {@see self::userRef()}
 */
final readonly class WikiApiPrincipal
{
    /** The person behind the token ({@see self::securityUser()}; `$principal->user` is deprecated). */
    private UserInterface $securityUser;

    /**
     * @param UserInterface $user the person behind the token; deprecated since 1.36 as a public member (a Symfony
     *                            type): read {@see self::userRef()}, and build principals with {@see self::of()}
     * @param string $channel `api`, or `mcp` for an in-process caller (spec 219), passed on to the audit event
     */
    public function __construct(
        UserInterface $user,
        public WikiApiLevel $level,
        public WikiApiWhere $where,
        public string $tokenId,
        public string $tokenName,
        public string $channel = 'api',
    ) {
        $this->securityUser = $user;
    }

    /**
     * 1.36: a principal for a framework-free authenticator
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
     * 1.36: the person as a framework-free reference (use it instead of {@see self::$user}).
     *
     * @return WikiUserRef the person
     */
    public function userRef(): WikiUserRef
    {
        return WikiUserRefs::fromUser($this->securityUser);
    }

    /**
     * 1.49: `$principal->user` still works and triggers a deprecation notice; 2.0 removes it.
     */
    public function __get(string $name): mixed
    {
        if ($name === 'user') {
            trigger_deprecation('nowo-tech/wiki-bundle', '1.49', 'Reading %s::$user (a Symfony user) is deprecated, 2.0 removes it: read userRef() (a framework-free WikiUserRef) instead.', self::class);

            return $this->securityUser;
        }
        if (property_exists($this, $name)) {
            // A private member, refused from outside as before.
            throw new Error(sprintf('Cannot access private property %s::$%s', self::class, $name));
        }
        trigger_error(sprintf('Undefined property: %s::$%s', self::class, $name), E_USER_WARNING);

        return null;
    }

    public function __isset(string $name): bool
    {
        return $name === 'user';
    }

    /**
     * The user as the bundle works with it. Bundle use only: removed in 2.0 with {@see self::$user}.
     *
     * @internal
     */
    public function securityUser(): UserInterface
    {
        return $this->securityUser;
    }
}
