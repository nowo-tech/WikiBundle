<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ai;

/**
 * The answer of a framework-free AI assistant (1.32.0, {@see WikiAiAnswererInterface}): the text and the pages used as
 * context, by id. The bundle loads the pages, keeps those that exist in the named space and that the user may open,
 * and turns the reply into a {@see WikiAiAnswer}.
 */
final readonly class WikiAiReply
{
    /**
     * @param string $answer answer text
     * @param list<array{pageId: string, spaceId: string, excerpt: string}> $sources pages used as context
     */
    public function __construct(
        public string $answer,
        public array $sources = [],
    ) {
    }
}
