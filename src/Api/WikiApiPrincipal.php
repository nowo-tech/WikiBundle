<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * 1.24 (spec 218): who a request is, as the host's authenticator answers it, for this request only. It holds no secret:
 * `$tokenId` is an opaque id.
 */
final readonly class WikiApiPrincipal
{
    /**
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
}
