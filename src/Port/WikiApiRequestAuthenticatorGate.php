<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use Nowo\WikiBundle\Api\Hook\WikiApiAuthenticatorInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiRequestAuthenticatorInterface;
use Nowo\WikiBundle\Api\WikiApiAuthFailure;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Symfony\Component\HttpFoundation\Request;

/**
 * Asks a framework-free {@see WikiApiRequestAuthenticatorInterface} (1.33.0) with the request context; the bearer
 * credential is its only credential.
 *
 * @internal
 */
final readonly class WikiApiRequestAuthenticatorGate implements WikiApiAuthenticatorInterface
{
    public function __construct(private WikiApiRequestAuthenticatorInterface $authenticator)
    {
    }

    public function authenticate(Request $request): WikiApiPrincipal|WikiApiAuthFailure
    {
        return $this->authenticator->authenticate(WikiRequestContexts::fromRequest($request, true));
    }
}
