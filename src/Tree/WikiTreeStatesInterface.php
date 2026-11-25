<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tree;

use Nowo\WikiBundle\Port\Internal\WikiTreeStateStoreInterface;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * Remembered page tree folds, framework-free (1.36.0): since 2.0 the only host port; it replaced the removed 1.x `WikiTreeStateStoreInterface`
 * (`nowo_wiki.tree.state_store` names your service). The space is named by id.
 */
interface WikiTreeStatesInterface
{
    /**
     * @return list<string> ids of the pages the user expanded in the space
     */
    public function expanded(WikiUserRef $user, string $spaceId): array;

    public function setExpanded(WikiUserRef $user, string $spaceId, string $pageId, bool $expanded): void;
}
