<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Panel;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Open / closed state of the wiki side panels per user (1.16, a stable public contract): remembered per user, not
 * per page, like the tree's fold state, and applied by the server before rendering (no flash of the wrong state).
 * The default keeps it in the session ({@see SessionWikiPanelStateStore}); `comments.state_store` names a host
 * service (for example a user-preferences table); 1.29: the page tree rail ({@see self::TREE_RAIL}) uses the same store.
 */
interface WikiPanelStateStoreInterface
{
    /** The comments panel (1.16). */
    public const COMMENTS = 'comments';

    /**
     * The page tree rail (1.29, `web_ui.tree_rail`): "open" means the tree is collapsed to the rail, so a user with
     * nothing stored keeps the expanded tree.
     */
    public const TREE_RAIL = 'tree_rail';

    /**
     * Whether the user left the panel open (false when nothing is stored).
     *
     * @param string $panel panel name ({@see self::COMMENTS}, {@see self::TREE_RAIL})
     */
    public function isOpen(UserInterface $user, string $panel): bool;

    /**
     * Records that the user opened (true) or closed (false) the panel.
     *
     * @param string $panel panel name ({@see self::COMMENTS}, {@see self::TREE_RAIL})
     */
    public function setOpen(UserInterface $user, string $panel, bool $open): void;
}
