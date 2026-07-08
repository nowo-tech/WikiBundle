<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Role-based default implementation of {@see WikiAccessCheckerInterface}.
 */
final readonly class ConfigurableWikiAccessChecker implements WikiAccessCheckerInterface
{
    /**
     * @param list<string> $adminRoles
     * @param list<string> $accessRoles
     * @param list<string> $listRoles
     * @param list<string> $createRoles
     * @param list<string> $editRoles
     * @param list<string> $historyRoles
     * @param list<string> $archiveRoles
     * @param list<string> $aiRoles
     * @param list<string> $importRoles
     * @param list<string> $exportRoles
     */
    public function __construct(
        private Security $security,
        private array $adminRoles,
        private array $accessRoles,
        private array $listRoles,
        private array $createRoles,
        private array $editRoles,
        private array $historyRoles,
        private array $archiveRoles,
        private array $aiRoles,
        private array $importRoles,
        private array $exportRoles,
    ) {
    }

    public function canAccess(?UserInterface $user = null): bool
    {
        return $this->isAdmin() || $this->hasAnyRole($this->accessRoles);
    }

    public function canList(?UserInterface $user = null): bool
    {
        return $this->isAdmin() || $this->hasAnyRole($this->listRoles);
    }

    public function canCreate(?UserInterface $user = null): bool
    {
        return $this->isAdmin() || $this->hasAnyRole($this->createRoles);
    }

    public function canEdit(?UserInterface $user = null): bool
    {
        return $this->isAdmin() || $this->hasAnyRole($this->editRoles);
    }

    public function canViewHistory(?UserInterface $user = null): bool
    {
        return $this->isAdmin() || $this->hasAnyRole($this->historyRoles);
    }

    public function canArchive(?UserInterface $user = null): bool
    {
        return $this->isAdmin() || $this->hasAnyRole($this->archiveRoles);
    }

    public function canAskAi(?UserInterface $user = null): bool
    {
        return $this->isAdmin() || $this->hasAnyRole($this->aiRoles);
    }

    public function canImport(?UserInterface $user = null): bool
    {
        return $this->isAdmin() || $this->hasAnyRole($this->importRoles);
    }

    public function canExport(?UserInterface $user = null): bool
    {
        return $this->isAdmin() || $this->hasAnyRole($this->exportRoles);
    }

    private function isAdmin(): bool
    {
        foreach ($this->adminRoles as $role) {
            if ($this->security->isGranted($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $roles
     */
    private function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->security->isGranted($role)) {
                return true;
            }
        }

        return false;
    }
}
