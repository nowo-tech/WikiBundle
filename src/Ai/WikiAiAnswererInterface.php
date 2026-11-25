<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ai;

use Nowo\WikiBundle\Port\Internal\WikiAiAssistantInterface;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * The AI assistant, framework-free (1.36.0): since 2.0 the only host port; it replaced the removed 1.x `WikiAiAssistantInterface` (alias this
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
