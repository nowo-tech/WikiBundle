<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * 1.24 (spec 218 FR-008): one successful write through the REST API, dispatched as {@see WikiEvents::API_WRITE} after
 * the write committed, for the host's audit. It carries ids and field names only: never a secret, a value or page text.
 * Without a listener the API refuses every write before writing (503 `AUDIT_001`).
 */
final class WikiApiWriteEvent extends Event
{
    /**
     * @param string $operation `page.create`, `page.update`, `comment.delete`, …
     * @param array<string, string> $targetIds other ids the write touched (revision, thread, comment, file)
     * @param list<string> $changedFields field names only
     */
    public function __construct(
        public readonly WikiApiPrincipal $principal,
        public readonly string $operation,
        public readonly string $wikiSlug,
        public readonly ?string $spaceId = null,
        public readonly ?string $pageId = null,
        public readonly array $targetIds = [],
        public readonly array $changedFields = [],
    ) {
    }
}
