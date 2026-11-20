<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\App;

use Nowo\WikiBundle\LinkPicker\WikiLinkTenantResolverInterface;
use Nowo\WikiBundle\Search\WikiSearchTenantResolverInterface;
use Nowo\WikiBundle\Search\WikiSearchTenantsResolverInterface;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;

/**
 * 1.46: a host's tenants for the SearchKit group tests, scripted through static state: the tenant key of each space
 * (by slug) and the tenant the current request works in (per viewer).
 */
final class ApiSearchTenants implements WikiLinkTenantResolverInterface, WikiSearchTenantResolverInterface, WikiSearchTenantsResolverInterface
{
    /** @var array<string, string> space slug => tenant key */
    public static array $spaces = [];

    /** @var array<string, string|null> user identifier => current tenant key */
    public static array $current = [];

    /** @var array<string, list<string>> user identifier => current tenant keys (1.47; wins over when set) */
    public static array $lists = [];

    public static function reset(): void
    {
        self::$spaces  = [];
        self::$current = [];
        self::$lists   = [];
    }

    public function tenantOf(WikiSpaceSnapshot $space): ?string
    {
        return self::$spaces[$space->slug] ?? null;
    }

    public function currentTenant(string $userIdentifier): ?string
    {
        return self::$current[$userIdentifier] ?? null;
    }

    public function currentTenants(string $userIdentifier): array
    {
        if (isset(self::$lists[$userIdentifier])) {
            return self::$lists[$userIdentifier];
        }
        $current = self::$current[$userIdentifier] ?? null;

        return $current === null ? [] : [$current];
    }
}
