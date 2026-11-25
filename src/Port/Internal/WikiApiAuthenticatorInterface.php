<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port\Internal;

use Nowo\WikiBundle\Api\Hook\WikiApiRequestAuthenticatorInterface;
use Nowo\WikiBundle\Api\WikiApiAuthFailure;
use Nowo\WikiBundle\Api\WikiApiCredential;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Symfony\Component\HttpFoundation\Request;

/**
 * 1.24 (spec 218): the host's token check, required with `api.enabled` (the bundle stores no tokens). Called once per
 * request; revoked, expired, disabled, erased and removed-member states answer {@see WikiApiAuthFailure::Invalid} at this
 * request. {@see WikiApiCredential::fromRequest()} reads the header.
 *
 * @internal Bundle side of a host port since 2.0 (the 1.x host interface of this name was removed): a host implements
 *           {@see WikiApiRequestAuthenticatorInterface} instead.
 */
interface WikiApiAuthenticatorInterface
{
    public function authenticate(Request $request): WikiApiPrincipal|WikiApiAuthFailure;
}
