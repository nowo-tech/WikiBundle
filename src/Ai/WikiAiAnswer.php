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
     * @param list<array{page: WikiPage, space: WikiSpace, excerpt: string}> $sources
     */
    public function __construct(
        public string $answer,
        public array $sources = [],
    ) {
    }
}
