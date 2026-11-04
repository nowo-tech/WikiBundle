<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiRevisionLabel;

/**
 * Named versions (1.15).
 */
interface WikiRevisionLabelRepositoryInterface
{
    public function save(WikiRevisionLabel $label): void;

    public function findForRevision(WikiPageRevision $revision): ?WikiRevisionLabel;

    /**
     * Labels of the given revisions, keyed by revision id.
     *
     * @param list<WikiPageRevision> $revisions
     *
     * @return array<string, string>
     */
    public function labelsFor(array $revisions): array;
}
