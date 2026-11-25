<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Nowo\WikiBundle\Port\Internal\WikiAccessCheckerInterface;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * Global wiki rights, framework-free (1.36.0): since 2.0 the only host port; it replaced the removed 1.x `WikiAccessCheckerInterface`
 * (`nowo_wiki.security.access_checker` names your service). The user is a {@see WikiUserRef}; null when the bundle asks
 * without one (decide from your own security context, as the default checker does from the token).
 */
interface WikiAccessPolicyInterface
{
    public function canAccess(?WikiUserRef $user = null): bool;

    public function canList(?WikiUserRef $user = null): bool;

    public function canCreate(?WikiUserRef $user = null): bool;

    public function canEdit(?WikiUserRef $user = null): bool;

    public function canViewHistory(?WikiUserRef $user = null): bool;

    public function canArchive(?WikiUserRef $user = null): bool;

    public function canAskAi(?WikiUserRef $user = null): bool;

    public function canImport(?WikiUserRef $user = null): bool;

    public function canExport(?WikiUserRef $user = null): bool;
}
