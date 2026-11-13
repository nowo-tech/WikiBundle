<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * Global wiki rights, framework-free (1.33.0): the twin of the deprecated {@see WikiAccessCheckerInterface}
 * (`nowo_wiki.security.access_checker` takes either). The user is a {@see WikiUserRef}; null when the bundle asks
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
