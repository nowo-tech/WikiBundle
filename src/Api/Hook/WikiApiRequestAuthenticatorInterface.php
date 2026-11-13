<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Hook;

use Nowo\WikiBundle\Api\WikiApiAuthFailure;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\ValueObject\WikiRequestContext;

/**
 * REST API authentication, framework-free (1.34.0): the twin of the deprecated {@see WikiApiAuthenticatorInterface}
 * (`nowo_wiki.api.hooks.authenticator` takes either). The bearer credential is `$request->credential`; build the
 * principal with {@see WikiApiPrincipal::of()}.
 */
interface WikiApiRequestAuthenticatorInterface
{
    public function authenticate(WikiRequestContext $request): WikiApiPrincipal|WikiApiAuthFailure;
}
