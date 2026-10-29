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
     * Creates the checker from the configured role lists.
     *
     * @param Security $security Symfony security helper used for role checks
     * @param list<string> $adminRoles roles granted every permission
     * @param list<string> $accessRoles roles allowed to enter the wiki manage area
     * @param list<string> $listRoles roles allowed to list spaces and pages
     * @param list<string> $createRoles roles allowed to create spaces and pages
     * @param list<string> $editRoles roles allowed to edit pages
     * @param list<string> $historyRoles roles allowed to view revision history
     * @param list<string> $archiveRoles roles allowed to archive pages
     * @param list<string> $aiRoles roles allowed to ask the AI assistant
     * @param list<string> $importRoles roles allowed to import documents
     * @param list<string> $exportRoles roles allowed to export content
     * @param WikiTokenGuard|null $tokenGuard guard telling whether the token is trusted (null = always trusted)
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
        private ?WikiTokenGuard $tokenGuard = null,
    ) {
    }

    /**
     * Tells whether the user may enter the wiki manage area: granted to admin roles or any configured role for this permission.
     * Always denied when the security token is not trusted for the current request.
     *
     * @param UserInterface|null $user user to check (ignored; the current security token is evaluated)
     *
     * @return bool true when access is granted
     */
    public function canAccess(?UserInterface $user = null): bool
    {
        return $this->isAdmin() || $this->hasAnyRole($this->accessRoles);
    }

    /**
     * Tells whether the user may list wiki spaces and pages: granted to admin roles or any configured role for this permission.
     * Always denied when the security token is not trusted for the current request.
     *
     * @param UserInterface|null $user user to check (ignored; the current security token is evaluated)
     *
     * @return bool true when access is granted
     */
    public function canList(?UserInterface $user = null): bool
    {
        return $this->isAdmin() || $this->hasAnyRole($this->listRoles);
    }

    /**
     * Tells whether the user may create wiki spaces and pages: granted to admin roles or any configured role for this permission.
     * Always denied when the security token is not trusted for the current request.
     *
     * @param UserInterface|null $user user to check (ignored; the current security token is evaluated)
     *
     * @return bool true when access is granted
     */
    public function canCreate(?UserInterface $user = null): bool
    {
        return $this->isAdmin() || $this->hasAnyRole($this->createRoles);
    }

    /**
     * Tells whether the user may edit wiki pages: granted to admin roles or any configured role for this permission.
     * Always denied when the security token is not trusted for the current request.
     *
     * @param UserInterface|null $user user to check (ignored; the current security token is evaluated)
     *
     * @return bool true when access is granted
     */
    public function canEdit(?UserInterface $user = null): bool
    {
        return $this->isAdmin() || $this->hasAnyRole($this->editRoles);
    }

    /**
     * Tells whether the user may view the revision history of wiki pages: granted to admin roles or any configured role for this permission.
     * Always denied when the security token is not trusted for the current request.
     *
     * @param UserInterface|null $user user to check (ignored; the current security token is evaluated)
     *
     * @return bool true when access is granted
     */
    public function canViewHistory(?UserInterface $user = null): bool
    {
        return $this->isAdmin() || $this->hasAnyRole($this->historyRoles);
    }

    /**
     * Tells whether the user may archive wiki pages: granted to admin roles or any configured role for this permission.
     * Always denied when the security token is not trusted for the current request.
     *
     * @param UserInterface|null $user user to check (ignored; the current security token is evaluated)
     *
     * @return bool true when access is granted
     */
    public function canArchive(?UserInterface $user = null): bool
    {
        return $this->isAdmin() || $this->hasAnyRole($this->archiveRoles);
    }

    /**
     * Tells whether the user may ask the wiki AI assistant: granted to admin roles or any configured role for this permission.
     * Always denied when the security token is not trusted for the current request.
     *
     * @param UserInterface|null $user user to check (ignored; the current security token is evaluated)
     *
     * @return bool true when access is granted
     */
    public function canAskAi(?UserInterface $user = null): bool
    {
        return $this->isAdmin() || $this->hasAnyRole($this->aiRoles);
    }

    /**
     * Tells whether the user may import documents into the wiki: granted to admin roles or any configured role for this permission.
     * Always denied when the security token is not trusted for the current request.
     *
     * @param UserInterface|null $user user to check (ignored; the current security token is evaluated)
     *
     * @return bool true when access is granted
     */
    public function canImport(?UserInterface $user = null): bool
    {
        return $this->isAdmin() || $this->hasAnyRole($this->importRoles);
    }

    /**
     * Tells whether the user may export wiki content: granted to admin roles or any configured role for this permission.
     * Always denied when the security token is not trusted for the current request.
     *
     * @param UserInterface|null $user user to check (ignored; the current security token is evaluated)
     *
     * @return bool true when access is granted
     */
    public function canExport(?UserInterface $user = null): bool
    {
        return $this->isAdmin() || $this->hasAnyRole($this->exportRoles);
    }

    private function isAdmin(): bool
    {
        if (!$this->isTokenTrusted()) {
            return false;
        }

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
        if (!$this->isTokenTrusted()) {
            return false;
        }

        foreach ($roles as $role) {
            if ($this->security->isGranted($role)) {
                return true;
            }
        }

        return false;
    }

    private function isTokenTrusted(): bool
    {
        return !$this->tokenGuard instanceof WikiTokenGuard || $this->tokenGuard->isTokenTrusted();
    }
}
