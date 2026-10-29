<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tells whether the security token can be trusted for the current request.
 *
 * Without `services_resetter`, the token storage keeps the previous request's token when the current
 * request is not handled by a secured firewall. Wiki access decisions only use the token when the main
 * request went through one; outside HTTP (CLI) the token storage is trusted as is.
 */
final readonly class WikiTokenGuard
{
    /**
     * Creates the guard.
     *
     * @param RequestStack $requestStack request stack used to read the main request
     * @param Security|null $security security helper used to read the firewall config, or null to fall back to the `_firewall_context` request attribute
     */
    public function __construct(
        private RequestStack $requestStack,
        private ?Security $security = null,
    ) {
    }

    /**
     * Tells whether the security token can be trusted for the current request.
     * Returns true outside HTTP (no main request); otherwise true only when the main request went through a secured firewall.
     *
     * @return bool true when the token can be used for access decisions
     */
    public function isTokenTrusted(): bool
    {
        $request = $this->requestStack->getMainRequest();
        if (!$request instanceof Request) {
            return true;
        }

        if (!$this->security instanceof Security) {
            return $request->attributes->has('_firewall_context');
        }

        return $this->security->getFirewallConfig($request)?->isSecurityEnabled() === true;
    }
}
