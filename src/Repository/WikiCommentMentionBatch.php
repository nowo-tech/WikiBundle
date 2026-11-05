<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentThread;

use function array_key_last;
use function count;

/**
 * One page of {@see WikiCommentMentionRepositoryInterface::commentsMentioning()} /
 * {@see WikiCommentMentionRepositoryInterface::threadsMentioning()} (1.17).
 *
 * The walk goes on while `scanned` (the ids of the page query) is the full limit and resumes after `lastId`, so a
 * candidate deleted between the id query and the load never ends it early (audit FG3-L1).
 *
 * `loaded` lists the entities this lookup loaded itself; the others were already managed before it (the caller's own
 * instances, for example comments kept by `eraseAuthor()`) and must never be released (audit FG2-L1).
 *
 * @template T of WikiComment|WikiCommentThread
 */
final readonly class WikiCommentMentionBatch
{
    /** How many candidate ids the page query returned (audit FG3-L1: the walk goes on while a page is full). */
    public int $scanned;

    /** The last candidate id of the page query: the next page starts after it (null for an empty page). */
    public ?string $lastId;

    /**
     * @param list<T> $entities every candidate of the page that still exists, ordered by id
     * @param list<T> $loaded the candidates that were not managed before the lookup (the only ones that may be released)
     * @param int|null $scanned the candidate ids the page query returned, rows deleted before loading included
     *                          (default: count($entities))
     * @param string|null $lastId the last of those ids (default: the last entity's id)
     */
    public function __construct(
        public array $entities,
        public array $loaded = [],
        ?int $scanned = null,
        ?string $lastId = null,
    ) {
        $last          = $entities === [] ? null : $entities[array_key_last($entities)];
        $this->scanned = $scanned ?? count($entities);
        $this->lastId  = $lastId ?? $last?->getId();
    }
}
