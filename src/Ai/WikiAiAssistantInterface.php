<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ai;

use Nowo\WikiBundle\Entity\WikiSpace;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Answers questions about wiki documentation (Symfony AI when enabled).
 */
interface WikiAiAssistantInterface
{
    public function isAvailable(): bool;

    public function ask(UserInterface $user, string $question, ?WikiSpace $space = null): WikiAiAnswer;
}
