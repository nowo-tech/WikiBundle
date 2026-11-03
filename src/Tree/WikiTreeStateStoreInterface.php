<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tree;

use Nowo\WikiBundle\Entity\WikiSpace;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Fold state of the page tree per user and space (1.14): the ids of the pages a user expanded, applied by the server
 * before rendering (no flash). The default keeps it in the session ({@see SessionWikiTreeStateStore}); a host with
 * per-user UI preferences (e.g. a table) sets `tree.state_store` to its own service.
 */
interface WikiTreeStateStoreInterface
{
    /**
     * Ids of the pages the user expanded in the space (any order; ids of deleted pages are ignored by the tree).
     *
     * @return list<string>
     */
    public function expanded(UserInterface $user, WikiSpace $space): array;

    /**
     * Records that the user expanded (true) or collapsed (false) a page of the space.
     */
    public function setExpanded(UserInterface $user, WikiSpace $space, string $pageId, bool $expanded): void;
}
