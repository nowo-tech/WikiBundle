<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Symfony\Bundle\SecurityBundle\Security;
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
    public function __construct(
        private RequestStack $requestStack,
        private ?Security $security = null,
    ) {
    }

    public function isTokenTrusted(): bool
    {
        $request = $this->requestStack->getMainRequest();
        if ($request === null) {
            return true;
        }

        if (!$this->security instanceof Security) {
            return $request->attributes->has('_firewall_context');
        }

        return $this->security->getFirewallConfig($request)?->isSecurityEnabled() === true;
    }
}
