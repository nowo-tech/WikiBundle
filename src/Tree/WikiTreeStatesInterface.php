<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tree;

use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * Remembered page tree folds, framework-free (1.34.0): the twin of the deprecated {@see WikiTreeStateStoreInterface}
 * (`nowo_wiki.tree.state_store` takes either). The space is named by id.
 */
interface WikiTreeStatesInterface
{
    /**
     * @return list<string> ids of the pages the user expanded in the space
     */
    public function expanded(WikiUserRef $user, string $spaceId): array;

    public function setExpanded(WikiUserRef $user, string $spaceId, string $pageId, bool $expanded): void;
}
