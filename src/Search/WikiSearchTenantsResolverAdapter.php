<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Search;

/**
 * 1.47.1: what the container aliases {@see WikiSearchTenantsResolverInterface} to. Whatever `search_kit_provider.tenant_resolver`
 * names, the list interface is always a list: a service that implements it answers itself, one that only implements the
 * single-key {@see WikiSearchTenantResolverInterface} answers a list of one, anything else no tenant. Stateless (FrankenPHP
 * worker mode without kernel reset): every call asks the inner service again.
 */
final readonly class WikiSearchTenantsResolverAdapter implements WikiSearchTenantsResolverInterface
{
    public function __construct(private object $inner)
    {
    }

    public function currentTenants(string $userIdentifier): array
    {
        if ($this->inner instanceof WikiSearchTenantsResolverInterface) {
            return $this->inner->currentTenants($userIdentifier);
        }
        if ($this->inner instanceof WikiSearchTenantResolverInterface) {
            $key = $this->inner->currentTenant($userIdentifier);

            return $key === null ? [] : [$key];
        }

        return [];
    }
}
