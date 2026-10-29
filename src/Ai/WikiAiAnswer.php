<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ai;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;

/**
 * AI assistant response with optional source pages used as context.
 */
final readonly class WikiAiAnswer
{
    /**
     * Holds the assistant answer and the pages used as context.
     *
     * @param string $answer answer text returned by the agent
     * @param list<array{page: WikiPage, space: WikiSpace, excerpt: string}> $sources pages used as context
     */
    public function __construct(
        public string $answer,
        public array $sources = [],
    ) {
    }
}
