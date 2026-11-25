<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Hook;

use Nowo\WikiBundle\Api\WikiApiAuthFailure;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Port\Internal\WikiApiAuthenticatorInterface;
use Nowo\WikiBundle\ValueObject\WikiRequestContext;

/**
 * REST API authentication, framework-free (1.36.0): since 2.0 the only host port; it replaced the removed 1.x `WikiApiAuthenticatorInterface`
 * (`nowo_wiki.api.hooks.authenticator` names your service). The bearer credential is `$request->credential`; build the
 * principal with {@see WikiApiPrincipal::of()}.
 */
interface WikiApiRequestAuthenticatorInterface
{
    public function authenticate(WikiRequestContext $request): WikiApiPrincipal|WikiApiAuthFailure;
}
