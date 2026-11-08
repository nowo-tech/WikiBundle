<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Hook;

use Nowo\WikiBundle\Api\WikiApiAuthFailure;
use Nowo\WikiBundle\Api\WikiApiCredential;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Symfony\Component\HttpFoundation\Request;

/**
 * 1.24 (spec 218): the host's token check, required with `api.enabled` (the bundle stores no tokens). Called once per
 * request; revoked, expired, disabled, erased and removed-member states answer {@see WikiApiAuthFailure::Invalid} at this
 * request. {@see WikiApiCredential::fromRequest()} reads the header.
 */
interface WikiApiAuthenticatorInterface
{
    public function authenticate(Request $request): WikiApiPrincipal|WikiApiAuthFailure;
}
