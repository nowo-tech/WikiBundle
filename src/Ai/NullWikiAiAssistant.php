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
    public function isAvailable(): bool
    {
        return false;
    }

    public function ask(UserInterface $user, string $question, ?WikiSpace $space = null): WikiAiAnswer
    {
        throw new WikiAiUnavailableException('Wiki AI is not configured. Install symfony/ai-bundle and enable nowo_wiki.ai.');
    }
}
