<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Panel;

use Nowo\WikiBundle\Port\Internal\WikiPanelStateStoreInterface;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * Remembered side panel state, framework-free (1.36.0): since 2.0 the only host port; it replaced the removed 1.x
 * `WikiPanelStateStoreInterface` (`nowo_wiki.comments.state_store` names your service). Panels are
 * {@see WikiPanelStateStoreInterface::COMMENTS} and {@see WikiPanelStateStoreInterface::TREE_RAIL}.
 */
interface WikiPanelStatesInterface
{
    public function isOpen(WikiUserRef $user, string $panel): bool;

    public function setOpen(WikiUserRef $user, string $panel, bool $open): void;
}
