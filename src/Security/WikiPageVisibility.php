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
     *
     * @param array<string, bool> $decisions page id => readable with its ancestors, shared across calls so a list of
     *                                       hits walks each branch once (audit DJ-I1)
     */
    public static function isReadable(WikiSpaceAccessCheckerInterface $checker, UserInterface $user, WikiSpace $space, WikiPage $page, array &$decisions = []): bool
    {
        $chain  = [];
        $result = true;
        for ($node = $page; $node instanceof WikiPage; $node = $node->getParent()) {
            $id = $node->getId();
            if (isset($decisions[$id])) {
                $result = $decisions[$id];
                break;
            }
            // A page met twice is a parent cycle: hidden.
            if (isset($chain[$id])) {
                $result = false;
                break;
            }
            $chain[$id] = true;
            if (!$checker->isGranted($user, WikiAction::View, $space, $node)) {
                $result = false;
                break;
            }
        }
        // Every page walked shares the outcome: below a hidden page everything is hidden.
        foreach ($chain as $id => $unused) {
            $decisions[$id] = $result;
        }

        return $result;
    }
}
