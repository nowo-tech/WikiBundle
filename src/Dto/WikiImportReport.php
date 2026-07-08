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

    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
        public int $failed = 0,
    ) {
    }

    public function addMessage(string $message): void
    {
        $this->messages[] = $message;
    }
}
