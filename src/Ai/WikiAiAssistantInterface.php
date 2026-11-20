<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ai;

use Nowo\WikiBundle\Entity\WikiSpace;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Answers questions about wiki documentation (Symfony AI when enabled).
 *
 * @deprecated since 1.36, removed in 2.0: implement {@see WikiAiAnswererInterface} instead
 *             (1.49: a host service that still implements it triggers a deprecation notice)
 */
interface WikiAiAssistantInterface
{
    /**
     * Tells whether the assistant is configured and can answer questions.
     *
     * @return bool whether the assistant can answer questions
     */
    public function isAvailable(): bool;

    /**
     * Answers a question using the wiki pages the user can access.
     *
     * @param UserInterface $user user asking; only spaces they can access feed the context
     * @param string $question natural-language question
     * @param WikiSpace|null $space optional space to restrict the context to
     *
     * @return WikiAiAnswer answer text and the pages used as context
     */
    public function ask(UserInterface $user, string $question, ?WikiSpace $space = null): WikiAiAnswer;
}
