<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ai;

use InvalidArgumentException;
use Nowo\WikiBundle\Entity\WikiSpace;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Security\Core\User\UserInterface;
use Throwable;
use UnexpectedValueException;

use function count;
use function get_debug_type;
use function is_string;
use function mb_strlen;
use function sprintf;
use function trim;

/**
 * Uses a configured Symfony AI agent to answer wiki questions.
 *
 * Every outbound agent call is logged (start / success / failure) without the question text,
 * the answer or any credential (REQ-OBS-001).
 */
final readonly class SymfonyAiWikiAssistant implements WikiAiAssistantInterface
{
    private const DEFAULT_SYSTEM_PROMPT = <<<'PROMPT'
You are a documentation assistant for an internal wiki. Answer using only the provided context or tool results.
Cite page titles when relevant. If the answer is not in the context, say you could not find it in the wiki.
Respond in the same language as the user's question.
PROMPT;

    /**
     * Wires the Symfony AI agent, the context retriever and the AI options.
     *
     * @param AgentInterface $agent configured Symfony AI agent
     * @param WikiContextRetriever $contextRetriever retrieves wiki excerpts injected as system context
     * @param bool $useContextInjection whether to inject retrieved wiki context into the system prompt
     * @param int $maxContextPages maximum number of pages retrieved as context
     * @param int $maxContextChars character budget of the injected context
     * @param string|null $systemPrompt custom system prompt, or null for the default one
     * @param LoggerInterface|null $logger logger (defaults to a NullLogger)
     */
    public function __construct(
        private AgentInterface $agent,
        private WikiContextRetriever $contextRetriever,
        private bool $useContextInjection,
        private int $maxContextPages,
        private int $maxContextChars,
        private ?string $systemPrompt,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    private LoggerInterface $logger;

    /**
     * Always true: this implementation is only registered when the AI integration is enabled.
     *
     * @return bool whether the assistant can answer questions
     */
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * Sends the question (plus optional wiki context) to the configured agent.
     *
     * @param UserInterface $user user asking; only spaces they can access feed the context
     * @param string $question natural-language question
     * @param WikiSpace|null $space optional space to restrict the context to
     *
     * @throws InvalidArgumentException when the question is empty
     * @throws UnexpectedValueException when the agent returns a non-text result
     * @throws Throwable when the agent call fails (e.g. HTTP timeout); logged first
     *
     * @return WikiAiAnswer answer text and the pages used as context
     */
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
        $logContext = [
            'bundle'          => 'nowo_wiki',
            'action'          => 'ai_ask',
            'space'           => $space?->getSlug(),
            'question_length' => mb_strlen($question),
            'context_pages'   => count($sources),
        ];
        $this->logger->info('Wiki AI agent call started.', $logContext);

        try {
            $content = $this->agent->call($messages)->getContent();
        } catch (Throwable $exception) {
            $this->logger->error('Wiki AI agent call failed.', $logContext + ['exception_class' => $exception::class]);

            throw $exception;
        }

        if (!is_string($content)) {
            $this->logger->error('Wiki AI agent returned a non-text result.', $logContext + ['result_type' => get_debug_type($content)]);

            throw new UnexpectedValueException('The wiki AI agent returned a non-text result.');
        }

        $this->logger->info('Wiki AI agent call succeeded.', $logContext + ['answer_length' => mb_strlen($content)]);

        return new WikiAiAnswer($content, $sources);
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
