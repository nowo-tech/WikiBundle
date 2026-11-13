<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ai;

use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * The AI assistant, framework-free (1.33.0): the twin of the deprecated {@see WikiAiAssistantInterface} (alias either
 * interface to your service).
 */
interface WikiAiAnswererInterface
{
    /** Whether the assistant is configured and can answer questions. */
    public function isAvailable(): bool;

    /**
     * Answers a question from the wiki pages the user can access.
     *
     * @param WikiUserRef $user user asking
     * @param string $question natural-language question
     * @param WikiSpaceSnapshot|null $space optional space to restrict the context to
     */
    public function ask(WikiUserRef $user, string $question, ?WikiSpaceSnapshot $space = null): WikiAiReply;
}
