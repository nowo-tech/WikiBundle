<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The one read rule of a page (1.13.2): {@see WikiAction::View} on the page **and on every ancestor**. A hidden page
 * hides its whole sub-tree — on the page view, history, diff, attachments, co-editing, search and AI hits, as in the
 * sidebar tree, the export and the page-link / mention resolver. A parent cycle in the data reads as hidden.
 */
final class WikiPageVisibility
{
    /**
     * Whether the user may read the page and every page above it.
     */
    public static function isReadable(WikiSpaceAccessCheckerInterface $checker, UserInterface $user, WikiSpace $space, WikiPage $page): bool
    {
        $seen = [];
        for ($node = $page; $node instanceof WikiPage; $node = $node->getParent()) {
            if (isset($seen[$node->getId()]) || !$checker->isGranted($user, WikiAction::View, $space, $node)) {
                return false;
            }
            $seen[$node->getId()] = true;
        }

        return true;
    }
}
