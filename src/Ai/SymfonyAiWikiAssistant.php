<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ai;

use InvalidArgumentException;
use Nowo\WikiBundle\Entity\WikiSpace;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Security\Core\User\UserInterface;

use function sprintf;
use function trim;

/**
 * Uses a configured Symfony AI agent to answer wiki questions.
 */
final readonly class SymfonyAiWikiAssistant implements WikiAiAssistantInterface
{
    private const DEFAULT_SYSTEM_PROMPT = <<<'PROMPT'
You are a documentation assistant for an internal wiki. Answer using only the provided context or tool results.
Cite page titles when relevant. If the answer is not in the context, say you could not find it in the wiki.
Respond in the same language as the user's question.
PROMPT;

    public function __construct(
        private AgentInterface $agent,
        private WikiContextRetriever $contextRetriever,
        private bool $useContextInjection,
        private int $maxContextPages,
        private int $maxContextChars,
        private ?string $systemPrompt,
    ) {
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function ask(UserInterface $user, string $question, ?WikiSpace $space = null): WikiAiAnswer
    {
        $question = trim($question);
        if ($question === '') {
            throw new InvalidArgumentException('Question must not be empty.');
        }

        $sources  = [];
        $messages = new MessageBag();

        if ($this->useContextInjection) {
            $retrieved = $this->contextRetriever->retrieve(
                $user,
                $question,
                $space,
                $this->maxContextPages,
                $this->maxContextChars,
            );
            $sources = $retrieved['sources'];
            $messages->add(Message::forSystem($this->buildSystemPrompt($retrieved['context'])));
        }

        $messages->add(Message::ofUser($question));
        $response = $this->agent->call($messages);

        return new WikiAiAnswer($response->getContent(), $sources);
    }

    private function buildSystemPrompt(string $context): string
    {
        $base = $this->systemPrompt ?? self::DEFAULT_SYSTEM_PROMPT;

        if ($context === '') {
            return $base . "\n\nNo matching wiki pages were found for this question.";
        }

        return sprintf("%s\n\n## Wiki context\n\n%s", $base, $context);
    }
}
