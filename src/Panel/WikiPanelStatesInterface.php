<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Panel;

use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * Remembered side panel state, framework-free (1.34.0): the twin of the deprecated
 * {@see WikiPanelStateStoreInterface} (`nowo_wiki.comments.state_store` takes either). Panels are
 * {@see WikiPanelStateStoreInterface::COMMENTS} and {@see WikiPanelStateStoreInterface::TREE_RAIL}.
 */
interface WikiPanelStatesInterface
{
    public function isOpen(WikiUserRef $user, string $panel): bool;

    public function setOpen(WikiUserRef $user, string $panel, bool $open): void;
}
