<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ai;

use Nowo\WikiBundle\Ai\Exception\WikiAiUnavailableException;
use Nowo\WikiBundle\Entity\WikiSpace;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Fallback when {@see symfony/ai-bundle} is not installed or AI is disabled.
 */
final class NullWikiAiAssistant implements WikiAiAssistantInterface
{
    /**
     * Always false: AI is disabled or symfony/ai-bundle is not installed.
     *
     * @return bool whether the assistant can answer questions
     */
    public function isAvailable(): bool
    {
        return false;
    }

    /**
     * Always fails because no AI integration is configured.
     *
     * @param UserInterface $user user asking the question
     * @param string $question natural-language question
     * @param WikiSpace|null $space optional space to restrict the context to
     *
     * @throws WikiAiUnavailableException always
     *
     * @return WikiAiAnswer never returned
     */
    public function ask(UserInterface $user, string $question, ?WikiSpace $space = null): WikiAiAnswer
    {
        throw new WikiAiUnavailableException('Wiki AI is not configured. Install symfony/ai-bundle and enable nowo_wiki.ai.');
    }
}
