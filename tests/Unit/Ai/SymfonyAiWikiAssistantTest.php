<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Ai;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;
use Nowo\WikiBundle\Ai\SymfonyAiWikiAssistant;
use Nowo\WikiBundle\Ai\WikiContextRetriever;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;

use function count;

final class SymfonyAiWikiAssistantTest extends TestCase
{
    public function testIsAvailable(): void
    {
        self::assertTrue($this->assistant(withContext: false)->isAvailable());
    }

    public function testAskRejectsEmptyQuestion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->assistant(withContext: false)->ask(new TestUser(), '   ');
    }

    public function testAskWithoutContextInjection(): void
    {
        $agent                  = new AgentStub('Answer text');
        $agent->expectsMessages = static fn (MessageBag $bag): bool => count($bag->getMessages()) === 1;

        $answer = $this->assistant($agent, withContext: false)->ask(new TestUser(), 'How do I deploy?');

        self::assertSame('Answer text', $answer->answer);
        self::assertSame([], $answer->sources);
    }

    public function testAskWithContextInjectionUsesCustomPrompt(): void
    {
        $user  = new TestUser();
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::User, 'user-1');
        $page  = new WikiPage($space, 'deploy', 'Deploy');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Steps</p>', $user));

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$space]);

        $retriever = new WikiContextRetriever(
            $this->createSearchService([[$page, 'contentHtml' => '<p>Steps</p>']]),
            $resolver,
        );

        $assistant = new SymfonyAiWikiAssistant(
            new AgentStub('Follow the runbook.'),
            $retriever,
            true,
            5,
            5000,
            'Custom system prompt.',
        );

        $answer = $assistant->ask($user, 'deploy steps', $space);

        self::assertSame('Follow the runbook.', $answer->answer);
        self::assertNotEmpty($answer->sources);
    }

    public function testAskWithEmptyContextAppendsNoPagesMessage(): void
    {
        $agent                  = new AgentStub('No info.');
        $agent->expectsMessages = static function (MessageBag $bag): bool {
            foreach ($bag->getMessages() as $message) {
                if (str_contains($message->getContent(), 'No matching wiki pages')) {
                    return true;
                }
            }

            return false;
        };

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([]);

        $assistant = new SymfonyAiWikiAssistant(
            $agent,
            new WikiContextRetriever(new WikiSearchService($this->createMock(EntityManagerInterface::class)), $resolver),
            true,
            5,
            5000,
            null,
        );

        $answer = $assistant->ask(new TestUser(), 'unknown topic');

        self::assertSame('No info.', $answer->answer);
    }

    /**
     * @param list<array{0: WikiPage, contentHtml: string}> $rows
     */
    private function createSearchService(array $rows): WikiSearchService
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($rows);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('innerJoin')->willReturnSelf();
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        return new WikiSearchService($em);
    }

    private function assistant(?AgentInterface $agent = null, bool $withContext = true): SymfonyAiWikiAssistant
    {
        $agent ??= new AgentStub('default answer');

        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([]);

        return new SymfonyAiWikiAssistant(
            $agent,
            new WikiContextRetriever(
                new WikiSearchService($this->createMock(EntityManagerInterface::class)),
                $resolver,
            ),
            $withContext,
            8,
            12000,
            null,
        );
    }
}

final class AgentStub implements AgentInterface
{
    /** @var callable|null */
    public $expectsMessages;

    public function __construct(
        private readonly string $content,
    ) {
    }

    public function call(MessageBag $messages, array $options = []): ResultInterface
    {
        if ($this->expectsMessages !== null) {
            Assert::assertTrue(($this->expectsMessages)($messages));
        }

        return new ResultStub($this->content);
    }

    public function getName(): string
    {
        return 'wiki_assistant_stub';
    }
}

final readonly class ResultStub implements ResultInterface
{
    public function __construct(
        private string $content,
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getMetadata(): Metadata
    {
        return new Metadata([]);
    }

    public function getRawResult(): ?RawResultInterface
    {
        return null;
    }

    public function setRawResult(mixed $rawResult): void
    {
    }
}
