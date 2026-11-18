<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use InvalidArgumentException;
use Nowo\WikiBundle\Api\Security\WikiApiRequestListener;
use Nowo\WikiBundle\User\WikiUserId;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Role-based default implementation of {@see WikiAccessCheckerInterface}: the admin roles grant everything; any other
 * permission needs one of the access roles (the general gate, 1.10) and one of the roles of that action. An empty list
 * grants nothing.
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
     * Tells whether the user may enter the wiki manage area: granted to admin roles, or to users holding an access role and a role of this permission.
     * Always denied when the security token is not trusted for the current request.
     *
     * @param UserInterface|null $user user to check (1.44: decided for that user when it is not the session's user; otherwise the current security token is evaluated)
     *
     * @return bool true when access is granted
     */
    public function canAccess(?UserInterface $user = null): bool
    {
        return $this->isAdmin($user) || $this->hasAnyRole($this->accessRoles, $user);
    }

    /**
     * Tells whether the user may list wiki spaces and pages: granted to admin roles, or to users holding an access role and a role of this permission.
     * Always denied when the security token is not trusted for the current request.
     *
     * @param UserInterface|null $user user to check (1.44: decided for that user when it is not the session's user; otherwise the current security token is evaluated)
     *
     * @return bool true when access is granted
     */
    public function canList(?UserInterface $user = null): bool
    {
        return $this->isAdmin($user) || ($this->hasAnyRole($this->accessRoles, $user) && $this->hasAnyRole($this->listRoles, $user));
    }

    /**
     * Tells whether the user may create wiki spaces and pages: granted to admin roles, or to users holding an access role and a role of this permission.
     * Always denied when the security token is not trusted for the current request.
     *
     * @param UserInterface|null $user user to check (1.44: decided for that user when it is not the session's user; otherwise the current security token is evaluated)
     *
     * @return bool true when access is granted
     */
    public function canCreate(?UserInterface $user = null): bool
    {
        return $this->isAdmin($user) || ($this->hasAnyRole($this->accessRoles, $user) && $this->hasAnyRole($this->createRoles, $user));
    }

    /**
     * Tells whether the user may edit wiki pages: granted to admin roles, or to users holding an access role and a role of this permission.
     * Always denied when the security token is not trusted for the current request.
     *
     * @param UserInterface|null $user user to check (1.44: decided for that user when it is not the session's user; otherwise the current security token is evaluated)
     *
     * @return bool true when access is granted
     */
    public function canEdit(?UserInterface $user = null): bool
    {
        return $this->isAdmin($user) || ($this->hasAnyRole($this->accessRoles, $user) && $this->hasAnyRole($this->editRoles, $user));
    }

    /**
     * Tells whether the user may view the revision history of wiki pages: granted to admin roles, or to users holding an access role and a role of this permission.
     * Always denied when the security token is not trusted for the current request.
     *
     * @param UserInterface|null $user user to check (1.44: decided for that user when it is not the session's user; otherwise the current security token is evaluated)
     *
     * @return bool true when access is granted
     */
    public function canViewHistory(?UserInterface $user = null): bool
    {
        return $this->isAdmin($user) || ($this->hasAnyRole($this->accessRoles, $user) && $this->hasAnyRole($this->historyRoles, $user));
    }

    /**
     * Tells whether the user may archive wiki pages: granted to admin roles, or to users holding an access role and a role of this permission.
     * Always denied when the security token is not trusted for the current request.
     *
     * @param UserInterface|null $user user to check (1.44: decided for that user when it is not the session's user; otherwise the current security token is evaluated)
     *
     * @return bool true when access is granted
     */
    public function canArchive(?UserInterface $user = null): bool
    {
        return $this->isAdmin($user) || ($this->hasAnyRole($this->accessRoles, $user) && $this->hasAnyRole($this->archiveRoles, $user));
    }

    /**
     * Tells whether the user may ask the wiki AI assistant: granted to admin roles, or to users holding an access role and a role of this permission.
     * Always denied when the security token is not trusted for the current request.
     *
     * @param UserInterface|null $user user to check (1.44: decided for that user when it is not the session's user; otherwise the current security token is evaluated)
     *
     * @return bool true when access is granted
     */
    public function canAskAi(?UserInterface $user = null): bool
    {
        return $this->isAdmin($user) || ($this->hasAnyRole($this->accessRoles, $user) && $this->hasAnyRole($this->aiRoles, $user));
    }

    /**
     * Tells whether the user may import documents into the wiki: granted to admin roles, or to users holding an access role and a role of this permission.
     * Always denied when the security token is not trusted for the current request.
     *
     * @param UserInterface|null $user user to check (1.44: decided for that user when it is not the session's user; otherwise the current security token is evaluated)
     *
     * @return bool true when access is granted
     */
    public function canImport(?UserInterface $user = null): bool
    {
        return $this->isAdmin($user) || ($this->hasAnyRole($this->accessRoles, $user) && $this->hasAnyRole($this->importRoles, $user));
    }

    /**
     * Tells whether the user may export wiki content: granted to admin roles, or to users holding an access role and a role of this permission.
     * Always denied when the security token is not trusted for the current request.
     *
     * @param UserInterface|null $user user to check (1.44: decided for that user when it is not the session's user; otherwise the current security token is evaluated)
     *
     * @return bool true when access is granted
     */
    public function canExport(?UserInterface $user = null): bool
    {
        return $this->isAdmin($user) || ($this->hasAnyRole($this->accessRoles, $user) && $this->hasAnyRole($this->exportRoles, $user));
    }

    private function isAdmin(?UserInterface $user): bool
    {
        return $this->hasAnyRole($this->adminRoles, $user);
    }

    /**
     * Whether the user holds one of the roles. 1.44 (audit TI-M1): a user other than the session's (a search run for
     * another identifier inside a request, e.g. SearchKit's `searchFor()`) is decided for that user
     * (`Security::isGrantedForUser()`, its own roles), never on the session's token. The session's user, and a call with
     * no user or with no session user, are decided on the current token, which must be trusted.
     *
     * @param list<string> $roles
     */
    private function hasAnyRole(array $roles, ?UserInterface $user = null): bool
    {
        $session = $this->security->getUser();
        if ($user instanceof UserInterface && $session instanceof UserInterface && !$this->isSessionUser($user, $session)) {
            foreach ($roles as $role) {
                try {
                    if ($this->security->isGrantedForUser($user, $role)) {
                        return true;
                    }
                } catch (InvalidArgumentException) {
                    // Audit TI-D1: IS_AUTHENTICATED_* / PUBLIC_ACCESS cannot be decided for a user without a session (an
                    // offline token): not granted, never a 500.
                }
            }

            return false;
        }
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

    /**
     * Whether the user is the session's user: the same identifier, or (audit TI-D2) the same exact id — the REST API's
     * principal may be a security adapter of another class that the API already matched to the firewall's user by id
     * ({@see WikiApiRequestListener}); it is judged on the firewall's token, as before 1.44.
     */
    private function isSessionUser(UserInterface $user, UserInterface $session): bool
    {
        if ($user->getUserIdentifier() === $session->getUserIdentifier()) {
            return true;
        }
        $id = WikiUserId::of($user);

        return $id !== null && $id === WikiUserId::of($session);
    }

    private function isTokenTrusted(): bool
    {
        return !$this->tokenGuard instanceof WikiTokenGuard || $this->tokenGuard->isTokenTrusted();
    }
}
