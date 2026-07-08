<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Application-specific access rules for wiki manage routes.
 */
interface WikiAccessCheckerInterface
{
    public function canAccess(?UserInterface $user = null): bool;

    public function canList(?UserInterface $user = null): bool;

    public function canCreate(?UserInterface $user = null): bool;

    public function canEdit(?UserInterface $user = null): bool;

    public function canViewHistory(?UserInterface $user = null): bool;

    public function canArchive(?UserInterface $user = null): bool;

    public function canAskAi(?UserInterface $user = null): bool;

    public function canImport(?UserInterface $user = null): bool;

    public function canExport(?UserInterface $user = null): bool;
}
