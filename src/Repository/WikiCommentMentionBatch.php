<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentThread;

/**
 * One page of {@see WikiCommentMentionRepositoryInterface::commentsMentioning()} /
 * {@see WikiCommentMentionRepositoryInterface::threadsMentioning()} (1.17).
 *
 * `loaded` lists the entities this lookup loaded itself; the others were already managed before it (the caller's own
 * instances, for example comments kept by `eraseAuthor()`) and must never be released (audit FG2-L1).
 *
 * @template T of WikiComment|WikiCommentThread
 */
final readonly class WikiCommentMentionBatch
{
    /**
     * @param list<T> $entities every candidate of the page, ordered by id
     * @param list<T> $loaded the candidates that were not managed before the lookup (the only ones that may be released)
     */
    public function __construct(
        public array $entities,
        public array $loaded = [],
    ) {
    }
}
