<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Permissive checker used only when security.allow_unauthenticated is true (demo/dev).
 */
final class AllowAllWikiAccessChecker implements WikiAccessCheckerInterface
{
    /**
     * Tells whether the user may enter the wiki manage area; always grants access.
     *
     * @param UserInterface|null $user user to check (ignored)
     *
     * @return bool always true
     */
    public function canAccess(?UserInterface $user = null): bool
    {
        return true;
    }

    /**
     * Tells whether the user may list wiki spaces and pages; always grants access.
     *
     * @param UserInterface|null $user user to check (ignored)
     *
     * @return bool always true
     */
    public function canList(?UserInterface $user = null): bool
    {
        return true;
    }

    /**
     * Tells whether the user may create wiki spaces and pages; always grants access.
     *
     * @param UserInterface|null $user user to check (ignored)
     *
     * @return bool always true
     */
    public function canCreate(?UserInterface $user = null): bool
    {
        return true;
    }

    /**
     * Tells whether the user may edit wiki pages; always grants access.
     *
     * @param UserInterface|null $user user to check (ignored)
     *
     * @return bool always true
     */
    public function canEdit(?UserInterface $user = null): bool
    {
        return true;
    }

    /**
     * Tells whether the user may view the revision history of wiki pages; always grants access.
     *
     * @param UserInterface|null $user user to check (ignored)
     *
     * @return bool always true
     */
    public function canViewHistory(?UserInterface $user = null): bool
    {
        return true;
    }

    /**
     * Tells whether the user may archive wiki pages; always grants access.
     *
     * @param UserInterface|null $user user to check (ignored)
     *
     * @return bool always true
     */
    public function canArchive(?UserInterface $user = null): bool
    {
        return true;
    }

    /**
     * Tells whether the user may ask the wiki AI assistant; always grants access.
     *
     * @param UserInterface|null $user user to check (ignored)
     *
     * @return bool always true
     */
    public function canAskAi(?UserInterface $user = null): bool
    {
        return true;
    }

    /**
     * Tells whether the user may import documents into the wiki; always grants access.
     *
     * @param UserInterface|null $user user to check (ignored)
     *
     * @return bool always true
     */
    public function canImport(?UserInterface $user = null): bool
    {
        return true;
    }

    /**
     * Tells whether the user may export wiki content; always grants access.
     *
     * @param UserInterface|null $user user to check (ignored)
     *
     * @return bool always true
     */
    public function canExport(?UserInterface $user = null): bool
    {
        return true;
    }
}
