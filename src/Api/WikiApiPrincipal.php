<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api;

use Nowo\WikiBundle\Api\Hook\WikiApiRequestAuthenticatorInterface;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Security\Core\User\UserInterface;

use function trigger_deprecation;

/**
 * 1.24 (spec 218): who a request is, as the host's authenticator answers it, for this request only. It holds no secret:
 * `$tokenId` is an opaque id.
 */
final readonly class WikiApiPrincipal
{
    /**
     * The person behind the token, read with {@see self::userRef()}. 2.0: private (the 1.x public `$user` was
     * removed), so a host that still reads `->user` gets an Error instead of a silent null.
     */
    private UserInterface $user;

    /** The token's level. */
    public WikiApiLevel $level;

    /** The token's reach. */
    public WikiApiWhere $where;

    /** Token id (audit); an opaque id, never a secret. */
    public string $tokenId;

    /** Token name (audit). */
    public string $tokenName;

    /** `api`, or `mcp` for an in-process caller (spec 219), passed on to the audit event. */
    public string $channel;

    /**
     * @deprecated since 2.0, removed in 3.0: build principals with {@see self::of()} and a {@see WikiUserRef} (REQ-ARCH-001
     *             rule 3: the authenticator port takes and returns only bundle value objects)
     *
     * @param UserInterface $user the person behind the token
     * @param string $channel `api`, or `mcp` for an in-process caller (spec 219), passed on to the audit event
     */
    public function __construct(
        UserInterface $user,
        WikiApiLevel $level,
        WikiApiWhere $where,
        string $tokenId,
        string $tokenName,
        string $channel = 'api',
    ) {
        trigger_deprecation('nowo-tech/wiki-bundle', '2.0', 'Building %s with a Symfony user (its constructor) is deprecated, 3.0 removes it: use %s::of() with a WikiUserRef.', self::class, self::class);
        $this->user      = $user;
        $this->level     = $level;
        $this->where     = $where;
        $this->tokenId   = $tokenId;
        $this->tokenName = $tokenName;
        $this->channel   = $channel;
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
        // Without the deprecated constructor (3.0 keeps only this path): the readonly members are set once, by reflection.
        $principal = (new ReflectionClass(self::class))->newInstanceWithoutConstructor();
        foreach (['user' => WikiUserRefs::securityUser($user), 'level' => $level, 'where' => $where, 'tokenId' => $tokenId, 'tokenName' => $tokenName, 'channel' => $channel] as $name => $value) {
            (new ReflectionProperty(self::class, $name))->setValue($principal, $value);
        }

        return $principal;
    }

    /**
     * 1.36: the person as a framework-free reference.
     *
     * @return WikiUserRef the person
     */
    public function userRef(): WikiUserRef
    {
        return WikiUserRefs::fromUser($this->user);
    }
}
