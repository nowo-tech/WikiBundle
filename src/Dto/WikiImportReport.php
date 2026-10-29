<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Dto;

/**
 * Summary of a wiki import run.
 */
final class WikiImportReport
{
    /** @var list<string> */
    public array $messages = [];

    /**
     * Creates an import report with initial counters.
     *
     * @param int $created number of pages created
     * @param int $updated number of existing pages that received a new revision
     * @param int $skipped number of documents skipped
     * @param int $failed number of documents that failed to import
     */
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
        public int $failed = 0,
    ) {
    }

    /**
     * Appends a per-document message to the report.
     *
     * @param string $message human-readable message to record
     */
    public function addMessage(string $message): void
    {
        // @igor-ignore - Not shared worker service state.
        $this->messages[] = $message;
    }
}
