<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Application-specific access rules for wiki manage routes.
 */
interface WikiAccessCheckerInterface
{
    /**
     * Tells whether the user may enter the wiki manage area.
     *
     * @param UserInterface|null $user user to check, or null (implementations may rely on the current security token)
     *
     * @return bool true when access is granted
     */
    public function canAccess(?UserInterface $user = null): bool;

    /**
     * Tells whether the user may list wiki spaces and pages.
     *
     * @param UserInterface|null $user user to check, or null (implementations may rely on the current security token)
     *
     * @return bool true when access is granted
     */
    public function canList(?UserInterface $user = null): bool;

    /**
     * Tells whether the user may create wiki spaces and pages.
     *
     * @param UserInterface|null $user user to check, or null (implementations may rely on the current security token)
     *
     * @return bool true when access is granted
     */
    public function canCreate(?UserInterface $user = null): bool;

    /**
     * Tells whether the user may edit wiki pages.
     *
     * @param UserInterface|null $user user to check, or null (implementations may rely on the current security token)
     *
     * @return bool true when access is granted
     */
    public function canEdit(?UserInterface $user = null): bool;

    /**
     * Tells whether the user may view the revision history of wiki pages.
     *
     * @param UserInterface|null $user user to check, or null (implementations may rely on the current security token)
     *
     * @return bool true when access is granted
     */
    public function canViewHistory(?UserInterface $user = null): bool;

    /**
     * Tells whether the user may archive wiki pages.
     *
     * @param UserInterface|null $user user to check, or null (implementations may rely on the current security token)
     *
     * @return bool true when access is granted
     */
    public function canArchive(?UserInterface $user = null): bool;

    /**
     * Tells whether the user may ask the wiki AI assistant.
     *
     * @param UserInterface|null $user user to check, or null (implementations may rely on the current security token)
     *
     * @return bool true when access is granted
     */
    public function canAskAi(?UserInterface $user = null): bool;

    /**
     * Tells whether the user may import documents into the wiki.
     *
     * @param UserInterface|null $user user to check, or null (implementations may rely on the current security token)
     *
     * @return bool true when access is granted
     */
    public function canImport(?UserInterface $user = null): bool;

    /**
     * Tells whether the user may export wiki content.
     *
     * @param UserInterface|null $user user to check, or null (implementations may rely on the current security token)
     *
     * @return bool true when access is granted
     */
    public function canExport(?UserInterface $user = null): bool;
}
