<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\TwoZero\Fixtures\AsAliasRemoved;

use Nowo\WikiBundle\Security\WikiAccessPolicyInterface;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * A twin implementation aliased under the removed 1.x id: in 2.0 that alias would do nothing (the bundle's default
 * would answer), so the build must fail and point to the twin's alias.
 */
#[AsAlias('Nowo\WikiBundle\Security\WikiAccessCheckerInterface')]
final class HostAccessPolicy implements WikiAccessPolicyInterface
{
    public function canAccess(?WikiUserRef $user = null): bool
    {
        return false;
    }

    public function canList(?WikiUserRef $user = null): bool
    {
        return false;
    }

    public function canCreate(?WikiUserRef $user = null): bool
    {
        return false;
    }

    public function canEdit(?WikiUserRef $user = null): bool
    {
        return false;
    }

    public function canViewHistory(?WikiUserRef $user = null): bool
    {
        return false;
    }

    public function canArchive(?WikiUserRef $user = null): bool
    {
        return false;
    }

    public function canAskAi(?WikiUserRef $user = null): bool
    {
        return false;
    }

    public function canImport(?WikiUserRef $user = null): bool
    {
        return false;
    }

    public function canExport(?WikiUserRef $user = null): bool
    {
        return false;
    }
}
