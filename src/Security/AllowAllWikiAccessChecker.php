<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Permissive checker used only when security.allow_unauthenticated is true (demo/dev).
 */
final class AllowAllWikiAccessChecker implements WikiAccessCheckerInterface
{
    public function canAccess(?UserInterface $user = null): bool
    {
        return true;
    }

    public function canList(?UserInterface $user = null): bool
    {
        return true;
    }

    public function canCreate(?UserInterface $user = null): bool
    {
        return true;
    }

    public function canEdit(?UserInterface $user = null): bool
    {
        return true;
    }

    public function canViewHistory(?UserInterface $user = null): bool
    {
        return true;
    }

    public function canArchive(?UserInterface $user = null): bool
    {
        return true;
    }

    public function canAskAi(?UserInterface $user = null): bool
    {
        return true;
    }

    public function canImport(?UserInterface $user = null): bool
    {
        return true;
    }

    public function canExport(?UserInterface $user = null): bool
    {
        return true;
    }
}
