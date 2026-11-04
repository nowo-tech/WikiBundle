<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Panel;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Open / closed state of the wiki side panels per user (1.16, a stable public contract): remembered per user, not
 * per page, like the tree's fold state, and applied by the server before rendering (no flash of the wrong state).
 * The default keeps it in the session ({@see SessionWikiPanelStateStore}); `comments.state_store` names a host
 * service (for example a user-preferences table).
 */
interface WikiPanelStateStoreInterface
{
    /** The comments panel (1.16). */
    public const COMMENTS = 'comments';

    /**
     * Whether the user left the panel open (false when nothing is stored).
     *
     * @param string $panel panel name ({@see self::COMMENTS})
     */
    public function isOpen(UserInterface $user, string $panel): bool;

    /**
     * Records that the user opened (true) or closed (false) the panel.
     *
     * @param string $panel panel name ({@see self::COMMENTS})
     */
    public function setOpen(UserInterface $user, string $panel, bool $open): void;
}
