<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use DateTimeImmutable;
use RuntimeException;

/**
 * 1.24 (spec 218): a save or a restore started from a revision that is no longer the current one. Nothing was
 * written; the caller reads the page again and decides.
 */
final class WikiRevisionConflictException extends RuntimeException
{
    /**
     * @param int $expectedRevision the revision the caller started from
     * @param int $currentRevision the current revision of the page (0: none)
     * @param object|null $updatedBy author of the current revision
     * @param DateTimeImmutable|null $updatedAt when the current revision was written
     */
    public function __construct(
        public readonly int $expectedRevision,
        public readonly int $currentRevision,
        public readonly ?object $updatedBy = null,
        public readonly ?DateTimeImmutable $updatedAt = null,
    ) {
        parent::__construct('The page changed since the revision this save started from.');
    }
}
