<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiRevisionLabel;

/**
 * Named versions (1.15).
 *
 * Persistence adapter of the bundle's own entities, not a host port (REQ-ARCH-001): it is entity-typed by design;
 * hosts reach wiki data through the services, the id operations and the events.
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
