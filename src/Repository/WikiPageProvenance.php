<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Repository;

/**
 * Spec 234: who created a page and where its last write came from, read without the revision bodies — the
 * author id of its oldest stored revision (the creator: history thinning keeps one revision of every session, so the
 * oldest session, the creator's, always keeps one), and the channel, actor label and author id of its current revision.
 * Ids and labels are plain strings (framework-free).
 */
final readonly class WikiPageProvenance
{
    public function __construct(
        public ?string $creatorId,
        public ?string $channel,
        public ?string $actorLabel,
        public ?string $currentAuthorId,
    ) {
    }
}
